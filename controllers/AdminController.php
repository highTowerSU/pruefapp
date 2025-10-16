<?php

declare(strict_types=1);

use RedBeanPHP\R as R;

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
            ];
        }, array_values($beans));

        $content = render_template('admin_user_list.php', [
            'users' => $users,
            'roleOptions' => $roleOptions,
        ]);

        $body = render_template('layout.php', [
            'title' => 'Benutzerverwaltung',
            'content' => $content,
        ]);

        return [200, [], $body];
    }

    public static function auditLog(array $params, bool $isHx): array
    {
        $limit = 200;
        $beans = R::findAll('auditlog', ' ORDER BY id DESC LIMIT ' . (int) $limit);

        $entries = array_map(static function ($bean) {
            $details = [];
            $rawDetails = (string) ($bean->details_json ?? '');
            if ($rawDetails !== '') {
                $decoded = json_decode($rawDetails, true);
                if (is_array($decoded)) {
                    $details = $decoded;
                }
            }

            $rawTimestamp = (string) ($bean->erstellt_am ?? '');
            $timestamp = null;
            if ($rawTimestamp !== '') {
                try {
                    $timestamp = new \DateTimeImmutable($rawTimestamp);
                } catch (\Exception) {
                    $timestamp = null;
                }
            }

            return [
                'id' => (int) $bean->id,
                'aktion' => (string) ($bean->aktion ?? ''),
                'nutzername' => (string) ($bean->nutzername ?? ''),
                'anzeige_name' => (string) ($bean->anzeige_name ?? ''),
                'ip_adresse' => (string) ($bean->ip_adresse ?? ''),
                'details' => $details,
                'zeitpunkt' => $timestamp,
                'zeitpunkt_roh' => $rawTimestamp,
            ];
        }, array_values($beans));

        $content = render_template('audit_log.php', [
            'entries' => $entries,
        ]);

        $body = render_template('layout.php', [
            'title' => 'Audit-Log',
            'content' => $content,
        ]);

        return [200, [], $body];
    }

    public static function updateUserRole(array $params, bool $isHx): array
    {
        if (!current_user_has_role('admin')) {
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
}
