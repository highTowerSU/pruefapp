<?php

declare(strict_types=1);

use RedBeanPHP\OODBBean;
use RedBeanPHP\R;
use Ceneos\PhpBase\Tenant\TenantRepository;

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
        if (!current_user_has_role('admin')) {
            $allowed = array_fill_keys(current_user_customer_ids(), true);
            $data['customers'] = array_values(array_filter($data['customers'], static fn($row): bool => isset($allowed[(int) $row->id])));
            $data['sites'] = array_values(array_filter($data['sites'], static fn($row): bool => isset($allowed[(int) $row->customer_id])));
            $siteIds = array_fill_keys(array_map(static fn($row): int => (int) $row->id, $data['sites']), true);
            $data['buildings'] = array_values(array_filter($data['buildings'], static fn($row): bool => isset($siteIds[(int) $row->site_id])));
            $buildingIds = array_fill_keys(array_map(static fn($row): int => (int) $row->id, $data['buildings']), true);
            $data['floors'] = array_values(array_filter($data['floors'], static fn($row): bool => isset($buildingIds[(int) $row->building_id])));
            $floorIds = array_fill_keys(array_map(static fn($row): int => (int) $row->id, $data['floors']), true);
            $data['areas'] = array_values(array_filter($data['areas'], static fn($row): bool => isset($floorIds[(int) $row->floor_id])));
            $areaIds = array_fill_keys(array_map(static fn($row): int => (int) $row->id, $data['areas']), true);
            $data['rooms'] = array_values(array_filter($data['rooms'], static fn($row): bool => isset($floorIds[(int) $row->floor_id]) || isset($areaIds[(int) ($row->area_id ?? 0)])));
        }
        $customerOrder = [];
        foreach ($data['customers'] as $index => $customer) $customerOrder[(int) $customer->id] = $index;
        usort($data['sites'], static function ($a, $b) use ($customerOrder): int {
            return [$customerOrder[(int) $a->customer_id] ?? PHP_INT_MAX, mb_strtolower((string) $a->name)] <=> [$customerOrder[(int) $b->customer_id] ?? PHP_INT_MAX, mb_strtolower((string) $b->name)];
        });
        $siteOrder = [];
        foreach ($data['sites'] as $index => $site) $siteOrder[(int) $site->id] = $index;
        usort($data['buildings'], static function ($a, $b) use ($siteOrder): int {
            return [$siteOrder[(int) $a->site_id] ?? PHP_INT_MAX, mb_strtolower((string) $a->name)] <=> [$siteOrder[(int) $b->site_id] ?? PHP_INT_MAX, mb_strtolower((string) $b->name)];
        });
        $buildingOrder = [];
        foreach ($data['buildings'] as $index => $building) $buildingOrder[(int) $building->id] = $index;
        usort($data['floors'], static function ($a, $b) use ($buildingOrder): int {
            return [$buildingOrder[(int) $a->building_id] ?? PHP_INT_MAX, (int) ($a->sort_order ?? 0), mb_strtolower((string) $a->name)] <=> [$buildingOrder[(int) $b->building_id] ?? PHP_INT_MAX, (int) ($b->sort_order ?? 0), mb_strtolower((string) $b->name)];
        });
        $data['canManage'] = current_user_has_role('admin');
        $data['tenants'] = (new TenantRepository())->all();
        $data['sevdeskContactsByTenant'] = [];
        foreach ($data['tenants'] as $tenant) $data['sevdeskContactsByTenant'][(int) $tenant->id] = self::sevdeskContacts($tenant);

        $content = render_template('structure_index.php', $data);
        if ($isHx) return [200, ['Content-Type' => 'text/html; charset=utf-8'], $content];
        return [200, [], render_template('layout.php', [
            'title' => 'Struktur',
            'content' => $content,
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
    public static function deleteRoom(array $params, bool $isHx): array { $params['type'] = 'room'; return self::delete($params, $isHx); }

    public static function bulkAction(array $params, bool $isHx): array
    {
        if (!current_user_has_role('admin')) return forbidden_response();
        $type = (string) ($_POST['type'] ?? '');
        $ids = array_values(array_unique(array_filter(array_map('intval', (array) ($_POST['structure_ids'] ?? [])), static fn(int $id): bool => $id > 0)));
        if (!isset(self::DEFINITIONS[$type]) || $ids === []) return self::redirectWithError('Bitte Einträge eines Strukturtyps auswählen.');
        $cascade = ($_POST['cascade'] ?? '0') === '1';
        foreach ($ids as $id) {
            $entity = R::load($type, $id);
            if (!$entity->id) continue;
            $descendants = self::descendants($type, $id);
            $roomIds = $descendants['room'] ?? [];
            if ($type === 'room') $roomIds[] = $id;
            $hasChildren = $descendants !== [] || ($type === 'room' && (int) R::count('device', 'room_id = ?', [$id]) > 0);
            if ($hasChildren && !$cascade) return self::redirectWithError('Mindestens ein Eintrag enthält Unterstruktur. Bitte „mit Unterstruktur“ bestätigen.');
            if ($cascade && $roomIds !== []) {
                $marks = implode(',', array_fill(0, count($roomIds), '?'));
                R::exec('DELETE FROM inspection WHERE device_id IN (SELECT id FROM device WHERE room_id IN (' . $marks . '))', $roomIds);
                R::exec('DELETE FROM device WHERE room_id IN (' . $marks . ')', $roomIds);
            }
            if ($cascade) foreach (['room', 'area', 'floor', 'building', 'site', 'customer'] as $table) foreach ($descendants[$table] ?? [] as $childId) R::exec("DELETE FROM {$table} WHERE id = ?", [$childId]);
            R::trash($entity);
        }
        $_SESSION['meldung'] = count($ids) . ' Struktur-Eintrag(e) gelöscht.';
        return [303, ['Location' => url_for('struktur')], ''];
    }

    public static function moveDevices(array $params, bool $isHx): array
    {
        if (!current_user_has_role('admin')) return forbidden_response();
        $sourceId = (int) ($params['id'] ?? 0);
        $targetId = (int) ($_POST['target_room_id'] ?? 0);
        $source = R::load('room', $sourceId);
        $target = R::load('room', $targetId);
        if (!$source->id || !$target->id || $sourceId === $targetId) return self::redirectWithError('Bitte einen anderen Zielraum auswählen.');
        $count = (int) R::exec('UPDATE device SET room_id = ?, updated_at = ? WHERE room_id = ?', [$targetId, date(DATE_ATOM), $sourceId]);
        $_SESSION['meldung'] = $count . ' Gerät(e) nach ' . (string) $target->name . ' verschoben.';
        return [303, ['Location' => url_for('struktur')], ''];
    }

    public static function delete(array $params, bool $isHx): array
    {
        if (!current_user_has_role('admin')) return forbidden_response();
        $type = (string) ($params['type'] ?? '');
        if (!isset(self::DEFINITIONS[$type])) return [404, [], 'Strukturtyp nicht gefunden'];
        $id = (int) ($params['id'] ?? 0);
        $entity = R::load($type, $id);
        if (!$entity->id) return self::redirectWithError('Eintrag nicht gefunden.');
        $cascade = ($_POST['cascade'] ?? '0') === '1';
        $descendants = self::descendants($type, $id);
        if ($descendants !== [] && !$cascade) return self::redirectWithError('Der Eintrag enthält noch Unterstruktur. Bitte die Kaskadenlöschung bestätigen.');
        if ($cascade) {
            $roomIds = $descendants['room'] ?? [];
            if ($type === 'room') $roomIds[] = $id;
            if ($roomIds !== []) {
                $marks = implode(',', array_fill(0, count($roomIds), '?'));
                R::exec('DELETE FROM inspection WHERE device_id IN (SELECT id FROM device WHERE room_id IN (' . $marks . '))', $roomIds);
                R::exec('DELETE FROM device WHERE room_id IN (' . $marks . ')', $roomIds);
            }
            foreach (['room', 'area', 'floor', 'building', 'site', 'customer'] as $table) foreach ($descendants[$table] ?? [] as $childId) R::exec("DELETE FROM {$table} WHERE id = ?", [$childId]);
        }
        if ($type === 'room') {
            R::exec('DELETE FROM inspection WHERE device_id IN (SELECT id FROM device WHERE room_id = ?)', [$id]);
            R::exec('DELETE FROM device WHERE room_id = ?', [$id]);
            R::exec('DELETE FROM room WHERE id = ?', [$id]);
            $_SESSION['meldung'] = 'Raum und zugehörige Geräte gelöscht.';
            return [303, ['Location' => url_for('struktur')], ''];
        }
        R::trash($entity);
        $_SESSION['meldung'] = 'Struktureintrag gelöscht.';
        return [303, ['Location' => url_for('struktur')], ''];
    }

    private static function descendants(string $type, int $id): array
    {
        $children = ['customer' => ['site', 'customer_id'], 'site' => ['building', 'site_id'], 'building' => ['floor', 'building_id'], 'floor' => ['area', 'floor_id', 'room', 'floor_id'], 'area' => ['room', 'area_id']];
        $result = [];
        $walk = static function (string $parentType, int $parentId) use (&$walk, &$result, $children): void {
            if (!isset($children[$parentType])) return;
            $rules = $children[$parentType];
            for ($i = 0; $i < count($rules); $i += 2) {
                [$childType, $column] = [$rules[$i], $rules[$i + 1]];
                $beans = R::findAll($childType, " {$column} = ? ", [$parentId]);
                foreach ($beans as $bean) { $childId = (int) $bean->id; $result[$childType][] = $childId; $walk($childType, $childId); }
            }
        };
        $walk($type, $id);
        return $result;
    }

    private static function save(string $type): array
    {
        if (!current_user_has_role('admin')) return forbidden_response();
        $definition = self::DEFINITIONS[$type];
        $id = (int) ($_POST['id'] ?? 0);
        $entity = $id > 0 ? R::load($type, $id) : R::dispense($type);
        if ($id > 0 && !$entity->id) return self::redirectWithError('Eintrag nicht gefunden.');

        $name = trim((string) ($_POST['name'] ?? ''));
        $parentId = (int) ($_POST[$definition['parent']] ?? 0);
        $submittedFloorCode = '';
        if ($type === 'floor') {
            $submittedFloorCode = self::code($_POST['code'] ?? '', false);
            if ($submittedFloorCode === '') return self::redirectWithError('Bitte ein Etagenkürzel angeben, z. B. U, E oder 1.');
            $building = $parentId > 0 ? R::load('building', $parentId) : null;
            if ($building !== null && $building->id && $submittedFloorCode !== '') {
                $name = (string) ($building->code ?? '') . $submittedFloorCode;
            }
        }
        if ($type !== 'customer' && !self::validParent($definition['parent_table'], $parentId)) {
            return self::redirectWithError($definition['label'] . ': Zuordnung fehlt.');
        }
        if ($name === '') return self::redirectWithError($definition['label'] . ': Name fehlt.');
        if ($type === 'customer' && $id > 0 && $parentId === $id) {
            return self::redirectWithError('Ein Kunde kann nicht sein eigener Unterkunde sein.');
        }

        try {
            $metadata = self::metadata((string) ($_POST['metadata_json'] ?? ''));
        } catch (\InvalidArgumentException $exception) {
            return self::redirectWithError($exception->getMessage());
        }

        $entity->name = $name;
        if ($type === 'customer') {
            $entity->tenant_id = (int) ($_POST['tenant_id'] ?? 0);
            if ($entity->tenant_id <= 0) return self::redirectWithError('Bitte dem Kunden einen Mandanten zuordnen.');
            $entity->sevdesk_customer_id = trim((string) ($_POST['sevdesk_customer_id'] ?? ''));
            $entity->sevdesk_customer_number = trim((string) ($_POST['sevdesk_customer_number'] ?? ''));
        }
        $entity->{$definition['parent']} = $parentId > 0 ? $parentId : null;
        $description = trim((string) ($_POST['description'] ?? ''));
        if (mb_strlen($description) > 240) return self::redirectWithError('Die Kurzbeschreibung darf maximal 240 Zeichen enthalten.');
        $entity->description = $description;
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
        } elseif (in_array($type, ['customer', 'site'], true)) {
            $entity->code = self::code($_POST['code'] ?? '', true);
        } elseif ($type === 'floor') {
            $entity->code = $submittedFloorCode;
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

    private static function sevdeskContacts($tenant): array
    {
        try {
            if (!$tenant || trim((string) ($tenant->sevdesk_api_token ?? '')) === '') return [];
            $contacts = (new SevDeskClient((string) ($tenant->sevdesk_api_url ?? 'https://my.sevdesk.de/api/v1'), (string) $tenant->sevdesk_api_token))->contacts();
            usort($contacts, static function (array $left, array $right): int {
                $leftName = trim((string) ($left['name'] ?? (($left['familyname'] ?? '') . ' ' . ($left['firstname'] ?? ''))));
                $rightName = trim((string) ($right['name'] ?? (($right['familyname'] ?? '') . ' ' . ($right['firstname'] ?? ''))));
                return [mb_strtolower($leftName), (string) ($left['customerNumber'] ?? $left['customer_number'] ?? '')] <=> [mb_strtolower($rightName), (string) ($right['customerNumber'] ?? $right['customer_number'] ?? '')];
            });
            return $contacts;
        } catch (Throwable) {
            return [];
        }
    }

    public static function floorIdentifier(OODBBean $floor, ?OODBBean $building = null): string
    {
        $building ??= R::load('building', (int) $floor->building_id);
        return (string) ($building->code ?? '') . (string) ($floor->code ?? '');
    }

    public static function roomIdentifier(OODBBean $room, OODBBean $floor, ?OODBBean $area = null): string
    {
        $number = (string) ($room->number ?: $room->name);
        if (str_contains($number, '-')) return $number;
        if ($number !== '' && preg_match('/^[^\d]+$/u', $number) && !preg_match('/^[A-Z]{1,3}$/', $number)) return $number;
        $building = R::load('building', (int) $floor->building_id);
        $buildingCode = (string) ($building->code ?? '');
        $floorCode = trim((string) ($floor->code ?? ''));
        if ($floorCode === '') {
            $floorName = trim((string) ($floor->name ?? ''));
            if ($buildingCode !== '' && str_starts_with(strtoupper($floorName), strtoupper($buildingCode))) {
                $floorCode = trim(substr($floorName, strlen($buildingCode)));
            }
        }
        $site = R::load('site', (int) $building->site_id);
        $customer = R::load('customer', (int) $site->customer_id);
        $pattern = trim((string) ($floor->room_code_pattern ?? ''));
        if ($pattern === '' || $pattern === 'auto') {
            $pattern = 'auto';
            $visitedCustomers = [];
            while ($customer !== null && $customer->id && !isset($visitedCustomers[(int) $customer->id])) {
                $visitedCustomers[(int) $customer->id] = true;
                $customerPattern = trim((string) ($customer->room_code_pattern ?? ''));
                if ($customerPattern !== '' && $customerPattern !== 'auto') {
                    $pattern = $customerPattern;
                    break;
                }
                $parentId = (int) ($customer->parent_customer_id ?? 0);
                $customer = $parentId > 0 ? R::load('customer', $parentId) : null;
                if ($customer === null || !$customer->id) break;
            }
        }

        if ($pattern === '' || $pattern === 'auto') {
            if ($area !== null && $area->id) return (string) $area->code . $number;
            if (in_array($floorCode, ['U', 'UG', 'K'], true)) {
                return $buildingCode . $floorCode . $number;
            }
            return preg_match('/^-?\d+$/', $floorCode) ? $floorCode . '.' . $number : $floorCode . $number;
        }

        return strtr($pattern, [
            '{building}' => $buildingCode,
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
        $decoded = json_decode($json);
        if (!$decoded instanceof \stdClass) throw new \InvalidArgumentException('Metadaten müssen ein JSON-Objekt sein, z. B. {"kostenstelle":"1000"}.');
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
