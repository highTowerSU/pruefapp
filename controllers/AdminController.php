<?php

declare(strict_types=1);

use RedBeanPHP\R as R;
use Ceneos\PhpBase\Audit\AuditTrailRepository;
use Ceneos\PhpBase\Audit\RevisionHistory;
use Ceneos\PhpBase\Database\RevisionSupport;
use Ceneos\PhpBase\Tenant\TenantRepository;

class AdminController
{
    /**
     * Narrow, token-protected diagnostic endpoint for operational support.
     * The secret is deliberately accepted only through an HTTP header so it
     * cannot leak through browser history, URLs, referrers or audit logs.
     */
    public static function inspectionApiDebug(array $params, bool $isHx): array
    {
        $configuredSecret = trim((string) (get_app_config('api_debug_secret', '') ?: getenv('PRUEFAPP_API_DEBUG_SECRET')));
        $providedSecret = trim((string) ($_SERVER['HTTP_X_API_DEBUG_SECRET'] ?? ''));
        $headers = ['Content-Type' => 'application/json; charset=utf-8', 'Cache-Control' => 'no-store'];
        if ($configuredSecret === '' || $providedSecret === '' || !hash_equals($configuredSecret, $providedSecret)) {
            return [403, $headers, json_encode(['ok' => false, 'error' => 'Nicht autorisiert.'], JSON_UNESCAPED_UNICODE)];
        }

        $query = trim((string) ($_GET['q'] ?? ''));
        $directory = trim((string) ($_GET['directory'] ?? ''));
        $summary = trim((string) ($_GET['summary'] ?? ''));
        if ($directory !== '') {
            return self::importDirectoryApiDebug($directory, $headers);
        }
        if ($summary === 'inspection-overview') {
            return self::inspectionOverviewApiDebug($headers);
        }
        if ($summary === 'inspection-duplicates') {
            return self::inspectionDuplicatesApiDebug($headers);
        }
        if ($summary === 'import-config') {
            return self::importConfigApiDebug($headers);
        }
        if ($summary === 'ai') {
            return self::aiProviderApiDebug($headers);
        }
        if ($summary === 'user-permissions') {
            return self::userPermissionsApiDebug($headers);
        }
        if ($summary === 'billing') {
            $filters = [];
            foreach (['q', 'eligibility', 'billing_status', 'customer_link', 'customer_id', 'site_id', 'building_id', 'floor_id', 'room_id', 'from', 'to', 'examiner', 'due_status', 'sort'] as $key) {
                if (isset($_GET[$key]) && !is_array($_GET[$key])) $filters[$key] = mb_substr(trim((string) $_GET[$key]), 0, 160);
            }
            $ids = array_filter(array_map('intval', explode(',', (string) ($_GET['ids'] ?? ''))));
            $invoiceId = max(0, (int) ($_GET['invoice_id'] ?? 0));
            $result = $invoiceId > 0
                ? BillingController::debugInvoice($invoiceId)
                : (($_GET['export_failures'] ?? '') === '1'
                ? BillingController::debugExportFailures()
                : (($_GET['render'] ?? '') === '1'
                    ? BillingController::debugRender($filters)
                    : BillingController::debugSelection($filters, $ids)));
            return [200, $headers, json_encode(['summary' => 'billing'] + $result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE)];
        }
        $failureId = strtoupper(trim((string) ($_GET['failure_id'] ?? '')));
        if ($failureId !== '') {
            if (!preg_match('/^[A-F0-9]{8}$/', $failureId)) return [400, $headers, json_encode(['ok' => false, 'error' => 'Ungültige Vorgangs-ID.'], JSON_UNESCAPED_UNICODE)];
            $failure = ApplicationFailureService::find($failureId);
            return [200, $headers, json_encode(['ok' => $failure !== null, 'failure' => $failure], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE)];
        }
        if ($query === '' || mb_strlen($query) > 120) {
            return [400, $headers, json_encode(['ok' => false, 'error' => 'Parameter q (Geräte- oder Prüfnummer) oder directory (freigegebener Importpfad) fehlt.'], JSON_UNESCAPED_UNICODE)];
        }
        $like = '%' . mb_strtolower($query) . '%';
        $rows = R::getAll(
            'SELECT i.id, i.device_id, i.external_number, i.test_date, i.next_due_date, i.room_snapshot, i.result_status, i.status, i.classification, i.source_type, i.source_file, i.archived_at, i.archived_reason, i.duplicate_of_inspection_id, '
            . 'i.result_reason_code, i.result_reason_text, d.external_number AS device_number, d.name AS device_name, r.number AS room_number '
            . 'FROM inspection i LEFT JOIN device d ON d.id = i.device_id LEFT JOIN room r ON r.id=d.room_id '
            . 'WHERE LOWER(COALESCE(i.external_number, \'\')) LIKE ? OR LOWER(COALESCE(d.external_number, \'\')) LIKE ? '
            . 'ORDER BY i.test_date DESC, i.id DESC LIMIT 100',
            [$like, $like]
        );
        foreach ($rows as &$row) {
            $row['computed_status'] = (string) R::getCell(
                'SELECT ' . InspectionEvaluationService::sqlStatusExpression('i') . ' FROM inspection i WHERE i.id = ?',
                [(int) $row['id']]
            );
            $row['expected_legacy'] = trim((string) ($row['test_date'] ?? '')) !== ''
                && (string) $row['test_date'] < '2025-01-01';
            $snapshotRow = R::getRow('SELECT source_row_json, legacy_row_json FROM inspection_source_snapshot WHERE inspection_id = ?', [(int) $row['id']]);
            $snapshot = json_decode((string) ($snapshotRow['legacy_row_json'] ?? ''), true);
            $row['source_snapshot_status'] = is_array($snapshot)
                ? InspectionEvaluationService::normalizeStatus((string) ($snapshot['result_status'] ?? ''), (string) ($snapshot['status'] ?? ''))
                : '';
            if (($_GET['source_row'] ?? '') === '1') {
                $sourceRow = json_decode((string) ($snapshotRow['source_row_json'] ?? ''), true);
                $row['source_row'] = is_array($sourceRow) ? $sourceRow : null;
            }
        }
        unset($row);
        $unclassified = (int) R::getCell("SELECT COUNT(*) FROM inspection WHERE COALESCE(test_date, '') <> '' AND test_date < '2025-01-01' AND COALESCE(classification, '') <> 'legacy'");
        $importsToReconcile = (int) R::getCell("SELECT COUNT(*) FROM inspection WHERE classification = 'migrated_import' AND result_status = 'data_missing'");
        $maintenanceTypes = ['legacy_classification_migration', 'import_result_reconciliation', 'csv_source_fact_reconciliation', 'inspection_duplicate_audit', 'inspection_duplicate_archive', 'inspection_csv_source_duplicate_archive', 'inspection_manual_csv_consolidation', 'all_report_regeneration'];
        $maintenanceJobs = array_values(array_map(static fn(array $job): array => [
            'id' => (string) ($job['id'] ?? ''),
            'type' => (string) ($job['type'] ?? ''),
            'state' => (string) ($job['state'] ?? ''),
            'step' => (int) ($job['step'] ?? 0),
            'total' => (int) ($job['total'] ?? 0),
            'message' => (string) ($job['message'] ?? ''),
        ], array_filter(BackgroundJobService::pending(200), static fn(array $job): bool => in_array((string) ($job['type'] ?? ''), $maintenanceTypes, true))));
        return [200, $headers, json_encode([
            'ok' => true,
            'query' => $query,
            'legacy_unclassified_count' => $unclassified,
            'import_result_reconciliation_count' => $importsToReconcile,
            'maintenance_jobs' => $maintenanceJobs,
            'rows' => $rows,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE)];
    }

    /** Read-only audit summary for duplicate or unusually close inspections. */
    private static function inspectionDuplicatesApiDebug(array $headers): array
    {
        $findings = R::getAll("SELECT review.finding_type, review.severity, review.reason, review.detected_at, review.status,
            device.external_number AS device_number, device.name AS device_name,
            earlier.id AS earlier_id, earlier.external_number AS earlier_number, earlier.test_date AS earlier_date,
            later.id AS later_id, later.external_number AS later_number, later.test_date AS later_date
            FROM inspectiondupreview review
            JOIN device ON device.id=review.device_id
            JOIN inspection earlier ON earlier.id=review.inspection_id
            JOIN inspection later ON later.id=review.peer_inspection_id
            WHERE review.status='open'
            ORDER BY CASE review.severity WHEN 'danger' THEN 0 ELSE 1 END, review.id DESC LIMIT 200");
        $jobs = array_values(array_map(static fn(array $job): array => [
            'id' => (string) ($job['id'] ?? ''), 'type' => (string) ($job['type'] ?? ''), 'state' => (string) ($job['state'] ?? ''),
            'step' => (int) ($job['step'] ?? 0), 'total' => (int) ($job['total'] ?? 0), 'message' => (string) ($job['message'] ?? ''),
        ], array_filter(BackgroundJobService::pending(200), static fn(array $job): bool => in_array((string) ($job['type'] ?? ''), ['inspection_duplicate_audit', 'csv_source_fact_reconciliation', 'inspection_duplicate_archive', 'inspection_csv_source_duplicate_archive'], true))));
        return [200, $headers, json_encode(['summary' => 'inspection-duplicates', 'ok' => true, 'open_count' => (int) R::getCell("SELECT COUNT(*) FROM inspectiondupreview WHERE status='open'"), 'jobs' => $jobs, 'findings' => $findings], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE)];
    }

    /** Read-only aggregate diagnosis for investigation of import quality. */
    private static function inspectionOverviewApiDebug(array $headers): array
    {
        $statusExpression = InspectionEvaluationService::sqlStatusExpression('i');
        $statusRows = R::getAll("SELECT {$statusExpression} AS status, COUNT(*) AS count FROM inspection i GROUP BY {$statusExpression}");
        $sourceRows = R::getAll(
            "SELECT COALESCE(NULLIF(i.source_type, ''), 'unbekannt') AS source_type,
                    COALESCE(NULLIF(i.source_file, ''), 'ohne Quelldatei') AS source_file,
                    COUNT(*) AS count
             FROM inspection i
             WHERE {$statusExpression} = 'data_missing'
             GROUP BY source_type, source_file
             ORDER BY count DESC, source_file ASC LIMIT 50"
        );
        $roomHistories = R::getAll(
            "SELECT d.id AS device_id, d.external_number, d.legacy_number, d.name,
                    COUNT(DISTINCT NULLIF(i.room_snapshot, '')) AS room_count,
                    GROUP_CONCAT(DISTINCT NULLIF(i.room_snapshot, '')) AS rooms,
                    COUNT(*) AS inspection_count
             FROM device d
             JOIN inspection i ON i.device_id = d.id
             WHERE NULLIF(i.room_snapshot, '') IS NOT NULL
             GROUP BY d.id
             HAVING COUNT(DISTINCT NULLIF(i.room_snapshot, '')) > 1
             ORDER BY room_count DESC, inspection_count DESC, d.id DESC LIMIT 100"
        );
        return [200, $headers, json_encode([
            'ok' => true,
            'summary' => 'inspection-overview',
            'status_counts' => $statusRows,
            'data_missing_by_source' => $sourceRows,
            'devices_with_multiple_room_snapshots' => $roomHistories,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE)];
    }

    /** Safe, header-secret protected AI probe; never returns a token or prompt. */
    private static function aiProviderApiDebug(array $headers): array
    {
        $provider = AiProviderService::selectedVocabularyProvider();
        $configuredModel = trim((string) get_app_config('vocabulary_ai_model', ''));
        $requestedModel = trim((string) ($_GET['model'] ?? ''));
        $model = $requestedModel !== '' && mb_strlen($requestedModel, 'UTF-8') <= 160 ? $requestedModel : $configuredModel;
        $baseUrl = trim((string) ($provider->base_url ?? ''));
        $result = [
            'ok' => false,
            'enabled' => get_app_config('vocabulary_ai_enabled', '0') === '1',
            'provider' => (string) ($provider->name ?? ''),
            'endpoint' => $baseUrl === '' ? '' : ((string) parse_url($baseUrl, PHP_URL_SCHEME) . '://' . (string) parse_url($baseUrl, PHP_URL_HOST) . (string) parse_url($baseUrl, PHP_URL_PATH)),
            'model' => $model,
            'configured_model' => $configuredModel,
            'temporary_model_override' => $requestedModel !== '' && $requestedModel !== $configuredModel,
        ];
        if ($provider === null || $model === '') {
            $result['error'] = 'Provider oder Modell ist nicht konfiguriert.';
            return [200, $headers, json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)];
        }
        try {
            $diagnostic = AiProviderService::diagnose($provider, $model);
            $result['ok'] = true;
            $result['response_model'] = $diagnostic['model'];
            $result['response'] = $diagnostic['content'];
        } catch (Throwable $exception) {
            $result['error'] = $exception->getMessage();
        }
        return [200, $headers, json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE)];
    }

    /** Read-only, redacted view of the server-side inspection permission calculation. */
    private static function userPermissionsApiDebug(array $headers): array
    {
        $users = [];
        foreach (R::findAll('oauthuser', ' ORDER BY LOWER(name), LOWER(email), id ') as $bean) {
            $email = trim((string) ($bean->email ?? ''));
            $name = trim((string) ($bean->name ?? '')) ?: ($email !== '' ? $email : 'Unbenannter Nutzer');
            $role = strtolower(trim((string) ($bean->role ?? ''))) ?: 'user';
            $users[] = [
                'id' => (int) $bean->id,
                'name' => $name,
                'selected_role' => $role,
                'report_signature_ready' => examiner_has_report_signature($email !== '' ? $email : $name),
            ];
        }
        $users = self::withInspectionPermissions($users);
        return [200, $headers, json_encode(['ok' => true, 'summary' => 'user-permissions', 'users' => array_map(static fn(array $user): array => [
            'id' => (int) $user['id'], 'name' => (string) $user['name'], 'role' => (string) $user['selected_role'], 'signature_ready' => !empty($user['report_signature_ready']), 'permissions' => $user['inspection_permissions'] ?? [],
        ], $users)], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE)];
    }

    /**
     * Read-only operational diagnosis for an explicitly allow-listed import root.
     * It deliberately exposes only filenames and aggregate counts, never file data.
     *
     * @param array<string,string> $headers
     */
    private static function importDirectoryApiDebug(string $directory, array $headers): array
    {
        $configuredRoot = rtrim(app_data_root(), '/');
        $configuredImportDirectory = trim((string) get_app_config('benning_reimport_directory', ''));
        $configuredReportsDirectory = trim((string) get_app_config('benning_reports_directory', ''));
        $allowedRoots = array_values(array_filter([
            '/tmp/berichte',
            $configuredRoot . '/uploads',
            $configuredImportDirectory,
            $configuredReportsDirectory,
        ], static fn(string $root): bool => $root !== ''));
        $resolved = realpath($directory);
        $allowed = false;
        if ($resolved !== false && is_dir($resolved)) {
            foreach ($allowedRoots as $allowedRoot) {
                $resolvedRoot = realpath($allowedRoot);
                if ($resolvedRoot !== false && ($resolved === $resolvedRoot || str_starts_with($resolved, rtrim($resolvedRoot, '/') . '/'))) {
                    $allowed = true;
                    break;
                }
            }
        }
        if (!$allowed) {
            return [400, $headers, json_encode([
                'ok' => false,
                'error' => 'Dieser Importpfad ist nicht freigegeben oder für den Webserver nicht lesbar.',
                'allowed_roots' => $allowedRoots,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)];
        }

        $counts = ['csv' => 0, 'ods' => 0, 'json' => 0, 'jsonl' => 0, 'pdf' => 0];
        $files = [];
        $csvPaths = [];
        $jsonlPaths = [];
        try {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($resolved, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::LEAVES_ONLY
            );
            foreach ($iterator as $file) {
                if (!$file instanceof \SplFileInfo || !$file->isFile()) continue;
                $extension = strtolower($file->getExtension());
                if (!array_key_exists($extension, $counts)) continue;
                $counts[$extension]++;
                $relative = ltrim(substr($file->getPathname(), strlen(rtrim($resolved, '/'))), '/');
                if (count($files) < 60) $files[] = $relative;
                if ($extension === 'csv') $csvPaths[] = $file->getPathname();
                if ($extension === 'jsonl') $jsonlPaths[] = $file->getPathname();
            }
        } catch (\UnexpectedValueException $e) {
            return [500, $headers, json_encode(['ok' => false, 'error' => 'Der Importpfad konnte nicht vollständig gelesen werden.', 'detail' => $e->getMessage()], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE)];
        }

        $pairs = [];
        $unpairedCsv = 0;
        foreach ($csvPaths as $csvPath) {
            $odsPath = substr($csvPath, 0, -4) . '.ods';
            $hasOds = is_file($odsPath);
            if (!$hasOds) $unpairedCsv++;
            if (count($pairs) < 30) {
                $pairs[] = [
                    'csv' => ltrim(substr($csvPath, strlen(rtrim($resolved, '/'))), '/'),
                    'ods_present' => $hasOds,
                ];
            }
        }
        $jsonlRegieFields = [];
        foreach ($jsonlPaths as $jsonlPath) {
            $handle = @fopen($jsonlPath, 'rb');
            if ($handle === false) continue;
            $lines = 0;
            try {
                while ($lines++ < 500 && ($line = fgets($handle)) !== false) {
                    $row = json_decode($line, true);
                    if (!is_array($row)) continue;
                    $stack = [$row];
                    $depth = 0;
                    while ($stack !== [] && $depth++ < 8) {
                        $data = array_pop($stack);
                        foreach ($data as $key => $value) {
                            if (preg_match('/regie|mehraufwand|zusätz|zusatz|extra(?:[ _-]?(?:work|aufwand))?|additional|arbeits(?:zeit|aufwand)|cost[ _-]?plus/iu', (string) $key) === 1) $jsonlRegieFields[(string) $key] = true;
                            if (is_array($value)) $stack[] = $value;
                        }
                    }
                }
            } finally { fclose($handle); }
        }
        $presentImportJob = static fn(array $job): array => [
            'id' => (string) ($job['id'] ?? ''),
            'type' => (string) ($job['type'] ?? ''),
            'label' => BackgroundJobService::label((string) ($job['type'] ?? '')),
            'state' => (string) ($job['state'] ?? ''),
            'current' => (int) ($job['current'] ?? 0),
            'total' => (int) ($job['total'] ?? 0),
            'message' => (string) ($job['message'] ?? ''),
            'created_at' => (string) ($job['created_at'] ?? ''),
            'finished_at' => (string) ($job['finished_at'] ?? ''),
        ];
        $isImportJob = static fn(array $job): bool => in_array((string) ($job['type'] ?? ''), ['directory_import', 'pending_measurement_import', 'phoenix_sync'], true);
        $jobs = array_values(array_map($presentImportJob, array_filter(BackgroundJobService::pending(200), $isImportJob)));
        $recentJobs = array_values(array_map($presentImportJob, array_filter(BackgroundJobService::latest(40), $isImportJob)));

        return [200, $headers, json_encode([
            'ok' => true,
            'directory' => $resolved,
            'readable' => is_readable($resolved),
            'file_counts' => $counts,
            'importable_file_count' => $counts['csv'] + $counts['json'] + $counts['jsonl'],
            'csv_without_matching_ods_count' => $unpairedCsv,
            'csv_ods_pairs' => $pairs,
            'jsonl_regie_fields' => array_keys($jsonlRegieFields),
            'sample_files' => $files,
            'pending_import_jobs' => $jobs,
            'recent_import_jobs' => $recentJobs,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE)];
    }

    /** Narrow operational diagnosis for the GUI-configured Phoenix repair cron. */
    private static function importConfigApiDebug(array $headers): array
    {
        $importDirectory = trim((string) get_app_config('benning_reimport_directory', ''));
        $reportsDirectory = trim((string) get_app_config('benning_reports_directory', ''));
        $migrationRoot = rtrim(app_data_root(), '/') . '/migrations';
        $present = static fn(array $job): array => [
            'id' => (string) ($job['id'] ?? ''),
            'type' => (string) ($job['type'] ?? ''),
            'state' => (string) ($job['state'] ?? ''),
            'current' => (int) ($job['current'] ?? 0),
            'total' => (int) ($job['total'] ?? 0),
            'message' => (string) ($job['message'] ?? ''),
            'created_at' => (string) ($job['created_at'] ?? ''),
            'finished_at' => (string) ($job['finished_at'] ?? ''),
        ];
        $repairTypes = ['directory_import', 'all_report_regeneration'];
        $jobs = array_values(array_map($present, array_filter(
            array_merge(BackgroundJobService::pending(200), BackgroundJobService::latest(80)),
            static fn(array $job): bool => in_array((string) ($job['type'] ?? ''), $repairTypes, true)
        )));
        return [200, $headers, json_encode([
            'ok' => true,
            'import_directory' => $importDirectory,
            'import_directory_exists' => $importDirectory !== '' && is_dir($importDirectory),
            'import_directory_readable' => $importDirectory !== '' && is_readable($importDirectory),
            'reports_directory' => $reportsDirectory,
            'reports_directory_exists' => $reportsDirectory !== '' && is_dir($reportsDirectory),
            'reports_directory_readable' => $reportsDirectory !== '' && is_readable($reportsDirectory),
            'migration_directory_writable' => is_dir($migrationRoot) && is_writable($migrationRoot),
            'repair_state' => [
                'regie_reimport_version' => (string) get_app_config('benning_import_regie_reimport_version', ''),
                'report_regeneration_version' => (string) get_app_config('benning_import_regie_reports_version', ''),
                'storage' => 'Datenbank-Konfiguration; unabhängig von Dateirechten des Migrationsordners.',
            ],
            'jobs' => $jobs,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE)];
    }

    /** Read-only diagnosis for disputed inspection states; strictly superadmin-only. */
    public static function inspectionDebug(array $params, bool $isHx): array
    {
        if (!current_user_is_superadmin()) return forbidden_response();

        $query = trim((string) ($_GET['q'] ?? ''));
        $rows = [];
        if ($query !== '') {
            $like = '%' . mb_strtolower($query) . '%';
            $rows = R::getAll(
                'SELECT i.*, d.external_number AS device_number, d.name AS device_name '
                . 'FROM inspection i LEFT JOIN device d ON d.id = i.device_id '
                . 'WHERE LOWER(COALESCE(i.external_number, \'\')) LIKE ? OR LOWER(COALESCE(d.external_number, \'\')) LIKE ? '
                . 'ORDER BY i.test_date DESC, i.id DESC LIMIT 100',
                [$like, $like]
            );
            foreach ($rows as &$row) {
                $row['computed_status'] = (string) R::getCell(
                    'SELECT ' . InspectionEvaluationService::sqlStatusExpression('i') . ' FROM inspection i WHERE i.id = ?',
                    [(int) $row['id']]
                );
                $row['expected_legacy'] = trim((string) ($row['test_date'] ?? '')) !== ''
                    && (string) $row['test_date'] < '2025-01-01';
            }
            unset($row);
        }
        $unclassified = (int) R::getCell("SELECT COUNT(*) FROM inspection WHERE COALESCE(test_date, '') <> '' AND test_date < '2025-01-01' AND COALESCE(classification, '') <> 'legacy'");
        $migration = BackgroundJobService::pending(200);
        $legacyJob = null;
        $importReconciliationJob = null;
        $duplicateAuditJob = null;
        foreach ($migration as $job) {
            if (($job['type'] ?? '') === 'legacy_classification_migration') {
                $legacyJob = $job;
                break;
            }
            if (($job['type'] ?? '') === 'import_result_reconciliation') $importReconciliationJob = $job;
            if (($job['type'] ?? '') === 'inspection_duplicate_audit') $duplicateAuditJob = $job;
        }
        $importsToReconcile = (int) R::getCell("SELECT COUNT(*) FROM inspection WHERE classification = 'migrated_import' AND result_status = 'data_missing'");
        $duplicateFindings = R::getAll("SELECT review.*, earlier.external_number AS earlier_number, earlier.test_date AS earlier_date, later.external_number AS later_number, later.test_date AS later_date, device.external_number AS device_number, device.name AS device_name
            FROM inspectiondupreview review
            JOIN inspection earlier ON earlier.id=review.inspection_id
            JOIN inspection later ON later.id=review.peer_inspection_id
            JOIN device ON device.id=review.device_id
            WHERE review.status='open'
            ORDER BY CASE review.severity WHEN 'danger' THEN 0 ELSE 1 END, review.detected_at DESC, review.id DESC LIMIT 60");
        $duplicateOpen = (int) R::getCell("SELECT COUNT(*) FROM inspectiondupreview WHERE status='open'");
        $content = render_template('admin_inspection_debug.php', compact('query', 'rows', 'unclassified', 'legacyJob', 'importsToReconcile', 'importReconciliationJob', 'duplicateAuditJob', 'duplicateFindings', 'duplicateOpen'));
        if ($isHx) return [200, ['Content-Type' => 'text/html; charset=utf-8'], $content];
        return [200, [], render_template('layout.php', ['title' => 'Prüfungsdiagnose', 'content' => $content])];
    }

    public static function enqueueLegacyClassificationMigration(array $params, bool $isHx): array
    {
        if (!current_user_is_superadmin()) return forbidden_response();
        $total = (int) R::getCell("SELECT COUNT(*) FROM inspection WHERE COALESCE(test_date, '') <> '' AND test_date < '2025-01-01' AND COALESCE(classification, '') <> 'legacy'");
        if ($total > 0) {
            BackgroundJobService::enqueue(
                'legacy_classification_migration',
                ['type' => 'legacy_classification_migration', 'owner_user_id' => (int) (current_user()->id ?? 0)],
                ['total' => $total, 'dedupe_key' => 'maintenance:legacy-classification:v2', 'cancellable' => false]
            );
            $_SESSION['meldung'] = $total . ' historische Prüfung(en) wurden zur Legacy-Klassifizierung vorgemerkt.';
        } else {
            $_SESSION['meldung'] = 'Keine unklassifizierten historischen Prüfungen gefunden.';
        }
        return [303, ['Location' => url_for('admin/debug/pruefungen')], ''];
    }

    public static function enqueueInspectionDuplicateAudit(array $params, bool $isHx): array
    {
        if (!current_user_is_superadmin()) return forbidden_response();
        $total = (int) R::getCell("SELECT COUNT(*) FROM inspection WHERE COALESCE(test_date, '') <> ''");
        if ($total > 0) {
            BackgroundJobService::enqueue(
                'inspection_duplicate_audit',
                ['type' => 'inspection_duplicate_audit', 'owner_user_id' => (int) (current_user()->id ?? 0)],
                ['total' => $total, 'dedupe_key' => 'maintenance:inspection-duplicate-audit:manual:' . date('YmdHis'), 'cancellable' => false]
            );
            $_SESSION['meldung'] = 'Prüfungsdubletten werden im Hintergrund erneut geprüft. Der Lauf verändert keine Prüfungen oder Rechnungen.';
        } else {
            $_SESSION['meldung'] = 'Keine datierten Prüfungen zum Prüfen gefunden.';
        }
        return [303, ['Location' => url_for('admin/debug/pruefungen')], ''];
    }

    public static function users(array $params, bool $isHx): array
    {
        if (!current_user_has_role('admin')) {
            return forbidden_response();
        }

        $roleOptions = available_user_roles();

        $beans = R::findAll('oauthuser', ' ORDER BY LOWER(name), LOWER(email), id ');
        $users = array_map(static function ($bean) use ($roleOptions) {
            $preferred = trim((string) ($bean->preferred_username ?? ''));
            $email = trim((string) ($bean->email ?? ''));
            $name = trim((string) ($bean->name ?? ''));

            if ($name === '') {
                $given = trim((string) ($bean->given_name ?? ''));
                $family = trim((string) ($bean->family_name ?? ''));
                $combined = trim($given . ' ' . $family);
                if ($combined !== '') {
                    $name = $combined;
                }
            }

            if ($name === '') {
                $name = $preferred !== '' ? $preferred : ($email !== '' ? $email : 'Unbenannter Nutzer');
            }

            $rawRole = strtolower(trim((string) ($bean->role ?? '')));
            if ($rawRole === '' || !array_key_exists($rawRole, $roleOptions)) {
                $rawRole = null;
            }

            $rawLastLogin = (string) ($bean->last_login_at ?? '');
            $lastLogin = null;
            if ($rawLastLogin !== '') {
                try {
                    $lastLogin = new \DateTimeImmutable($rawLastLogin);
                } catch (\Throwable) {
                    $lastLogin = null;
                }
            }

            return [
                'id' => (int) $bean->id,
                'name' => $name,
                'email' => $email,
                'preferred_username' => $preferred,
                'role' => $rawRole,
                'selected_role' => $rawRole ?? 'user',
                'role_missing' => $rawRole === null,
                'keycloak_url' => keycloak_user_admin_url((string) ($bean->sub ?? '')),
                'login_count' => (int) ($bean->login_count ?? 0),
                'last_login_at' => $lastLogin,
                'raw_last_login_at' => $rawLastLogin,
                'sub' => (string) ($bean->sub ?? ''),
                'report_signature_ready' => examiner_has_report_signature($email !== '' ? $email : $name),
                'report_signature_updated_at' => (string) ($bean->report_signature_updated_at ?? ''),
                'customer_ids' => array_map('intval', R::getCol('SELECT customer_id FROM oauthuser_customer WHERE oauthuser_id = ?', [(int) $bean->id])),
                'customer_access' => array_column(
                    R::getAll('SELECT customer_id, include_descendants FROM oauthuser_customer WHERE oauthuser_id = ?', [(int) $bean->id]),
                    'include_descendants',
                    'customer_id'
                ),
            ];
        }, array_values($beans));

        $users = self::withInspectionPermissions($users);

        $customers = array_values(R::findAll('customer', ' ORDER BY LOWER(name), id '));
        $content = render_template('admin_user_list.php', [
            'users' => $users,
            'roleOptions' => $roleOptions,
            'customers' => $customers,
            'customerRows' => self::customerHierarchy($customers),
            'canManageUsers' => current_user_is_superadmin(),
        ]);

        $body = render_template('layout.php', [
            'title' => 'Benutzerverwaltung',
            'content' => $content,
        ]);

        return [200, [], $body];
    }

    /** @param list<array<string,mixed>> $users @return list<array<string,mixed>> */
    private static function withInspectionPermissions(array $users): array
    {
        if ($users === []) return [];
        $inspectionTypes = InspectionTypeService::active();
        $requirementsByType = [];
        foreach (R::getAll('SELECT * FROM inspection_type_requirement WHERE active = 1 ORDER BY inspection_type_code, sort_order, id') as $requirement) {
            $requirementsByType[(string) $requirement['inspection_type_code']][] = $requirement;
        }
        $userIds = array_map(static fn(array $user): int => (int) $user['id'], $users);
        $marks = implode(',', array_fill(0, count($userIds), '?'));
        $qualificationsByUser = [];
        foreach (R::getAll("SELECT * FROM user_qualification WHERE oauthuser_id IN ({$marks}) ORDER BY id DESC", $userIds) as $qualification) {
            $qualificationsByUser[(int) $qualification['oauthuser_id']][(string) $qualification['requirement_code']][] = $qualification;
        }
        $today = date('Y-m-d');
        foreach ($users as &$user) {
            $roleAllowsInspection = in_array((string) ($user['selected_role'] ?? ''), ['editor', 'admin', 'superadmin'], true);
            $permissions = [];
            foreach ($inspectionTypes as $type) {
                $code = (string) $type['code'];
                $missing = [];
                if (!$roleAllowsInspection) $missing[] = 'Rolle erlaubt keine Prüfungen';
                if (empty($user['report_signature_ready'])) $missing[] = 'Unterschrift fehlt';
                foreach ($requirementsByType[$code] ?? [] as $requirement) {
                    $validProof = false;
                    foreach ($qualificationsByUser[(int) $user['id']][(string) $requirement['code']] ?? [] as $qualification) {
                        if (!empty($requirement['requires_confirmation']) && empty($qualification['confirmed_at'])) continue;
                        $expiryState = InspectionTypeService::qualificationExpiry($qualification, $requirement, $today);
                        if ($expiryState['state'] !== 'expired') { $validProof = true; break; }
                    }
                    if (!$validProof) $missing[] = (string) $requirement['name'];
                }
                $blocking = array_intersect($missing, ['Rolle erlaubt keine Prüfungen', 'Unterschrift fehlt']) !== [];
                $permissions[] = ['name' => (string) $type['name'], 'icon' => (string) ($type['icon'] ?: 'fa-clipboard-check'), 'allowed' => $missing === [], 'severity' => $missing === [] ? 'success' : ($blocking ? 'danger' : 'warning'), 'missing' => $missing];
            }
            $user['inspection_permissions'] = $permissions;
        }
        unset($user);
        return $users;
    }

    public static function auditLog(array $params, bool $isHx): array
    {
        // Audit data contains operational details, import payload metadata and
        // user/IP information.  Keep the entire endpoint (including HTMX
        // partials and direct links) restricted to administrators.
        if (!current_user_has_role('admin')) return forbidden_response();
        $requestedPage = isset($_GET['page']) ? (int) $_GET['page'] : 1;
        $auditFilters = [];
        foreach (['search', 'category', 'status', 'user', 'action', 'correlation_id', 'from', 'to'] as $filterKey) {
            $auditFilters[$filterKey] = trim((string) ($_GET[$filterKey] ?? ''));
        }
        $auditFilters['correlations'] = trim((string) ($_GET['correlations'] ?? ''));
        $auditRepository = new AuditTrailRepository();
        $events = $auditRepository->paginateEvents($requestedPage, 50, $auditFilters);
        $importRuns = $auditRepository->latestCorrelations('import', 20);
        $revisionPage = max(1, (int) ($_GET['revision_page'] ?? 1));
        $revisionPerPage = 50;
        $allRevisions = array_merge(
            (new RevisionHistory())->latest(RevisionSupport::enabledTables(), 100),
            (new TenantRepository())->latestRevisions(100)
        );
        usort($allRevisions, static fn(array $a, array $b): int => strcmp($b['timestamp'], $a['timestamp']));
        $revisionTotal = count($allRevisions);
        $revisionPages = max(1, (int) ceil($revisionTotal / $revisionPerPage));
        $revisionPage = min($revisionPage, $revisionPages);
        $revisions = array_slice($allRevisions, ($revisionPage - 1) * $revisionPerPage, $revisionPerPage);
        $cronPage = max(1, (int) ($_GET['cron_page'] ?? 1));
        $rawCronRuns = trim((string) ($_GET['cron_runs'] ?? ''));
        $cronRunFilters = array_values(array_unique(array_filter(array_map(
            static fn(string $value): string => preg_replace('/[^A-Za-z0-9_-]/', '', trim($value)),
            $rawCronRuns !== '' ? explode(',', $rawCronRuns) : [trim((string) ($_GET['cron_run'] ?? ''))]
        ))));
        $cronRunFilter = $cronRunFilters[0] ?? '';
        $cronPerPage = 50;
        // `cron_log` is intentionally an underscored SQL table name. RedBean
        // bean types cannot contain underscores, so do not call R::count()
        // with it as a bean type.
        $cronColumns = R::inspect('cron_log');
        $hasCronRunId = is_array($cronColumns) && isset($cronColumns['run_id']);
        if (!$hasCronRunId) $cronRunFilters = [];
        $cronWhere = $cronRunFilters !== [] ? ' WHERE run_id IN (' . implode(',', array_fill(0, count($cronRunFilters), '?')) . ')' : '';
        $cronArguments = $cronRunFilters;
        $cronTotal = (int) R::getCell('SELECT COUNT(*) FROM cron_log' . $cronWhere, $cronArguments);
        $cronPages = max(1, (int) ceil($cronTotal / $cronPerPage));
        $cronPage = min($cronPage, $cronPages);
        $cronOffset = ($cronPage - 1) * $cronPerPage;
        // LIMIT/OFFSET are validated integers here; interpolation keeps this
        // compatible with SQLite and the other RedBean drivers.
        $cronRunSelect = $hasCronRunId ? ', run_id' : ", '' AS run_id";
        $cronLog = R::getAll("SELECT run_at, level, message {$cronRunSelect} FROM cron_log{$cronWhere} ORDER BY id DESC LIMIT {$cronPerPage} OFFSET {$cronOffset}", $cronArguments);
        $cronImportant = $cronRunFilters !== []
            ? R::getAll("SELECT run_at, level, message {$cronRunSelect} FROM cron_log{$cronWhere}" . (str_contains($cronWhere, 'WHERE') ? " AND level IN ('warning', 'error', 'critical')" : '') . ' ORDER BY id DESC LIMIT 50', $cronArguments)
            : [];
        $cronRuns = $hasCronRunId
            ? R::getAll("SELECT run_id, MIN(run_at) AS started_at, MAX(run_at) AS finished_at, COUNT(*) AS entries, SUM(CASE WHEN level IN ('error','critical') THEN 1 ELSE 0 END) AS errors, SUM(CASE WHEN level = 'warning' THEN 1 ELSE 0 END) AS warnings FROM cron_log WHERE run_id != '' GROUP BY run_id ORDER BY MAX(id) DESC LIMIT 20")
            : [];
        $cronHeartbeat = [];
        $heartbeatPath = sys_get_temp_dir() . '/pruefapp-phoenix-jobs/cron-heartbeat.json';
        if (is_file($heartbeatPath)) $cronHeartbeat = json_decode((string) @file_get_contents($heartbeatPath), true) ?: [];
        $cronLastRun = trim((string) ($cronHeartbeat['last_run'] ?? ''));
        $cronAge = null;
        if ($cronLastRun !== '') {
            try { $cronAge = max(0, time() - (new DateTimeImmutable($cronLastRun))->getTimestamp()); } catch (Throwable) { $cronAge = null; }
        }
        $cronHealthy = $cronAge !== null && $cronAge <= 300;
        $cronPendingJobs = BackgroundJobService::pending(200);
        $cronRecentJobs = BackgroundJobService::latest(30);

        $content = render_template('audit_log.php', [
            'entries' => $events['entries'],
            'pagination' => $events['pagination'],
            'auditFilters' => $auditFilters,
            'importRuns' => $importRuns,
            'revisions' => $revisions,
            'cronLog' => $cronLog,
            'cronImportant' => $cronImportant,
            'cronRuns' => $cronRuns,
            'cronRunFilter' => $cronRunFilter,
            'cronRunFilters' => $cronRunFilters,
            'cronTotal' => $cronTotal,
            'cronPage' => $cronPage,
            'cronPages' => $cronPages,
            'cronLastRun' => $cronLastRun,
            'cronAge' => $cronAge,
            'cronHealthy' => $cronHealthy,
            'cronPendingJobs' => $cronPendingJobs,
            'cronRecentJobs' => $cronRecentJobs,
            'revisionPage' => $revisionPage,
            'revisionPages' => $revisionPages,
            'revisionTotal' => $revisionTotal,
        ]);

        if ($isHx) return [200, ['Content-Type' => 'text/html; charset=utf-8'], $content];

        $body = render_template('layout.php', [
            'title' => 'Audit & Revisionen',
            'content' => $content,
        ]);

        return [200, [], $body];
    }

    public static function exportAuditRuns(array $params, bool $isHx): array
    {
        if (!current_user_has_role('admin')) return forbidden_response();
        $kind = trim((string) ($_POST['kind'] ?? 'cron'));
        $format = strtolower(trim((string) ($_POST['format'] ?? 'json')));
        $rawIds = $_POST['ids'] ?? '';
        $ids = is_array($rawIds) ? $rawIds : explode(',', (string) $rawIds);
        $ids = array_values(array_unique(array_filter(array_map(
            static fn($value): string => preg_replace('/[^A-Za-z0-9_-]/', '', trim((string) $value)),
            $ids
        ))));
        if (!in_array($kind, ['cron', 'import'], true) || !in_array($format, ['csv', 'json'], true) || $ids === []) return [400, [], 'Keine gültige Lauf-Auswahl.'];
        $ids = array_slice($ids, 0, 200);
        if ($kind === 'cron') {
            $marks = implode(',', array_fill(0, count($ids), '?'));
            $rows = R::getAll("SELECT run_at, level, message, run_id, context_json FROM cron_log WHERE run_id IN ({$marks}) ORDER BY id ASC", $ids);
            $title = 'cron-laeufe';
        } else {
            $marks = implode(',', array_fill(0, count($ids), '?'));
            $rows = R::getAll("SELECT erstellt_am AS run_at, aktion AS action, nutzername AS user_name, anzeige_name AS display_name, status, correlation_id, details_json FROM auditlog WHERE category = 'import' AND correlation_id IN ({$marks}) ORDER BY id ASC", $ids);
            $title = 'import-laeufe';
        }
        $filename = $title . '-' . date('Ymd-His') . '.' . $format;
        if ($format === 'json') return [200, ['Content-Type' => 'application/json; charset=utf-8', 'Content-Disposition' => 'attachment; filename="' . $filename . '"'], json_encode(['kind' => $kind, 'run_ids' => $ids, 'rows' => $rows], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_INVALID_UTF8_SUBSTITUTE) ?: '{}'];
        $headers = $kind === 'cron' ? ['Zeitpunkt', 'Status', 'Meldung', 'Cron-Lauf', 'Kontext'] : ['Zeitpunkt', 'Aktion', 'Benutzer', 'Anzeigename', 'Status', 'Vorgang', 'Details'];
        $lines = [implode(';', array_map(static fn(string $value): string => '"' . str_replace('"', '""', $value) . '"', $headers))];
        foreach ($rows as $row) {
            $values = $kind === 'cron'
                ? [(string) ($row['run_at'] ?? ''), (string) ($row['level'] ?? ''), (string) ($row['message'] ?? ''), (string) ($row['run_id'] ?? ''), (string) ($row['context_json'] ?? '')]
                : [(string) ($row['run_at'] ?? ''), (string) ($row['action'] ?? ''), (string) ($row['user_name'] ?? ''), (string) ($row['display_name'] ?? ''), (string) ($row['status'] ?? ''), (string) ($row['correlation_id'] ?? ''), (string) ($row['details_json'] ?? '')];
            $lines[] = implode(';', array_map(static fn(string $value): string => '"' . str_replace('"', '""', $value) . '"', $values));
        }
        return [200, ['Content-Type' => 'text/csv; charset=utf-8', 'Content-Disposition' => 'attachment; filename="' . $filename . '"'], "\xEF\xBB\xBF" . implode("\r\n", $lines) . "\r\n"];
    }

    public static function updateUserRole(array $params, bool $isHx): array
    {
        if (!current_user_is_superadmin()) {
            return forbidden_response();
        }

        $userId = (int) ($params['id'] ?? 0);
        if ($userId <= 0) {
            return [404, [], '<h1>404 – Nutzer nicht gefunden</h1>'];
        }

        $user = R::load('oauthuser', $userId);
        if (!$user->id) {
            return [404, [], '<h1>404 – Nutzer nicht gefunden</h1>'];
        }

        $newRole = strtolower(trim((string) ($_POST['role'] ?? '')));
        $validRoles = array_keys(available_user_roles());

        if ($newRole === '') {
            $_SESSION['fehlermeldung'] = 'Bitte eine Rolle auswählen.';
        } elseif (!in_array($newRole, $validRoles, true)) {
            $_SESSION['fehlermeldung'] = 'Die ausgewählte Rolle ist ungültig.';
        } else {
            $previousRole = strtolower(trim((string) ($user->role ?? '')));

            if ($previousRole !== $newRole) {
                $user->role = $newRole;
                $user->updated_at = date('c');
                R::store($user);

                audit_log('nutzerrolle_geaendert', [
                    'oauthuser_id' => (int) $user->id,
                    'oauthuser_sub' => (string) ($user->sub ?? ''),
                    'rolle_alt' => $previousRole,
                    'rolle_neu' => $newRole,
                ]);

                $_SESSION['meldung'] = 'Rolle aktualisiert.';
            } else {
                $_SESSION['meldung'] = 'Die Rolle war bereits gesetzt.';
            }
        }

        return [303, ['Location' => url_for('admin/nutzer')], ''];
    }

    public static function loginAs(array $params, bool $isHx): array
    {
        if (!current_user_is_superadmin()) return forbidden_response();
        if (isset($_SESSION['impersonator_user_id'])) return [409, [], 'Eine Nutzeranmeldung ist bereits aktiv.'];
        $targetId = (int) ($params['id'] ?? 0);
        $target = R::load('oauthuser', $targetId);
        if (!$target->id) return [404, [], 'Nutzer nicht gefunden.'];
        if (strtolower((string) ($target->role ?? '')) === 'superadmin') return [403, [], 'Superadministratoren können nicht imitiert werden.'];
        $original = current_user();
        if (!$original || (int) $original->id === (int) $target->id) return [409, [], 'Diese Nutzeranmeldung ist nicht möglich.'];
        audit_log('nutzeranmeldung_gestartet', ['oauthuser_id' => (int) $target->id, 'durch_oauthuser_id' => (int) $original->id]);
        $_SESSION['impersonator_user_id'] = (int) $original->id;
        $_SESSION['impersonator_user_info'] = json_decode((string) ($original->userinfo_json ?? ''), true) ?: [
            'sub' => (string) ($original->sub ?? ''),
            'email' => (string) ($original->email ?? ''),
            'name' => (string) ($original->name ?? ''),
        ];
        $_SESSION['auth_user_id'] = (int) $target->id;
        $_SESSION['user_role'] = (string) ($target->role ?? 'user');
        $_SESSION['user'] = json_decode((string) ($target->userinfo_json ?? ''), true) ?: [
            'sub' => (string) ($target->sub ?? ''),
            'email' => (string) ($target->email ?? ''),
            'name' => (string) ($target->name ?? ''),
        ];
        try {
            $_SESSION['login_reminders'] = UserReminderService::afterLogin($target);
        } catch (Throwable $reminderError) {
            error_log('Prüferhinweise konnten bei der Nutzeranmeldung nicht erstellt werden: ' . $reminderError->getMessage());
            $_SESSION['login_reminders'] = [];
        }
        $_SESSION['meldung'] = 'Du bist jetzt als ' . trim((string) ($target->name ?: $target->email ?: 'Nutzer/in')) . ' angemeldet.';
        return [303, ['Location' => url_for()], ''];
    }

    public static function stopLoginAs(array $params, bool $isHx): array
    {
        $originalId = (int) ($_SESSION['impersonator_user_id'] ?? 0);
        if ($originalId <= 0) return [403, [], 'Keine Nutzeranmeldung aktiv.'];
        $currentId = current_user_id();
        $original = R::load('oauthuser', $originalId);
        if (!$original->id) return [410, [], 'Der ursprüngliche Superadmin wurde nicht gefunden.'];
        $_SESSION['auth_user_id'] = $originalId;
        $_SESSION['user_role'] = (string) ($original->role ?? 'superadmin');
        $_SESSION['user'] = json_decode((string) ($original->userinfo_json ?? ''), true) ?: ($_SESSION['impersonator_user_info'] ?? []);
        unset($_SESSION['impersonator_user_id'], $_SESSION['impersonator_user_info']);
        audit_log('nutzeranmeldung_beendet', ['oauthuser_id' => $currentId, 'durch_oauthuser_id' => $originalId]);
        $_SESSION['meldung'] = 'Die Nutzeranmeldung wurde beendet.';
        return [303, ['Location' => url_for('admin/nutzer')], ''];
    }

    public static function updateUserCustomers(array $params, bool $isHx): array
    {
        if (!current_user_is_superadmin()) return forbidden_response();
        $userId = (int) ($params['id'] ?? 0); $user = R::load('oauthuser', $userId);
        if (!$user->id) return [404, [], 'Nutzer nicht gefunden'];
        $descendants = is_array($_POST['include_descendants'] ?? null) ? $_POST['include_descendants'] : [];
        $selectedIds = array_values(array_unique(array_filter(array_map('intval', (array) ($_POST['customer_ids'] ?? [])), static fn(int $id): bool => $id > 0)));
        $customers = array_values(R::findAll('customer'));
        $parentById = [];
        foreach ($customers as $customer) {
            $customerId = (int) $customer->id;
            $parentById[$customerId] = (int) ($customer->parent_customer_id ?? 0);
        }
        $assignments = self::normalizeCustomerAssignments(
            $selectedIds,
            array_map('intval', array_keys($descendants)),
            $parentById
        );

        R::begin();
        try {
            R::exec('DELETE FROM oauthuser_customer WHERE oauthuser_id = ?', [$userId]);
            foreach ($assignments as $customerId => $includeDescendants) {
                R::exec(
                    'INSERT OR IGNORE INTO oauthuser_customer (oauthuser_id, customer_id, include_descendants, created_at) VALUES (?, ?, ?, ?)',
                    [$userId, $customerId, $includeDescendants ? 1 : 0, date('c')]
                );
            }
            R::commit();
        } catch (Throwable $exception) {
            R::rollback();
            throw $exception;
        }
        audit_log('nutzer_kundenzuordnung_geaendert', [
            'oauthuser_id' => $userId,
            'customer_ids' => array_map('intval', array_keys($assignments)),
            'include_descendants' => array_map('intval', array_keys(array_filter($assignments))),
        ]);
        $_SESSION['meldung'] = 'Kundenzuordnung aktualisiert.';
        return [303, ['Location' => url_for('admin/nutzer')], ''];
    }

    /**
     * Removes redundant child assignments covered by an ancestor assignment.
     *
     * @param array<int, int> $selectedIds
     * @param array<int, int> $includeDescendantIds
     * @param array<int, int> $parentById
     * @return array<int, bool>
     */
    public static function normalizeCustomerAssignments(
        array $selectedIds,
        array $includeDescendantIds,
        array $parentById
    ): array
    {
        $selectedSet = [];
        foreach (array_unique(array_map('intval', $selectedIds)) as $customerId) {
            if ($customerId > 0 && array_key_exists($customerId, $parentById)) $selectedSet[$customerId] = true;
        }
        $includeSet = [];
        foreach (array_unique(array_map('intval', $includeDescendantIds)) as $customerId) {
            if (isset($selectedSet[$customerId])) $includeSet[$customerId] = true;
        }
        $assignments = [];
        foreach (array_keys($selectedSet) as $customerId) {
            $parentId = $parentById[$customerId] ?? 0;
            $visited = [];
            $covered = false;
            while ($parentId > 0 && !isset($visited[$parentId])) {
                $visited[$parentId] = true;
                if (isset($selectedSet[$parentId], $includeSet[$parentId])) {
                    $covered = true;
                    break;
                }
                $parentId = $parentById[$parentId] ?? 0;
            }
            if (!$covered) $assignments[$customerId] = isset($includeSet[$customerId]);
        }
        return $assignments;
    }

    /**
     * @param array<int, object> $customers
     * @return array<int, array{customer: object, id: int, parent_id: int, depth: int, has_children: bool}>
     */
    private static function customerHierarchy(array $customers): array
    {
        $byId = [];
        $children = [];
        foreach ($customers as $customer) $byId[(int) $customer->id] = $customer;
        foreach ($customers as $customer) {
            $id = (int) $customer->id;
            $parentId = (int) ($customer->parent_customer_id ?? 0);
            if ($parentId === $id || !isset($byId[$parentId])) $parentId = 0;
            $children[$parentId][] = $id;
        }
        $rows = [];
        $visited = [];
        $append = static function (int $id, int $depth) use (&$append, &$rows, &$visited, $byId, $children): void {
            if (isset($visited[$id]) || !isset($byId[$id])) return;
            $visited[$id] = true;
            $customer = $byId[$id];
            $rows[] = [
                'customer' => $customer,
                'id' => $id,
                'parent_id' => (int) ($customer->parent_customer_id ?? 0),
                'depth' => $depth,
                'has_children' => !empty($children[$id]),
            ];
            foreach ($children[$id] ?? [] as $childId) $append($childId, $depth + 1);
        };
        foreach ($children[0] ?? [] as $rootId) $append($rootId, 0);
        foreach (array_keys($byId) as $id) $append($id, 0);
        return $rows;
    }
}
