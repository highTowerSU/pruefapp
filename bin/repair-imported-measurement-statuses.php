#!/usr/bin/env php
<?php

declare(strict_types=1);

use RedBeanPHP\R;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once dirname(__DIR__) . '/lib/lib.inc.php';
$apply = in_array('--apply', $argv, true);
$ids = [10486, 10501, 10540, 10541];
$before = [];
$plan = [];
foreach ($ids as $id) {
    $inspection = R::load('inspection', $id);
    if (!$inspection->id || $inspection->source_type !== 'manual' || $inspection->inspection_type_code !== 'electrical'
        || !in_array((string) $inspection->result_status, ['data_missing', 'passed'], true)) {
        throw new RuntimeException('Prüfung weicht vom geprüften Korrekturumfang ab: ' . $id);
    }
    $source = json_decode((string) $inspection->csv_row_json, true, 512, JSON_THROW_ON_ERROR);
    $class = InspectionEvaluationService::normalizeProtectionClass((string) ($source['Bezeichnung'] ?? ''));
    $date = DateTimeImmutable::createFromFormat('!d/m/Y', (string) ($source['Prüfdatum'] ?? ''));
    if (!in_array($class, ['I', 'II', 'III'], true) || !$date || $date->format('Y-m-d') !== $inspection->test_date
        || (int) ($source['Speicher Nr'] ?? 0) !== (int) $inspection->storage_slot
        || InspectionEvaluationService::normalizeStatus((string) ($source['Prüfergebnis'] ?? '')) !== 'passed') {
        throw new RuntimeException('Keine eindeutige passende Messquelle für Prüfung ' . $id);
    }
    $slots = json_decode((string) $inspection->storage_slots_json, true);
    if (is_array($slots) && count($slots) > 1) {
        throw new RuntimeException('Mehrere Speicherplätze benötigen eine getrennte Prüfung.');
    }
    $measurements = InspectionDataService::measurements($id);
    $answers = InspectionDataService::answers($id);
    $before[$id] = ['inspection' => $inspection->export(), 'measurements' => $measurements, 'answers' => $answers];
    $inspection->protection_class = $class;
    $evaluation = InspectionEvaluationService::evaluate($inspection->export(), $answers, $measurements, true);
    $plan[] = ['id' => $id, 'old_class' => $before[$id]['inspection']['protection_class'], 'source_class' => $class, 'old_status' => $before[$id]['inspection']['result_status'], 'evaluation' => $evaluation];
    if ($evaluation['status'] !== 'passed' || $evaluation['missing'] !== [] || $evaluation['failed'] !== []) {
        throw new RuntimeException('Keine vollständig bestandene quellenbasierte Neubewertung für Prüfung ' . $id);
    }
}
echo json_encode(['mode' => $apply ? 'apply' : 'preview', 'plan' => $plan], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
if (!$apply) {
    exit;
}
if (count(array_filter($plan, static fn(array $row): bool => $row['old_status'] === 'passed' && $row['old_class'] === $row['source_class'])) === count($plan)) {
    echo json_encode(['state' => 'already_applied']) . PHP_EOL;
    exit;
}
$directory = '/var/backups/ceneos';
if (!is_dir($directory)) {
    throw new RuntimeException('Geschütztes Betreiber-Sicherungsverzeichnis fehlt.');
}
$backup = $directory . '/pruefapp-before-source-measurement-repair-' . date('Ymd-His') . '.json';
$previousMask = umask(0077);
$file = fopen($backup, 'x');
umask($previousMask);
if ($file === false) {
    throw new RuntimeException('Sicherung konnte nicht erstellt werden.');
}
$contents = json_encode($before, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
if (fwrite($file, $contents) !== strlen($contents)) {
    fclose($file);
    throw new RuntimeException('Sicherung ist unvollständig.');
}
fclose($file);
R::begin();
try {
    foreach ($plan as $row) {
        $inspection = R::load('inspection', $row['id']);
        if ($inspection->updated_at !== $before[$row['id']]['inspection']['updated_at']) {
            throw new RuntimeException('Prüfung wurde zwischenzeitlich geändert.');
        }
        $inspection->protection_class = $row['source_class'];
        if ($row['old_class'] !== $row['source_class']) {
            $inspection->inspection_type = InspectionEvaluationService::canonicalInspectionType('', $row['source_class']);
        }
        $inspection->result_status = $row['evaluation']['status'];
        $inspection->status = 'completed';
        $inspection->result_reason_code = $row['evaluation']['reason_code'];
        $inspection->result_reason_text = $row['evaluation']['reason'];
        $inspection->updated_at = date(DATE_ATOM);
        R::store($inspection);
        foreach ($before[$row['id']]['measurements'] as $measurement) {
            if ($measurement['measurement_key'] !== 'IEA') continue;
            $evaluated = InspectionEvaluationService::evaluateMeasurement($inspection->export(), $measurement);
            R::exec('UPDATE inspection_measurement SET outcome=?,limit_value=?,limit_unit=?,rule_key=?,rule_version=?,updated_at=? WHERE id=? AND inspection_id=?', [$evaluated['outcome'], $evaluated['limit_value'], $evaluated['limit_unit'], $evaluated['rule_key'], '2', date(DATE_ATOM), $measurement['id'], $row['id']]);
        }
        audit_log('importierte_messung_neubewertet', ['inspection_id' => $row['id'], 'old_class' => $row['old_class'], 'source_class' => $row['source_class'], 'old_status' => $row['old_status'], 'new_status' => 'passed', 'reason' => 'BENNING-Quellklasse und vorhandenes Ersatzableitstromverfahren berücksichtigen; Messwerte und Prüffragen unverändert.', 'backup' => basename($backup)]);
    }
    R::commit();
} catch (Throwable $error) {
    R::rollback();
    throw $error;
}
echo json_encode(['state' => 'success', 'inspection_ids' => $ids, 'backup' => $backup], JSON_UNESCAPED_UNICODE) . PHP_EOL;
