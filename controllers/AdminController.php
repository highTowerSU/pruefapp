<?php

declare(strict_types=1);

use RedBeanPHP\R as R;

class AdminController
{
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
}
