<?php

declare(strict_types=1);

use RedBeanPHP\OODBBean;
use RedBeanPHP\R;

class StructureController
{
    private const DEFINITIONS = [
        'customer' => ['parent' => 'parent_customer_id', 'parent_table' => 'customer', 'label' => 'Kunde'],
        'site' => ['parent' => 'customer_id', 'parent_table' => 'customer', 'label' => 'Standort'],
        'building' => ['parent' => 'site_id', 'parent_table' => 'site', 'label' => 'Gebäude'],
        'floor' => ['parent' => 'building_id', 'parent_table' => 'building', 'label' => 'Etage'],
        'area' => ['parent' => 'floor_id', 'parent_table' => 'floor', 'label' => 'Bereich'],
        'room' => ['parent' => 'floor_id', 'parent_table' => 'floor', 'label' => 'Raum'],
    ];

    public static function index(array $params, bool $isHx): array
    {
        if (!current_user()) return [303, ['Location' => url_for('login.php')], ''];

        $data = [];
        foreach (array_keys(self::DEFINITIONS) as $table) {
            $order = $table === 'floor' ? ' ORDER BY building_id, sort_order, name ' : ' ORDER BY name ';
            $data[$table . 's'] = array_values(R::findAll($table, $order));
        }
        $data['canManage'] = current_user_can_manage_courses();

        return [200, [], render_template('layout.php', [
            'title' => 'Struktur',
            'content' => render_template('structure_index.php', $data),
        ])];
    }

    public static function saveCustomer(array $params, bool $isHx): array { return self::save('customer'); }
    public static function saveSite(array $params, bool $isHx): array { return self::save('site'); }
    public static function saveBuilding(array $params, bool $isHx): array { return self::save('building'); }
    public static function saveFloor(array $params, bool $isHx): array { return self::save('floor'); }
    public static function saveArea(array $params, bool $isHx): array { return self::save('area'); }
    public static function saveRoom(array $params, bool $isHx): array { return self::save('room'); }

    /** Compatibility with the previous routes. */
    public static function createCustomer(array $params, bool $isHx): array { return self::saveCustomer($params, $isHx); }
    public static function createSite(array $params, bool $isHx): array { return self::saveSite($params, $isHx); }
    public static function createBuilding(array $params, bool $isHx): array { return self::saveBuilding($params, $isHx); }
    public static function createFloor(array $params, bool $isHx): array { return self::saveFloor($params, $isHx); }
    public static function createRoom(array $params, bool $isHx): array { return self::saveRoom($params, $isHx); }

    private static function save(string $type): array
    {
        if (!current_user_can_manage_courses()) return forbidden_response();
        $definition = self::DEFINITIONS[$type];
        $id = (int) ($_POST['id'] ?? 0);
        $entity = $id > 0 ? R::load($type, $id) : R::dispense($type);
        if ($id > 0 && !$entity->id) return self::redirectWithError('Eintrag nicht gefunden.');

        $name = trim((string) ($_POST['name'] ?? ''));
        $parentId = (int) ($_POST[$definition['parent']] ?? 0);
        if ($name === '') return self::redirectWithError($definition['label'] . ': Name fehlt.');
        if ($type !== 'customer' && !self::validParent($definition['parent_table'], $parentId)) {
            return self::redirectWithError($definition['label'] . ': Zuordnung fehlt.');
        }
        if ($type === 'customer' && $parentId === $id) return self::redirectWithError('Ein Kunde kann nicht sein eigener Unterkunde sein.');

        try {
            $metadata = self::metadata((string) ($_POST['metadata_json'] ?? ''));
        } catch (\InvalidArgumentException $exception) {
            return self::redirectWithError($exception->getMessage());
        }

        $entity->name = $name;
        $entity->{$definition['parent']} = $parentId > 0 ? $parentId : null;
        $entity->comment = trim((string) ($_POST['comment'] ?? ''));
        $entity->metadata_json = $metadata;
        if ($type === 'customer' || $type === 'floor') {
            try {
                $entity->room_code_pattern = self::pattern((string) ($_POST['room_code_pattern'] ?? ''));
            } catch (\InvalidArgumentException $exception) {
                return self::redirectWithError($exception->getMessage());
            }
        }
        $entity->updated_at = date(DATE_ATOM);
        if (!$entity->created_at) $entity->created_at = $entity->updated_at;

        if ($type === 'building') {
            $entity->code = self::code($_POST['code'] ?? '', true);
            if ($entity->code === '') return self::redirectWithError('Bitte ein Gebäudekürzel angeben, z. B. AB.');
        } elseif ($type === 'floor') {
            $entity->code = self::code($_POST['code'] ?? '', false);
            if ($entity->code === '') return self::redirectWithError('Bitte ein Etagenkürzel angeben, z. B. U, E oder 1.');
            $entity->sort_order = isset($_POST['sort_order']) && $_POST['sort_order'] !== ''
                ? (int) $_POST['sort_order']
                : self::floorSort((string) $entity->code);
        } elseif ($type === 'area') {
            $entity->code = self::code($_POST['code'] ?? '', true);
            if ($entity->code === '') return self::redirectWithError('Bitte ein Bereichskürzel angeben, z. B. E oder F.');
        } elseif ($type === 'room') {
            $entity->number = trim((string) ($_POST['number'] ?? $name));
            $areaId = (int) ($_POST['area_id'] ?? 0);
            if ($areaId > 0) {
                $area = R::load('area', $areaId);
                if (!$area->id || (int) $area->floor_id !== $parentId) {
                    return self::redirectWithError('Der Bereich gehört nicht zur ausgewählten Etage.');
                }
            }
            $entity->area_id = $areaId > 0 ? $areaId : null;
        }

        R::store($entity);
        audit_log('struktur_' . $type . '_gespeichert', ['id' => (int) $entity->id, 'name' => $name]);
        $_SESSION['meldung'] = $definition['label'] . ' gespeichert.';
        return [303, ['Location' => url_for('struktur')], ''];
    }

    public static function floorIdentifier(OODBBean $floor, ?OODBBean $building = null): string
    {
        $building ??= R::load('building', (int) $floor->building_id);
        return (string) ($building->code ?? '') . (string) ($floor->code ?? '');
    }

    public static function roomIdentifier(OODBBean $room, OODBBean $floor, ?OODBBean $area = null): string
    {
        $number = (string) ($room->number ?: $room->name);
        $floorCode = (string) ($floor->code ?? '');
        $building = R::load('building', (int) $floor->building_id);
        $site = R::load('site', (int) $building->site_id);
        $customer = R::load('customer', (int) $site->customer_id);
        $pattern = trim((string) ($floor->room_code_pattern ?? ''))
            ?: trim((string) ($customer->room_code_pattern ?? 'auto'));

        if ($pattern === '' || $pattern === 'auto') {
            if ($area !== null && $area->id) return (string) $area->code . $number;
            if (in_array($floorCode, ['U', 'UG', 'K'], true)) {
                return (string) ($building->code ?? '') . $floorCode . $number;
            }
            return preg_match('/^-?\d+$/', $floorCode) ? $floorCode . '.' . $number : $floorCode . $number;
        }

        return strtr($pattern, [
            '{building}' => (string) ($building->code ?? ''),
            '{floor}' => $floorCode,
            '{area}' => (string) ($area->code ?? ''),
            '{room}' => $number,
        ]);
    }

    private static function validParent(string $table, int $id): bool { return $id > 0 && (bool) R::load($table, $id)->id; }
    private static function code(mixed $value, bool $lettersOnly): string
    {
        $code = strtoupper(trim((string) $value));
        return preg_replace($lettersOnly ? '/[^A-Z0-9-]/' : '/[^A-Z0-9-]/', '', $code) ?? '';
    }
    private static function floorSort(string $code): int
    {
        if (in_array($code, ['U', 'UG', 'K'], true)) return -100;
        if (in_array($code, ['E', 'EG', '0'], true)) return 0;
        return is_numeric($code) ? (int) $code * 100 : 10000;
    }
    private static function metadata(string $json): string
    {
        $json = trim($json);
        if ($json === '') return '{}';
        $decoded = json_decode($json, true);
        if (!is_array($decoded) || array_is_list($decoded)) throw new \InvalidArgumentException('Metadaten müssen ein JSON-Objekt sein, z. B. {"kostenstelle":"1000"}.');
        return json_encode($decoded, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    private static function pattern(string $pattern): string
    {
        $pattern = trim($pattern);
        if ($pattern === '' || $pattern === 'auto') return $pattern;
        $withoutTokens = str_replace(['{building}', '{floor}', '{area}', '{room}'], '', $pattern);
        if (str_contains($withoutTokens, '{') || str_contains($withoutTokens, '}') || !str_contains($pattern, '{room}')) {
            throw new \InvalidArgumentException('Ungültiges Kennungsmuster. Erlaubt sind {building}, {floor}, {area} und {room}.');
        }
        return $pattern;
    }
    private static function redirectWithError(string $message): array
    {
        $_SESSION['fehlermeldung'] = $message;
        return [303, ['Location' => url_for('struktur')], ''];
    }
}
