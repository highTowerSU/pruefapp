#!/usr/bin/env php
<?php

declare(strict_types=1);

use RedBeanPHP\R;

require dirname(__DIR__) . '/lib/lib.inc.php';
require_once dirname(__DIR__) . '/lib/ImportedMeasurementRecoveryService.php';

$options = getopt('', ['plan:', 'apply:']);
if (isset($options['plan']) === isset($options['apply'])) {
    fwrite(STDERR, "Aufruf: --plan=/var/backups/ceneos/plan.jsonl oder --apply=/var/backups/ceneos/plan.jsonl\n");
    exit(2);
}
$path = (string) ($options['plan'] ?? $options['apply']);
if (!str_starts_with($path, '/var/backups/ceneos/') || str_contains($path, '..')) throw new RuntimeException('Privates Sicherungsverzeichnis erforderlich.');
umask(0077);
R::freeze(true);
if (isset($options['plan'])) {
    $file = fopen($path, 'xb');
    if ($file === false) throw new RuntimeException('Plan konnte nicht neu angelegt werden.');
    $ids = R::getCol("SELECT id FROM inspection WHERE source_type IN ('reconciled','csv','json','phoenix') AND COALESCE(archived_at,'')='' ORDER BY id");
    $stats = ['inspections' => count($ids), 'ready' => 0, 'measurements' => 0, 'skipped' => [], 'years' => []];
    foreach ($ids as $id) {
        // Read one raw source at a time: historical ODS snapshots can be large.
        $inspection = R::getRow('SELECT id,test_date,storage_slot,raw_json,measurements_json FROM inspection WHERE id=?', [(int) $id]);
        if (R::count('inspection_measurement', 'inspection_id=?', [(int) $id]) > 0
            || !in_array(trim((string) ($inspection['measurements_json'] ?? '')), ['', '[]', '{}', 'null'], true)) {
            $plan = ['status' => 'already_present'];
        } else {
            $plan = ImportedMeasurementRecoveryService::plan($inspection);
        }
        if ($plan['status'] === 'ready') {
            $stats['ready']++;
            $stats['measurements'] += count($plan['measurements']);
            $year = substr((string) $inspection['test_date'], 0, 4);
            $stats['years'][$year] = ($stats['years'][$year] ?? 0) + 1;
        } else {
            $stats['skipped'][$plan['status']] = ($stats['skipped'][$plan['status']] ?? 0) + 1;
        }
        if (fwrite($file, json_encode(['id' => (int) $id] + $plan, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n") === false) throw new RuntimeException('Plan konnte nicht geschrieben werden.');
    }
    fclose($file);
    echo json_encode(['plan' => $path] + $stats, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), "\n";
    exit;
}
$file = fopen($path, 'rb');
if ($file === false) throw new RuntimeException('Plan fehlt.');
$directory = '/var/backups/ceneos/pruefapp-measurement-recovery-' . gmdate('Ymd-His');
if (!mkdir($directory, 0700) || !mkdir($directory . '/reports', 0700)) throw new RuntimeException('Sicherungsverzeichnis konnte nicht angelegt werden.');
$backupFile = fopen($directory . '/before.jsonl', 'xb');
if ($backupFile === false) throw new RuntimeException('Sicherung konnte nicht angelegt werden.');
$backup = static function (array $before, array $assets) use ($directory, $backupFile): void {
    $fields = array_intersect_key($before, array_flip(['id','external_number','measurements_json','report_path','updated_at','result_status','status','test_date','device_id','source_type']));
    $fields['source_sha256'] = hash('sha256', (string) $before['raw_json']);
    $fields['generated_assets'] = $assets;
    $path = (string) ($before['report_path'] ?? '');
    if (str_starts_with($path, 'reports/current/')) {
        $source = app_data_root() . '/' . $path;
        if (is_file($source) && !copy($source, $directory . '/reports/' . (int) $before['id'] . '.pdf')) throw new RuntimeException('Alter Bericht konnte nicht gesichert werden.');
    }
    if (fwrite($backupFile, json_encode($fields, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n") === false || !fflush($backupFile)) throw new RuntimeException('Sicherung konnte nicht geschrieben werden.');
};
$stats = ['restored' => 0, 'measurements' => 0, 'already_present' => 0];
while (($line = fgets($file)) !== false) {
    $plan = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
    if (($plan['status'] ?? '') !== 'ready') continue;
    $result = ImportedMeasurementRecoveryService::apply((int) $plan['id'], $plan, $backup);
    if ($result['status'] === 'already_present') {
        $stats['already_present']++;
        continue;
    }
    $stats['restored']++;
    $stats['measurements'] += $result['measurements'];
    audit_log('import_messwerte_wiederhergestellt', $result + ['backup' => $directory, 'plan' => basename($path)]);
}
fclose($file);
fclose($backupFile);
echo json_encode(['backup' => $directory] + $stats, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), "\n";
