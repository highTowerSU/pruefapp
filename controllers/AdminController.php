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
        if ($query === '' || mb_strlen($query) > 120) {
            return [400, $headers, json_encode(['ok' => false, 'error' => 'Parameter q (Geräte- oder Prüfnummer) fehlt.'], JSON_UNESCAPED_UNICODE)];
        }
        $like = '%' . mb_strtolower($query) . '%';
        $rows = R::getAll(
            'SELECT i.id, i.device_id, i.external_number, i.test_date, i.next_due_date, i.result_status, i.status, i.classification, i.source_type, i.source_file, '
            . 'i.result_reason_code, i.result_reason_text, d.external_number AS device_number, d.name AS device_name '
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
            $snapshot = json_decode((string) R::getCell('SELECT legacy_row_json FROM inspection_source_snapshot WHERE inspection_id = ?', [(int) $row['id']]), true);
            $row['source_snapshot_status'] = is_array($snapshot)
                ? InspectionEvaluationService::normalizeStatus((string) ($snapshot['result_status'] ?? ''), (string) ($snapshot['status'] ?? ''))
                : '';
            $snapshot = json_decode((string) R::getCell('SELECT legacy_row_json FROM inspection_source_snapshot WHERE inspection_id = ?', [(int) $row['id']]), true);
            $row['source_snapshot_status'] = is_array($snapshot)
                ? InspectionEvaluationService::normalizeStatus((string) ($snapshot['result_status'] ?? ''), (string) ($snapshot['status'] ?? ''))
                : '';
        }
        unset($row);
        $unclassified = (int) R::getCell("SELECT COUNT(*) FROM inspection WHERE COALESCE(test_date, '') <> '' AND test_date < '2025-01-01' AND COALESCE(classification, '') <> 'legacy'");
        $importsToReconcile = (int) R::getCell("SELECT COUNT(*) FROM inspection WHERE classification = 'migrated_import' AND result_status = 'data_missing'");
        $maintenanceJobs = array_values(array_map(static fn(array $job): array => [
            'id' => (string) ($job['id'] ?? ''),
            'type' => (string) ($job['type'] ?? ''),
            'state' => (string) ($job['state'] ?? ''),
            'step' => (int) ($job['step'] ?? 0),
            'total' => (int) ($job['total'] ?? 0),
            'message' => (string) ($job['message'] ?? ''),
        ], array_filter(BackgroundJobService::pending(200), static fn(array $job): bool => in_array((string) ($job['type'] ?? ''), ['legacy_classification_migration', 'import_result_reconciliation'], true))));
        return [200, $headers, json_encode([
            'ok' => true,
            'query' => $query,
            'legacy_unclassified_count' => $unclassified,
            'import_result_reconciliation_count' => $importsToReconcile,
            'maintenance_jobs' => $maintenanceJobs,
            'rows' => $rows,
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
        foreach ($migration as $job) {
            if (($job['type'] ?? '') === 'legacy_classification_migration') {
                $legacyJob = $job;
                break;
            }
            if (($job['type'] ?? '') === 'import_result_reconciliation') $importReconciliationJob = $job;
        }
        $importsToReconcile = (int) R::getCell("SELECT COUNT(*) FROM inspection WHERE classification = 'migrated_import' AND result_status = 'data_missing'");
        $content = render_template('admin_inspection_debug.php', compact('query', 'rows', 'unclassified', 'legacyJob', 'importsToReconcile', 'importReconciliationJob'));
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
                'customer_ids' => array_map('intval', R::getCol('SELECT customer_id FROM oauthuser_customer WHERE oauthuser_id = ?', [(int) $bean->id])),
                'customer_access' => array_column(
                    R::getAll('SELECT customer_id, include_descendants FROM oauthuser_customer WHERE oauthuser_id = ?', [(int) $bean->id]),
                    'include_descendants',
                    'customer_id'
                ),
            ];
        }, array_values($beans));

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

    public static function auditLog(array $params, bool $isHx): array
    {
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

        $content = render_template('audit_log.php', [
            'entries' => $events['entries'],
            'pagination' => $events['pagination'],
            'auditFilters' => $auditFilters,
            'importRuns' => $importRuns,
            'revisions' => $revisions,
            'cronLog' => $cronLog,
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
