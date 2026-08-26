<?php

declare(strict_types=1);

use Ceneos\PhpBase\Audit\AuditLogger;
use Ceneos\PhpBase\Jobs\JobQueue;
use Ceneos\PhpBase\Notification\NotificationRepository;
use RedBeanPHP\R;

/** PrüfApp facade around the shared, application-neutral base queue. */
final class BackgroundJobService
{
    private const TYPE_LABELS = [
        'pdf_zip' => 'PDF-ZIP-Export',
        'pdf_bundle' => 'Sammel-PDF',
        'pdf_regenerate' => 'Neue Prüfberichte',
        'examiner_migration' => 'Prüferzuordnung',
        'directory_import' => 'Datenimport',
        'import_rebuild_reset' => 'Importbestand sichern und zurücksetzen',
        'phoenix_sync' => 'Phoenix-Import',
        'phoenix_report_sync' => 'Phoenix-Originalberichte synchronisieren',
        'csv_ods_source_reconciliation' => 'CSV/ODS-Quellen zusammenführen',
        'missing_reports' => 'Fehlende Prüfberichte',
        'phoenix_pdf_restore' => 'Original-PDFs wiederherstellen',
        'report_migration' => 'PDF-Aufbereitung',
        'all_report_regeneration' => 'Alle Prüfberichte neu erzeugen',
        'measurement_migration' => 'Messdaten-Aufbereitung',
        'pending_measurement_import' => 'Messdaten importieren',
        'inspection_data_migration' => 'Prüfungsdaten migrieren',
        'imported_room_assignment' => 'Importierte Räume zuordnen',
        'legacy_classification_migration' => 'Legacy-Prüfungen klassifizieren',
        'import_result_reconciliation' => 'Import-Ergebnisse abgleichen',
        'csv_source_fact_reconciliation' => 'CSV-Quellwerte abgleichen',
        'inspection_duplicate_audit' => 'Prüfungsdubletten prüfen',
        'inspection_duplicate_review_cleanup' => 'Veraltete Dublettenhinweise schließen',
        'inspection_confirmed_draft_archive' => 'Bestätigten Prüfentwurf archivieren',
        'inspection_confirmed_archive' => 'Bestätigte Prüfungsdubletten archivieren',
        'inspection_confirmed_legacy_csv_archive' => 'Bestätigte Legacy-CSV-Dubletten archivieren',
        'inspection_confirmed_same_source_archive' => 'Bestätigte gleichquellige Importdubletten archivieren',
        'inspection_confirmed_historical_device_repair' => 'Historische Gerätezuordnungen bereinigen',
        'inspection_confirmed_historical_device_split' => 'Historische Importgeräte trennen',
        'inspection_confirmed_csv_manual_merge' => 'Bestätigte CSV- und manuelle Prüfung zusammenführen',
        'inspection_confirmed_number_restore' => 'Bestätigte Prüfnummer wiederherstellen',
        'inspection_duplicate_archive' => 'Importdubletten archivieren',
        'inspection_json_csv_mirror_archive' => 'JSON/CSV-Spiegelungen archivieren',
        'inspection_csv_source_duplicate_archive' => 'Gleiche CSV-Quellzeilen archivieren',
        'inspection_manual_csv_consolidation' => 'Manuelle Entwürfe mit CSV-Prüfungen zusammenführen',
        'inspection_pdf_zip' => 'Ausgewählte Prüfberichte',
        'vocabulary_suggestion' => 'KI-Stammdatenprüfung',
        'vocabulary_review_scan' => 'Stammdaten mit KI prüfen',
        'vocabulary_normalization' => 'Stammdaten vereinheitlichen',
    ];

    /** @param array<string,mixed> $payload @param array<string,mixed> $options */
    public static function enqueue(string $type, array $payload, array $options = []): array
    {
        $owner = max(0, (int) ($options['owner_user_id'] ?? $payload['owner_user_id'] ?? 0));
        $payload['owner_user_id'] = $owner;
        $options += [
            'owner_user_id' => $owner,
            'priority' => self::priority($type),
            'message' => self::label($type) . ' wartet auf den nächsten Hintergrundlauf.',
        ];
        $id = JobQueue::enqueue($type, $payload, app_storage_namespace(), $options);
        return self::findById($id) ?? [];
    }

    public static function find(string $publicId): ?array
    {
        $job = JobQueue::getByPublicId($publicId);
        return $job !== null ? self::present($job) : null;
    }

    public static function findById(int $id): ?array
    {
        $job = JobQueue::get($id);
        return $job !== null ? self::present($job) : null;
    }

    /** @return list<array<string,mixed>> */
    public static function pending(int $limit = 100): array
    {
        return array_map([self::class, 'present'], JobQueue::pending($limit));
    }

    /** @return list<array<string,mixed>> */
    public static function latest(int $limit = 100, ?int $ownerUserId = null): array
    {
        return array_map([self::class, 'present'], JobQueue::latest($limit, $ownerUserId));
    }

    public static function requestCancellation(string $publicId): bool
    {
        $job = JobQueue::getByPublicId($publicId);
        return $job !== null && JobQueue::requestCancellation((int) $job['id']);
    }

    /**
     * Replaces obsolete system maintenance work with its successor.
     *
     * This is intentionally narrower than a general force-cancel: it is used
     * only when the scheduler has a newer complete run of the same maintenance
     * type to enqueue.  Older report jobs used to be non-cancellable, so make
     * them cooperative first; the worker then stops at its next checkpoint.
     */
    public static function supersedePendingType(string $type, string $message): int
    {
        $cancelled = 0;
        foreach (self::pending(1000) as $job) {
            if ((string) ($job['type'] ?? '') !== $type) continue;
            if (!in_array((string) ($job['state'] ?? ''), ['queued', 'running', 'cancel_requested'], true)) continue;
            $id = (int) ($job['database_id'] ?? 0);
            if ($id <= 0) continue;
            // A previous release marked maintenance report runs as system
            // jobs.  Superseding them is safe because a new full run follows.
            R::exec('UPDATE backgroundjob SET cancellable = 1, message = ?, updated_at = ? WHERE id = ?', [$message, date(DATE_ATOM), $id]);
            if (JobQueue::requestCancellation($id)) $cancelled++;
        }
        return $cancelled;
    }

    /** @param array<string,mixed> $result */
    public static function complete(int $jobId, array $result = [], string $message = ''): void
    {
        $job = JobQueue::get($jobId);
        if ($job === null) return;
        JobQueue::finish($jobId, 'done', $result, $message !== '' ? $message : self::label($job['type']) . ' abgeschlossen.');
        $owner = (int) $job['owner_user_id'];
        $recipients = $owner > 0 ? [$owner] : (in_array($job['type'], ['directory_import', 'phoenix_sync', 'phoenix_report_sync', 'missing_reports', 'phoenix_pdf_restore', 'report_migration', 'all_report_regeneration', 'measurement_migration', 'inspection_data_migration', 'imported_room_assignment', 'legacy_classification_migration', 'import_result_reconciliation', 'csv_ods_source_reconciliation', 'inspection_duplicate_audit', 'inspection_duplicate_review_cleanup', 'inspection_confirmed_draft_archive', 'inspection_confirmed_archive', 'inspection_confirmed_legacy_csv_archive', 'inspection_confirmed_same_source_archive', 'inspection_confirmed_historical_device_repair', 'inspection_confirmed_historical_device_split', 'inspection_confirmed_csv_manual_merge', 'inspection_confirmed_number_restore', 'inspection_duplicate_archive', 'inspection_json_csv_mirror_archive', 'inspection_manual_csv_consolidation'], true) ? self::adminUserIds() : []);
        if ($recipients !== []) {
            NotificationRepository::publish($recipients, self::label($job['type']) . ' abgeschlossen', $message ?: 'Die Aufgabe wurde erfolgreich abgeschlossen.', [
                'category' => str_contains($job['type'], 'import') || in_array($job['type'], ['phoenix_sync', 'phoenix_report_sync'], true) ? 'import' : 'background_job',
                'severity' => 'success',
                'action_url' => self::resultActionUrl($job['type'], $job['public_id']),
                'job_id' => $jobId,
                'dedupe_key' => 'job:' . $job['public_id'] . ':done',
            ]);
        }
        self::auditTransition($job, 'hintergrundaufgabe_abgeschlossen', $result);
    }

    public static function fail(int $jobId, string $error): void
    {
        $job = JobQueue::get($jobId);
        if ($job === null) return;
        JobQueue::finish($jobId, 'failed', ['error' => $error], $error);
        $recipients = array_values(array_unique(array_filter([(int) $job['owner_user_id'], ...self::adminUserIds()])));
        NotificationRepository::publish($recipients, self::label($job['type']) . ' fehlgeschlagen', $error, [
            'category' => 'background_job',
            'severity' => 'error',
            'action_url' => url_for('admin/audit-log'),
            'job_id' => $jobId,
            'dedupe_key' => 'job:' . $job['public_id'] . ':failed',
        ]);
        self::auditTransition($job, 'hintergrundaufgabe_fehlgeschlagen', ['error' => $error]);
    }

    public static function markRead(string $publicId, int $userId): bool
    {
        $job = JobQueue::getByPublicId($publicId);
        return $job !== null && NotificationRepository::markJobRead((int) $job['id'], $userId) >= 0;
    }

    /** Keeps one current notification for a one-time maintenance migration. */
    public static function deduplicateImportReconciliationNotifications(): int
    {
        $title = self::label('import_result_reconciliation') . ' abgeschlossen';
        $ids = array_map('intval', R::getCol(
            'SELECT id FROM notification WHERE category = ? AND title = ? ORDER BY id DESC',
            ['import', $title]
        ));
        return NotificationRepository::deleteMany(array_slice($ids, 1));
    }

    /** Import old /tmp status files once; safe to call on every cron start. */
    public static function importLegacyJobs(): int
    {
        $root = sys_get_temp_dir() . '/pruefapp-phoenix-jobs';
        if (!is_dir($root)) return 0;
        $imported = 0;
        foreach (glob($root . '/*.status.json') ?: [] as $statusPath) {
            $publicId = basename($statusPath, '.status.json');
            if (!preg_match('/^[a-f0-9]{24}$/', $publicId) || JobQueue::getByPublicId($publicId) !== null) continue;
            $status = json_decode((string) @file_get_contents($statusPath), true);
            $payloadPath = $root . '/' . $publicId . '.json';
            $payload = is_file($payloadPath) ? json_decode((string) @file_get_contents($payloadPath), true) : [];
            if (!is_array($status)) continue;
            if (!is_array($payload)) $payload = [];
            $type = (string) ($status['type'] ?? $payload['type'] ?? 'background');
            $legacyStep = in_array($type, ['pdf_regenerate', 'examiner_migration', 'pdf_zip'], true) ? (int) ($status['step'] ?? 0) : 0;
            $jobId = JobQueue::enqueue($type, $payload, app_storage_namespace(), [
                'public_id' => $publicId,
                'owner_user_id' => (int) ($status['owner_user_id'] ?? $payload['owner_user_id'] ?? 0),
                'priority' => self::priority($type),
                'cancellable' => !in_array($type, ['examiner_migration', 'report_migration', 'phoenix_pdf_restore'], true),
                'current' => $legacyStep,
                'total' => (int) ($status['total'] ?? 0),
                'checkpoint' => ['next_index' => $legacyStep, 'legacy_status_path' => $statusPath],
                'message' => (string) ($status['message'] ?? ''),
                'dedupe_key' => 'legacy:' . $publicId,
            ]);
            $state = (string) ($status['state'] ?? 'queued');
            if (in_array($state, ['done', 'error', 'cancelled', 'cancel_requested'], true)) {
                $terminalState = $state === 'error' ? 'failed' : ($state === 'cancel_requested' ? 'cancelled' : $state);
                $terminalMessage = $state === 'cancel_requested' ? 'Die vorgemerkte Aufgabe wurde beim Wechsel auf die gemeinsame Queue abgebrochen.' : (string) ($status['message'] ?? $status['error'] ?? '');
                JobQueue::finish($jobId, $terminalState, ['legacy_status' => $status, 'output' => (string) ($status['output'] ?? '')], $terminalMessage);
            }
            $imported++;
        }
        return $imported;
    }

    /** Import the former JSON-only import history into the searchable audit trail once. */
    public static function importLegacyImportLogs(): int
    {
        $root = app_data_root() . '/import-logs';
        if (!is_dir($root)) return 0;
        $marker = app_data_root() . '/migration/import-log-audit-v1.json';
        $state = is_file($marker) ? json_decode((string) @file_get_contents($marker), true) : [];
        $processed = is_array($state['processed'] ?? null) ? $state['processed'] : [];
        $imported = 0;
        $logger = new AuditLogger();
        foreach (glob($root . '/*.json') ?: [] as $path) {
            $key = basename($path) . ':' . (string) (@filesize($path) ?: 0);
            if (isset($processed[$key])) continue;
            $decoded = json_decode((string) @file_get_contents($path), true);
            if (!is_array($decoded)) { $processed[$key] = date(DATE_ATOM); continue; }
            $stats = is_array($decoded['stats'] ?? null) ? $decoded['stats'] : $decoded;
            $correlationId = 'legacy-import-' . substr(hash('sha256', $key), 0, 20);
            $events = [];
            foreach ((array) ($stats['new_devices'] ?? []) as $row) $events[] = ['action' => 'import_datensatz_importiert', 'context' => ['_status' => 'importiert', 'legacy_summary' => true, 'device' => $row]];
            foreach (array_merge((array) ($stats['updated_devices'] ?? []), (array) ($stats['updated_inspections'] ?? [])) as $row) $events[] = ['action' => 'import_datensatz_aktualisiert', 'context' => ['_status' => 'aktualisiert', 'legacy_summary' => true, 'record' => $row]];
            foreach ((array) ($stats['not_imported'] ?? []) as $row) $events[] = ['action' => 'import_datensatz_uebersprungen', 'context' => ['_status' => 'übersprungen', 'legacy_summary' => true, 'record' => $row]];
            $events[] = ['action' => 'import_abgeschlossen', 'context' => ['_status' => 'abgeschlossen', 'legacy_summary' => true, 'source_log' => basename($path), 'created_at' => (string) ($decoded['created_at'] ?? ''), 'stats' => $stats]];
            $logger->logBatch($events, $correlationId, 'import');
            $processed[$key] = date(DATE_ATOM);
            $imported++;
        }
        if ($imported > 0 || !is_file($marker)) {
            if (!is_dir(dirname($marker))) mkdir(dirname($marker), 0770, true);
            file_put_contents($marker, json_encode(['version' => 1, 'processed' => $processed, 'updated_at' => date(DATE_ATOM)], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
        }
        return $imported;
    }

    public static function label(string $type): string
    {
        return self::TYPE_LABELS[$type] ?? 'Hintergrundaufgabe';
    }

    /** @param array<string,mixed> $job @return array<string,mixed> */
    private static function present(array $job): array
    {
        $result = (array) ($job['result'] ?? []);
        $legacy = (array) ($result['legacy_status'] ?? []);
        $state = $job['status'] === 'failed' ? 'error' : $job['status'];
        return array_merge($job, [
            'database_id' => (int) $job['id'],
            'id' => (string) $job['public_id'],
            'state' => $state,
            'step' => (int) $job['current'],
            'stats' => (array) ($result['stats'] ?? $legacy['stats'] ?? $result),
            'output' => (string) ($result['output'] ?? $legacy['output'] ?? ''),
            'notification_unread' => false,
            'label' => self::label((string) $job['type']),
        ]);
    }

    private static function priority(string $type): int
    {
        return match ($type) {
            'directory_import', 'import_rebuild_reset', 'phoenix_sync', 'phoenix_report_sync', 'csv_ods_source_reconciliation' => 40,
            'legacy_classification_migration' => 50,
            'import_result_reconciliation' => 45,
            'csv_source_fact_reconciliation' => 46,
            'inspection_duplicate_audit' => 35,
            'inspection_duplicate_review_cleanup' => 35,
            'inspection_confirmed_draft_archive' => 37,
            'inspection_confirmed_archive' => 37,
            'inspection_confirmed_legacy_csv_archive' => 37,
            'inspection_confirmed_same_source_archive' => 37,
            'inspection_confirmed_historical_device_repair' => 37,
            'inspection_confirmed_historical_device_split' => 37,
            'inspection_confirmed_csv_manual_merge' => 37,
            'inspection_confirmed_number_restore' => 37,
            'inspection_duplicate_archive' => 36,
            'inspection_json_csv_mirror_archive' => 36,
            'inspection_csv_source_duplicate_archive' => 36,
            'inspection_manual_csv_consolidation' => 37,
            'inspection_data_migration' => 40,
            'examiner_migration', 'phoenix_pdf_restore', 'report_migration', 'all_report_regeneration', 'measurement_migration' => 30,
            'pdf_regenerate', 'missing_reports' => 20,
            'pdf_bundle' => 0,
            'pdf_zip', 'inspection_pdf_zip' => -10,
            default => 10,
        };
    }

    /** @return list<int> */
    private static function adminUserIds(): array
    {
        try {
            return array_map('intval', R::getCol("SELECT id FROM oauthuser WHERE role IN ('admin', 'superadmin')"));
        } catch (Throwable) {
            return [];
        }
    }

    private static function resultActionUrl(string $type, string $publicId): string
    {
        return in_array($type, ['pdf_zip', 'pdf_bundle', 'inspection_pdf_zip'], true)
            ? url_for('geraete/zip/' . $publicId . '/download')
            : (in_array($type, ['directory_import', 'import_rebuild_reset', 'phoenix_sync', 'phoenix_report_sync', 'pending_measurement_import'], true) ? url_for('admin/pruefungen/import') : url_for('downloads'));
    }

    /** @param array<string,mixed> $job @param array<string,mixed> $details */
    private static function auditTransition(array $job, string $action, array $details): void
    {
        (new AuditLogger())->log($action, [
            '_category' => str_contains((string) $job['type'], 'import') || in_array((string) $job['type'], ['phoenix_sync', 'phoenix_report_sync'], true) ? 'import' : 'background_job',
            '_correlation_id' => 'job-' . $job['public_id'],
            '_entity_type' => 'background_job',
            '_entity_id' => $job['public_id'],
            '_status' => $action === 'hintergrundaufgabe_abgeschlossen' ? 'abgeschlossen' : 'fehlgeschlagen',
            'job_id' => $job['public_id'],
            'job_type' => $job['type'],
            'owner_user_id' => $job['owner_user_id'],
            'details' => $details,
        ]);
    }
}
