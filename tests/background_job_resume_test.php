<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Ceneos\PhpBase\Jobs\JobQueue;
use RedBeanPHP\R;

function app_storage_namespace(): string { return 'job-test'; }
function url_for(string $path): string { return '/' . ltrim($path, '/'); }
function app_data_root(): string { global $testDataRoot; return $testDataRoot; }
final class InspectionController
{
    public static function normalizeImportedMeasurements(array $measurements, string $status): array { return $measurements; }
}

require dirname(__DIR__) . '/lib/BackgroundJobService.php';
require dirname(__DIR__) . '/lib/MaintenanceJobHandler.php';

$database = sys_get_temp_dir() . '/pruefapp-job-resume-' . bin2hex(random_bytes(5)) . '.sqlite';
$testDataRoot = sys_get_temp_dir() . '/pruefapp-job-data-' . bin2hex(random_bytes(5));
mkdir($testDataRoot, 0770, true);
R::setup('sqlite:' . $database);
R::freeze(false);
JobQueue::resetSchemaCache();
try {
    $job = BackgroundJobService::enqueue('pdf_regenerate', ['inspection_ids' => range(1, 100)], [
        'owner_user_id' => 4,
        'total' => 100,
    ]);
    $publicId = (string) $job['id'];
    $first = JobQueue::claim('pdf_regenerate', 'slice-one', 30);
    if ($first === null) throw new RuntimeException('Aufgabe wurde nicht übernommen.');
    JobQueue::checkpoint((int) $first['id'], ['next_index' => 29], 29, 100, '29 von 100', 'slice-one', 30);
    JobQueue::release((int) $first['id'], 'slice-one', 'Fortsetzung folgt');

    $second = JobQueue::claim('pdf_regenerate', 'slice-two', 30);
    if ($second === null || $second['current'] !== 29 || ($second['checkpoint']['next_index'] ?? null) !== 29) {
        throw new RuntimeException('Der zweite Lauf begann nicht am gespeicherten Stand 29.');
    }
    JobQueue::checkpoint((int) $second['id'], ['next_index' => 30], 30, 100, '30 von 100', 'slice-two', 30);
    JobQueue::release((int) $second['id'], 'slice-two');
    $visible = BackgroundJobService::find($publicId);
    if ($visible === null || (int) $visible['step'] !== 30) {
        throw new RuntimeException('Der sichtbare Fortschritt ist nicht monoton auf 30 gestiegen.');
    }

    $inspectionIds = [];
    for ($number = 1; $number <= 31; $number++) {
        $inspection = R::dispense('inspection');
        $inspection->external_number = 'TEST-' . $number;
        $inspection->source_type = 'csv';
        $inspection->measurements_json = '[]';
        $inspection->result_status = 'bestanden';
        $inspectionIds[] = (int) R::store($inspection);
    }
    $maintenanceId = JobQueue::enqueue('measurement_migration', ['type' => 'measurement_migration'], 'job-test', [
        'current' => 29,
        'total' => 31,
        'checkpoint' => ['last_id' => $inspectionIds[28]],
    ]);
    $maintenance = JobQueue::claim('measurement_migration', 'maintenance-one', 30);
    if ($maintenance === null) throw new RuntimeException('Wartungsaufgabe wurde nicht übernommen.');
    try {
        MaintenanceJobHandler::run($maintenance, static function (array $checkpoint, int $current, int $total, string $number, string $message) use ($maintenanceId): void {
            JobQueue::checkpoint($maintenanceId, $checkpoint, $current, $total, $message, 'maintenance-one', 30);
            throw new RuntimeException('simulierter Zeitbudget-Abbruch');
        });
    } catch (RuntimeException $exception) {
        if ($exception->getMessage() !== 'simulierter Zeitbudget-Abbruch') throw $exception;
    }
    JobQueue::release($maintenanceId, 'maintenance-one', 'Fortsetzung');
    $resumedMaintenance = JobQueue::claim('measurement_migration', 'maintenance-two', 30);
    if ($resumedMaintenance === null || (int) $resumedMaintenance['current'] !== 30 || (int) ($resumedMaintenance['checkpoint']['last_id'] ?? 0) !== $inspectionIds[29]) {
        throw new RuntimeException('Der Wartungs-Handler hat seinen Datensatzcursor nicht gespeichert.');
    }
    $firstAfterResume = '';
    try {
        MaintenanceJobHandler::run($resumedMaintenance, static function (array $checkpoint, int $current, int $total, string $number, string $message) use (&$firstAfterResume): void {
            $firstAfterResume = $number;
            throw new RuntimeException('Testende');
        });
    } catch (RuntimeException $exception) {
        if ($exception->getMessage() !== 'Testende') throw $exception;
    }
    if ($firstAfterResume !== 'TEST-31') throw new RuntimeException('Der Wartungs-Handler begann nach dem Neustart wieder von vorne.');
    echo "PASS: Hintergrundaufgabe setzt nach dem Zeitabschnitt bei 29 fort\n";
} finally {
    R::close();
    @unlink($database);
    @rmdir($testDataRoot);
}
