<?php

declare(strict_types=1);

use Ceneos\PhpBase\Auth\RolePolicy;
use RedBeanPHP\R;

/** Shared backend authority for inspection types and examiner eligibility. */
final class InspectionTypeService
{
    public const ELECTRICAL = 'electrical';
    public const LADDER = 'ladder';

    /** @return list<array<string,mixed>> */
    public static function active(): array
    {
        $types = R::getAll('SELECT * FROM inspection_type WHERE active = 1 ORDER BY sort_order, name');
        foreach ($types as &$type) {
            $type['icon'] = match ((string) ($type['code'] ?? '')) {
                self::LADDER => 'fa-stairs',
                self::ELECTRICAL => 'fa-bolt',
                default => trim((string) ($type['icon'] ?? '')) ?: 'fa-clipboard-check',
            };
        }
        unset($type);
        return $types;
    }

    /** @return array<string,mixed>|null */
    public static function find(string $code): ?array
    {
        $row = R::getRow('SELECT * FROM inspection_type WHERE code = ? AND active = 1 LIMIT 1', [trim($code)]);
        return $row === [] ? null : $row;
    }

    public static function normalize(string $code): string
    {
        return self::find($code) !== null ? trim($code) : self::ELECTRICAL;
    }

    public static function defaultCatalogId(string $type): int
    {
        return (int) R::getCell(
            'SELECT id FROM inspection_catalog_version WHERE inspection_type_code = ? AND active = 1 ORDER BY id DESC LIMIT 1',
            [self::normalize($type)]
        );
    }

    /**
     * Determine the expiry state of a qualification.  A quarter of the
     * configured validity is retained as a clearly labelled grace period;
     * this is not an automatic renewal.
     *
     * @return array{expires_at:string,grace_until:string,state:string,grace_days:int}
     */
    public static function qualificationExpiry(array $qualification, array $requirement, ?string $today = null): array
    {
        $validity = (int) ($requirement['validity_days'] ?? 0);
        $expiry = trim((string) ($qualification['expires_at'] ?? ''));
        if ($expiry === '' && $validity > 0 && trim((string) ($qualification['issued_at'] ?? '')) !== '') {
            $expiry = date('Y-m-d', strtotime((string) $qualification['issued_at'] . ' +' . $validity . ' days'));
        }
        if ($validity < 1 || $expiry === '') return ['expires_at' => $expiry, 'grace_until' => '', 'state' => 'valid', 'grace_days' => 0];
        $graceDays = max(1, (int) ceil($validity * 0.25));
        $graceUntil = (new DateTimeImmutable($expiry))->modify('+' . $graceDays . ' days')->format('Y-m-d');
        $current = $today ?: date('Y-m-d');
        $state = $current <= $expiry ? 'valid' : ($current <= $graceUntil ? 'grace' : 'expired');
        return ['expires_at' => $expiry, 'grace_until' => $graceUntil, 'state' => $state, 'grace_days' => $graceDays];
    }

    /** @return array{allowed:bool,message:string,requirements:list<array<string,mixed>>,missing:list<string>,warnings:list<string>,grace:bool} */
    public static function examinerEligibility(object $user, string $type): array
    {
        $type = self::normalize($type);
        $requirements = R::getAll('SELECT * FROM inspection_type_requirement WHERE inspection_type_code = ? AND active = 1 ORDER BY sort_order, id', [$type]);
        if ($requirements === []) {
            return ['allowed' => true, 'message' => '', 'requirements' => [], 'missing' => [], 'warnings' => [], 'grace' => false];
        }
        $missing = [];
        $warnings = [];
        $inGrace = false;
        foreach ($requirements as $requirement) {
            $qualification = R::getRow(
                'SELECT * FROM user_qualification WHERE oauthuser_id = ? AND requirement_code = ? ORDER BY confirmed_at DESC, id DESC LIMIT 1',
                [(int) $user->id, (string) $requirement['code']]
            );
            if ($qualification === []) {
                $missing[] = (string) $requirement['name'];
                continue;
            }
            $confirmed = empty($requirement['requires_confirmation']) || !empty($qualification['confirmed_at']);
            $expiryState = self::qualificationExpiry($qualification, $requirement);
            if (!$confirmed) $missing[] = (string) $requirement['name'];
            elseif ($expiryState['state'] === 'expired') $missing[] = (string) $requirement['name'];
            elseif ($expiryState['state'] === 'grace') { $inGrace = true; $warnings[] = (string) $requirement['name'] . ' ist seit ' . date('d.m.Y', strtotime($expiryState['expires_at'])) . ' abgelaufen; Kulanzfrist bis ' . date('d.m.Y', strtotime($expiryState['grace_until'])) . '.'; }
        }
        return $missing === []
            ? ['allowed' => true, 'message' => $warnings === [] ? '' : implode(' ', $warnings), 'requirements' => $requirements, 'missing' => [], 'warnings' => $warnings, 'grace' => $inGrace]
            : ['allowed' => false, 'message' => 'Für diese Prüfart fehlen bestätigte oder gültige Nachweise: ' . implode(', ', $missing) . '.', 'requirements' => $requirements, 'missing' => $missing, 'warnings' => $warnings, 'grace' => false];
    }

    /**
     * Single backend authority for whether a user may start an inspection.
     * UI elements only display this decision; they do not reimplement it.
     *
     * @return array{allowed:bool,message:string,requirements:list<array<string,mixed>>,missing:list<string>}
     */
    public static function permissionForUser(object $user, string $type): array
    {
        $eligibility = self::examinerEligibility($user, $type);
        $missing = $eligibility['missing'];
        if (!RolePolicy::allows((string) ($user->role ?? ''), RolePolicy::EDITOR)) {
            array_unshift($missing, 'Technischer Prüfzugang (Editor/in oder Administration)');
        }
        $identity = trim((string) (($user->email ?? '') ?: ($user->name ?? '') ?: ($user->preferred_username ?? '')));
        if ($identity === '' || !examiner_has_report_signature($identity)) {
            $missing[] = 'Unterschrift im Profil';
        }
        $missing = array_values(array_unique($missing));
        return [
            'allowed' => $missing === [],
            'message' => $missing === [] ? ($eligibility['message'] ?? '') : 'Für diese Prüfart fehlen: ' . implode(', ', $missing) . '.',
            'requirements' => $eligibility['requirements'],
            'missing' => $missing,
            'warnings' => $eligibility['warnings'] ?? [],
            'grace' => (bool) ($eligibility['grace'] ?? false),
        ];
    }

    /** @return array<string,mixed> */
    public static function deviceAttributes(int $deviceId, string $type): array
    {
        $values = [];
        foreach (R::getAll('SELECT attribute_key, value_json FROM device_attribute WHERE device_id = ? AND inspection_type_code = ?', [$deviceId, self::normalize($type)]) as $row) {
            $decoded = json_decode((string) $row['value_json'], true);
            $values[(string) $row['attribute_key']] = is_array($decoded) ? ($decoded['value'] ?? null) : $decoded;
        }
        return $values;
    }

    /** @param array<string,mixed> $attributes */
    public static function saveDeviceAttributes(int $deviceId, string $type, array $attributes): void
    {
        $type = self::normalize($type);
        foreach (self::attributeDefinitions($type) as $key => $definition) {
            if (!array_key_exists($key, $attributes)) continue;
            $value = is_bool($attributes[$key]) ? $attributes[$key] : trim((string) $attributes[$key]);
            $row = R::getRow('SELECT id FROM device_attribute WHERE device_id = ? AND inspection_type_code = ? AND attribute_key = ?', [$deviceId, $type, $key]);
            $payload = json_encode(['value' => $value, 'label' => $definition['label']], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($row === []) {
                R::exec('INSERT INTO device_attribute (device_id, inspection_type_code, attribute_key, value_json, updated_at) VALUES (?, ?, ?, ?, ?)', [$deviceId, $type, $key, $payload, date(DATE_ATOM)]);
            } else {
                R::exec('UPDATE device_attribute SET value_json = ?, updated_at = ? WHERE id = ?', [$payload, date(DATE_ATOM), (int) $row['id']]);
            }
        }
    }

    /** @return array<string,array{label:string,type:string,options?:array<string,string>}> */
    public static function attributeDefinitions(string $type): array
    {
        return match (self::normalize($type)) {
            self::LADDER => [
                'ladder_kind' => ['label' => 'Leiterart', 'type' => 'select', 'options' => ['stehleiter' => 'Stehleiter', 'anlegeleiter' => 'Anlegeleiter', 'schiebleiter' => 'Schiebeleiter', 'steckleiter' => 'Steckleiter', 'mehrzweckleiter' => 'Mehrzweckleiter', 'podestleiter' => 'Podestleiter', 'tritt' => 'Tritt', 'sonstige' => 'Sonstige']],
                'material' => ['label' => 'Werkstoff', 'type' => 'select', 'options' => ['aluminium' => 'Aluminium', 'kunststoff' => 'Kunststoff', 'holz' => 'Holz', 'stahl' => 'Stahl', 'edelstahl' => 'Edelstahl']],
                'ladder_length_cm' => ['label' => 'Leiterlänge (cm)', 'type' => 'number'],
                'rung_count' => ['label' => 'Sprossen/Stufen', 'type' => 'number'],
                'article_number' => ['label' => 'Artikel-/Typnummer', 'type' => 'text'],
                'purchased_on' => ['label' => 'Anschaffungsdatum', 'type' => 'date'],
                'retired_on' => ['label' => 'Aussonderungsdatum', 'type' => 'date'],
            ],
            default => [
                'warming_device' => ['label' => 'Wärmegerät', 'type' => 'boolean'],
                'cable_length_m' => ['label' => 'Kabellänge (m)', 'type' => 'number'],
            ],
        };
    }
}
