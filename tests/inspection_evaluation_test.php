<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/InspectionEvaluationService.php';

$base = [
    'protection_class' => 'I',
    'examiner' => 'pruefer@example.test',
    'test_date' => '2026-08-05',
    'warming_device_snapshot' => 0,
    'cable_length_m' => 12.6,
];
$answers = [];
foreach (['identification', 'visual_label', 'visual_cable', 'visual_housing', 'function', 'safe_operation', 'customer_notice'] as $position => $key) {
    $answers[] = ['item_key' => $key, 'question_snapshot' => $key, 'outcome' => 'passed', 'required' => 1, 'sort_order' => $position];
}
$measurements = [
    ['measurement_key' => 'RPE', 'numeric_value' => 0.39, 'unit' => 'Ω'],
    ['measurement_key' => 'RISO', 'numeric_value' => 20, 'unit' => 'MΩ'],
    ['measurement_key' => 'IPE', 'numeric_value' => 0.2, 'unit' => 'mA'],
];

$passed = InspectionEvaluationService::evaluate($base, $answers, $measurements, true);
if ($passed['status'] !== InspectionEvaluationService::PASSED) throw new RuntimeException('Gültige SK-I-Prüfung wurde nicht bestanden.');
if (InspectionEvaluationService::rslLimit(12.6) !== 0.5) throw new RuntimeException('RSL-Grenzwert wurde nicht serverseitig aus der Kabellänge berechnet.');
if (InspectionEvaluationService::requiredMeasurementKeys('Klasse II') !== ['RISO', 'IBER']) {
    throw new RuntimeException('Importierte Schutzklassenbezeichnung wurde nicht zentral normalisiert.');
}

$failedMeasurements = $measurements;
$failedMeasurements[0]['numeric_value'] = 0.51;
$failed = InspectionEvaluationService::evaluate($base, $answers, $failedMeasurements, true);
if ($failed['status'] !== InspectionEvaluationService::FAILED) throw new RuntimeException('Grenzwertverletzung wurde nicht als nicht bestanden bewertet.');

$missing = InspectionEvaluationService::evaluate($base, $answers, array_slice($measurements, 0, 2), true);
if ($missing['status'] !== InspectionEvaluationService::DATA_MISSING || !str_contains(implode(' ', $missing['missing']), 'IPE')) {
    throw new RuntimeException('Fehlender Pflichtmesswert wurde nicht eindeutig ausgewiesen.');
}

$missingQuestions = InspectionEvaluationService::evaluate($base, [], $measurements, true);
if ($missingQuestions['status'] !== InspectionEvaluationService::DATA_MISSING
    || !in_array('Erforderliche Prüffragen', $missingQuestions['missing'], true)
) {
    throw new RuntimeException('Eine Prüfung ohne Pflichtfragen darf nicht abgeschlossen werden.');
}

$editing = InspectionEvaluationService::evaluate($base, [], [], false);
if ($editing['status'] !== InspectionEvaluationService::IN_PROGRESS) throw new RuntimeException('Zwischenspeicherung muss in Bearbeitung bleiben.');

$heating = InspectionEvaluationService::evaluateMeasurement(array_replace($base, ['warming_device_snapshot' => 1]), ['measurement_key' => 'RISO', 'numeric_value' => 0.4]);
if ($heating['outcome'] !== 'passed' || $heating['limit_value'] !== 0.3) throw new RuntimeException('Wärmegerät-Regel wurde nicht zentral angewendet.');

if (!InspectionEvaluationService::reportPathAllowed('passed', 'legacy', '/var/www/berichte/alt.pdf')) {
    throw new RuntimeException('Legacy-Originalberichte wurden unzulässig gesperrt.');
}
if (InspectionEvaluationService::reportPathAllowed('passed', 'migrated_import', '/var/www/berichte/import.pdf')) {
    throw new RuntimeException('Importierte Originalberichte dürfen nicht als neuer Standardbericht erscheinen.');
}
if (!InspectionEvaluationService::reportPathAllowed('failed', 'migrated_import', 'reports/current/42.pdf')) {
    throw new RuntimeException('Neuer Bericht einer abgeschlossenen Importprüfung wurde unzulässig gesperrt.');
}
if (InspectionEvaluationService::reportPathAllowed('in_progress', 'native', 'reports/current/43.pdf')) {
    throw new RuntimeException('Unfertige Prüfung darf keinen freigegebenen Bericht besitzen.');
}

echo "PASS: kanonische Prüfstatus und serverseitige Messregeln\n";
