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
        $events = (new AuditTrailRepository())->paginateEvents($requestedPage, 50);
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
        $cronPerPage = 50;
        $cronTotal = (int) R::count('cron_log');
        $cronPages = max(1, (int) ceil($cronTotal / $cronPerPage));
        $cronPage = min($cronPage, $cronPages);
        $cronOffset = ($cronPage - 1) * $cronPerPage;
        // LIMIT/OFFSET are validated integers here; interpolation keeps this
        // compatible with SQLite and the other RedBean drivers.
        $cronLog = R::getAll("SELECT run_at, level, message FROM cron_log ORDER BY id DESC LIMIT {$cronPerPage} OFFSET {$cronOffset}");

        $content = render_template('audit_log.php', [
            'entries' => $events['entries'],
            'pagination' => $events['pagination'],
            'revisions' => $revisions,
            'cronLog' => $cronLog,
            'cronTotal' => $cronTotal,
            'cronPage' => $cronPage,
            'cronPages' => $cronPages,
            'revisionPage' => $revisionPage,
            'revisionPages' => $revisionPages,
            'revisionTotal' => $revisionTotal,
        ]);

        $body = render_template('layout.php', [
            'title' => 'Audit & Revisionen',
            'content' => $content,
        ]);

        return [200, [], $body];
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
