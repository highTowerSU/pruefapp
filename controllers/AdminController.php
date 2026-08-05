<?php

declare(strict_types=1);

use RedBeanPHP\R as R;
use Ceneos\PhpBase\Audit\AuditTrailRepository;
use Ceneos\PhpBase\Audit\RevisionHistory;
use Ceneos\PhpBase\Database\RevisionSupport;
use Ceneos\PhpBase\Tenant\TenantRepository;

class AdminController
{
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
            ];
        }, array_values($beans));

        $content = render_template('admin_user_list.php', [
            'users' => $users,
            'roleOptions' => $roleOptions,
            'customers' => R::findAll('customer', ' ORDER BY LOWER(name), id '),
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

    public static function updateUserCustomers(array $params, bool $isHx): array
    {
        if (!current_user_is_superadmin()) return forbidden_response();
        $userId = (int) ($params['id'] ?? 0); $user = R::load('oauthuser', $userId);
        if (!$user->id) return [404, [], 'Nutzer nicht gefunden'];
        R::exec('DELETE FROM oauthuser_customer WHERE oauthuser_id = ?', [$userId]);
        foreach (array_unique(array_map('intval', (array) ($_POST['customer_ids'] ?? []))) as $customerId) {
            if ($customerId > 0 && R::load('customer', $customerId)->id) R::exec('INSERT OR IGNORE INTO oauthuser_customer (oauthuser_id, customer_id, created_at) VALUES (?, ?, ?)', [$userId, $customerId, date('c')]);
        }
        audit_log('nutzer_kundenzuordnung_geaendert', ['oauthuser_id' => $userId]);
        $_SESSION['meldung'] = 'Kundenzuordnung aktualisiert.';
        return [303, ['Location' => url_for('admin/nutzer')], ''];
    }
}
