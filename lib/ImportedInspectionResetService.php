<?php

declare(strict_types=1);

use RedBeanPHP\R;

/**
 * Creates a reversible reset point and clears only rebuildable import data.
 *
 * Native Prüfweb records (`source_type = manual`) are intentionally outside
 * this service. A caller must inspect the dry-run result before executing it.
 */
final class ImportedInspectionResetService
{
    /** @return array<string,int> */
    public static function preview(): array
    {
        // Every non-native inspection can be reconstructed from the staged
        // sources. This includes the candidate workflow's `reconciled` rows;
        // leaving those behind made old, storage-slot-based pseudo numbers
        // survive a supposedly clean rebuild.
        $imported = self::rebuildableInspectionWhere();
        return [
            'imported_inspections' => (int) R::getCell("SELECT COUNT(*) FROM inspection WHERE {$imported}"),
            'manual_inspections_kept' => (int) R::getCell("SELECT COUNT(*) FROM inspection WHERE source_type = 'manual'"),
            'orphan_devices_after_reset' => (int) R::getCell("SELECT COUNT(*) FROM device d WHERE EXISTS (SELECT 1 FROM inspection i WHERE i.device_id = d.id AND {$imported}) AND NOT EXISTS (SELECT 1 FROM inspection i WHERE i.device_id = d.id AND i.source_type = 'manual')"),
            'billing_invoices_to_reset' => (int) R::getCell('SELECT COUNT(*) FROM billinginvoice'),
            'billing_exports_to_reset' => (int) R::getCell('SELECT COUNT(*) FROM billingexport'),
        ];
    }

    /**
     * Takes an SQLite online backup into the application data directory.
     * A non-SQLite DSN is rejected rather than risking an incomplete copy.
     */
    public static function backup(): string
    {
        $database = app_database_path();
        if ($database === '' || str_contains($database, ':')) {
            throw new RuntimeException('Automatisches Backup ist nur für eine SQLite-Datenbank möglich. Für diese Datenbankart zuerst ein konsistentes Betreiber-Backup erstellen.');
        }
        if (!is_file($database)) throw new RuntimeException('Die PrüfApp-Datenbank wurde nicht gefunden.');
        if (!class_exists(SQLite3::class)) throw new RuntimeException('Die PHP-Erweiterung SQLite3 fehlt; Backup kann nicht sicher erstellt werden.');

        $directory = app_data_root() . '/backups';
        if (!is_dir($directory) && !mkdir($directory, 0770, true) && !is_dir($directory)) {
            throw new RuntimeException('Das Backup-Verzeichnis konnte nicht erstellt werden.');
        }
        $target = $directory . '/before-import-rebuild-' . date('Ymd-His') . '.sqlite';
        $source = new SQLite3($database, SQLITE3_OPEN_READONLY);
        $destination = new SQLite3($target, SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE);
        try {
            if (!$source->backup($destination)) throw new RuntimeException('SQLite-Online-Backup fehlgeschlagen.');
        } finally {
            $destination->close();
            $source->close();
        }
        if (!is_file($target) || filesize($target) === 0) throw new RuntimeException('SQLite-Backup wurde nicht vollständig angelegt.');
        file_put_contents($target . '.sha256', hash_file('sha256', $target) . '  ' . basename($target) . "\n", LOCK_EX);
        return $target;
    }

    /** @return array<string,int|string> */
    public static function execute(string $backupPath): array
    {
        if (!is_file($backupPath) || !is_file($backupPath . '.sha256')) {
            throw new InvalidArgumentException('Ein geprüftes Backup muss vor dem Import-Reset vorhanden sein.');
        }
        $expected = trim((string) strtok((string) file_get_contents($backupPath . '.sha256'), ' '));
        if ($expected === '' || !hash_equals($expected, (string) hash_file('sha256', $backupPath))) {
            throw new RuntimeException('Die Prüfsumme des Backups stimmt nicht; der Reset wurde nicht ausgeführt.');
        }

        $preview = self::preview();
        $rebuildable = self::rebuildableInspectionWhere();
        $ids = array_map('intval', R::getCol("SELECT id FROM inspection WHERE {$rebuildable}"));
        $deviceIds = array_map('intval', R::getCol("SELECT DISTINCT device_id FROM inspection WHERE {$rebuildable} AND device_id > 0"));
        R::begin();
        try {
            if ($ids !== []) {
                $marks = implode(',', array_fill(0, count($ids), '?'));
                $sessionIds = array_map('intval', R::getCol("SELECT id FROM inspection_companion_session WHERE inspection_id IN ({$marks})", $ids));
                if ($sessionIds !== []) {
                    R::exec('DELETE FROM inspection_companion_item WHERE session_id IN (' . implode(',', array_fill(0, count($sessionIds), '?')) . ')', $sessionIds);
                    R::exec('DELETE FROM inspection_companion_session WHERE id IN (' . implode(',', array_fill(0, count($sessionIds), '?')) . ')', $sessionIds);
                }
                foreach (['inspection_answer', 'inspection_measurement', 'inspection_diagnostic', 'inspection_source_snapshot', 'inspection_report_asset', 'billinginvoiceitem', 'device_finding'] as $table) {
                    R::exec("DELETE FROM {$table} WHERE inspection_id IN ({$marks})", $ids);
                }
                R::exec("DELETE FROM device_media WHERE inspection_id IN ({$marks})", $ids);
                R::exec("DELETE FROM inspectiondupreview WHERE inspection_id IN ({$marks}) OR peer_inspection_id IN ({$marks})", array_merge($ids, $ids));
                R::exec("DELETE FROM inspection WHERE id IN ({$marks})", $ids);
            }
            // Invoices and drafts were explicitly declared disposable for this
            // rebuild. Clear their history, then reset any remaining native
            // inspection to the initial billing state.
            foreach (['billinginvoiceposition', 'billingregietransfer', 'billinginvoiceitem', 'billingexport', 'billinginvoice'] as $table) {
                R::exec("DELETE FROM {$table}");
            }
            R::exec("UPDATE inspection SET billing_exported_at=NULL, billing_export_id='', billing_exported_by='', billing_eligibility='not_billable', billing_status='not_exported', billing_active_invoice_item_id=NULL, billing_last_error='', billing_last_export_id=NULL");
            $orphanIds = $deviceIds === [] ? [] : array_map('intval', R::getCol(
                'SELECT id FROM device WHERE id IN (' . implode(',', array_fill(0, count($deviceIds), '?')) . ') AND NOT EXISTS (SELECT 1 FROM inspection WHERE inspection.device_id = device.id)',
                $deviceIds
            ));
            if ($orphanIds !== []) {
                $orphanMarks = implode(',', array_fill(0, count($orphanIds), '?'));
                $mediaIds = array_map('intval', R::getCol("SELECT id FROM device_media WHERE device_id IN ({$orphanMarks})", $orphanIds));
                if ($mediaIds !== []) R::exec('DELETE FROM device_media_analysis WHERE media_id IN (' . implode(',', array_fill(0, count($mediaIds), '?')) . ')', $mediaIds);
                R::exec("DELETE FROM device_media WHERE device_id IN ({$orphanMarks})", $orphanIds);
                R::exec("DELETE FROM device_attribute WHERE device_id IN ({$orphanMarks})", $orphanIds);
                R::exec("DELETE FROM device_finding WHERE device_id IN ({$orphanMarks})", $orphanIds);
                R::exec("DELETE FROM device WHERE id IN ({$orphanMarks})", $orphanIds);
            }
            R::commit();
        } catch (Throwable $exception) {
            R::rollback();
            throw $exception;
        }
        audit_log('importbestand_zurueckgesetzt', ['backup' => basename($backupPath), 'preview' => $preview]);
        return $preview + ['backup' => $backupPath];
    }

    private static function rebuildableInspectionWhere(): string
    {
        // Only real Prüfweb entries attached to a valid device survive. A
        // device number shorter than six characters is a historic import
        // placeholder (for example 001-23), never an inventory number.
        $validManualDevice = "EXISTS (SELECT 1 FROM device AS reset_device"
            . " WHERE reset_device.id = inspection.device_id"
            . " AND LENGTH(TRIM(COALESCE(reset_device.external_number, ''))) >= 6)";

        return "(source_type <> 'manual' OR NOT {$validManualDevice})";
    }

    /** Restores the verified SQLite backup if a subsequent rebuild step fails. */
    public static function restore(string $backupPath): void
    {
        $database = app_database_path();
        if ($database === '' || str_contains($database, ':') || !class_exists(SQLite3::class)) {
            throw new RuntimeException('Automatische Wiederherstellung ist nur für SQLite möglich.');
        }
        if (!is_file($backupPath) || !is_file($backupPath . '.sha256')) throw new InvalidArgumentException('Das Wiederherstellungsbackup fehlt.');
        $expected = trim((string) strtok((string) file_get_contents($backupPath . '.sha256'), ' '));
        if ($expected === '' || !hash_equals($expected, (string) hash_file('sha256', $backupPath))) throw new RuntimeException('Das Wiederherstellungsbackup ist beschädigt.');
        R::close();
        $source = new SQLite3($backupPath, SQLITE3_OPEN_READONLY);
        $destination = new SQLite3($database, SQLITE3_OPEN_READWRITE);
        try {
            if (!$source->backup($destination)) throw new RuntimeException('SQLite-Wiederherstellung fehlgeschlagen.');
        } finally {
            $destination->close();
            $source->close();
        }
    }
}
