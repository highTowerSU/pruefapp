<?php

declare(strict_types=1);

use RedBeanPHP\R;

/** Shared backend authority for inspection types and examiner eligibility. */
final class InspectionTypeService
{
    public const ELECTRICAL = 'electrical';
    public const LADDER = 'ladder';

    /** @return list<array<string,mixed>> */
    public static function active(): array
    {
        return R::getAll('SELECT * FROM inspection_type WHERE active = 1 ORDER BY sort_order, name');
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

    /** @return array{allowed:bool,message:string,requirements:list<array<string,mixed>>} */
    public static function examinerEligibility(object $user, string $type): array
    {
        $type = self::normalize($type);
        $requirements = R::getAll('SELECT * FROM inspection_type_requirement WHERE inspection_type_code = ? AND active = 1 ORDER BY sort_order, id', [$type]);
        if ($requirements === []) {
            return ['allowed' => true, 'message' => '', 'requirements' => []];
        }
        $missing = [];
        foreach ($requirements as $requirement) {
            $qualification = R::getRow(
                'SELECT * FROM user_qualification WHERE oauthuser_id = ? AND requirement_code = ? ORDER BY confirmed_at DESC, id DESC LIMIT 1',
                [(int) $user->id, (string) $requirement['code']]
            );
            $confirmed = empty($requirement['requires_confirmation']) || !empty($qualification['confirmed_at']);
            $expiry = trim((string) ($qualification['expires_at'] ?? ''));
            if ($expiry === '' && !empty($requirement['validity_days']) && trim((string) ($qualification['issued_at'] ?? '')) !== '') {
                $expiry = date('Y-m-d', strtotime((string) $qualification['issued_at'] . ' +' . (int) $requirement['validity_days'] . ' days'));
            }
            $valid = $expiry === '' || $expiry >= date('Y-m-d');
            if (!$confirmed || !$valid) $missing[] = (string) $requirement['name'];
        }
        return $missing === []
            ? ['allowed' => true, 'message' => '', 'requirements' => $requirements]
            : ['allowed' => false, 'message' => 'Für diese Prüfart fehlen bestätigte oder gültige Nachweise: ' . implode(', ', $missing) . '.', 'requirements' => $requirements];
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
