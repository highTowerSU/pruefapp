<?php

declare(strict_types=1);

use RedBeanPHP\R;

class DeviceController
{
    public static function index(array $params, bool $isHx): array
    {
        if (!current_user()) return [303, ['Location' => url_for('login.php')], ''];
        $rooms = array_values(R::findAll('room', ' ORDER BY name '));
        $roomLabels = [];
        foreach ($rooms as $room) {
            $floor = R::load('floor', (int) $room->floor_id);
            $area = (int) ($room->area_id ?? 0) > 0 ? R::load('area', (int) $room->area_id) : null;
            $building = R::load('building', (int) $floor->building_id);
            $site = R::load('site', (int) $building->site_id);
            $customer = R::load('customer', (int) $site->customer_id);
            $tokens = [
                self::token($customer),
                self::token($site),
                self::token($building),
                StructureController::roomIdentifier($room, $floor, $area),
                (string) $room->name,
            ];
            $roomLabels[(int) $room->id] = implode(' · ', array_filter($tokens));
        }
        $requestedPerPage = (int) ($_GET['per_page'] ?? 50);
        $perPage = in_array($requestedPerPage, [25, 50, 100, 200], true) ? $requestedPerPage : 50;
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $query = trim((string) ($_GET['q'] ?? ''));
        $year = trim((string) ($_GET['year'] ?? ''));
        $from = trim((string) ($_GET['from'] ?? ''));
        $to = trim((string) ($_GET['to'] ?? ''));
        $customerId = (int) ($_GET['customer_id'] ?? 0);
        $siteId = (int) ($_GET['site_id'] ?? 0);
        $buildingId = (int) ($_GET['building_id'] ?? 0);
        $floorId = (int) ($_GET['floor_id'] ?? 0);
        $roomId = (int) ($_GET['room_id'] ?? 0);
        $deviceId = (int) ($_GET['device_id'] ?? 0);
        $sort = (string) ($_GET['sort'] ?? 'name');
        $orderBy = ['room' => 'd.room_id, d.name', 'id' => 'd.id', 'name' => 'd.name'][$sort] ?? 'd.name';
        $where = [];
        $paramsQuery = [];
        if ($customerId > 0) { $where[] = 'c.id = ?'; $paramsQuery[] = $customerId; }
        if ($siteId > 0) { $where[] = 's.id = ?'; $paramsQuery[] = $siteId; }
        if ($buildingId > 0) { $where[] = 'b.id = ?'; $paramsQuery[] = $buildingId; }
        if ($floorId > 0) { $where[] = 'f.id = ?'; $paramsQuery[] = $floorId; }
        if ($roomId > 0) { $where[] = 'r.id = ?'; $paramsQuery[] = $roomId; }
        if ($deviceId > 0) { $where[] = 'd.id = ?'; $paramsQuery[] = $deviceId; }
        if ($query !== '') { $where[] = '(LOWER(d.name) LIKE ? OR LOWER(d.external_number) LIKE ? OR LOWER(d.inventory_number) LIKE ? OR LOWER(d.description) LIKE ? OR LOWER(d.comment) LIKE ?)'; $like = '%' . strtolower($query) . '%'; array_push($paramsQuery, $like, $like, $like, $like, $like); }
        $dateWhere = [];
        if (preg_match('/^\d{4}$/', $year)) { $dateWhere[] = 'i.test_date >= ? AND i.test_date < ?'; $paramsQuery[] = $year . '-01-01'; $paramsQuery[] = ((int) $year + 1) . '-01-01'; }
        if ($from !== '') { $dateWhere[] = 'i.test_date >= ?'; $paramsQuery[] = $from; }
        if ($to !== '') { $dateWhere[] = 'i.test_date <= ?'; $paramsQuery[] = $to; }
        $structureJoin = ' LEFT JOIN room r ON r.id = d.room_id LEFT JOIN floor f ON f.id = r.floor_id LEFT JOIN building b ON b.id = f.building_id LEFT JOIN site s ON s.id = b.site_id LEFT JOIN customer c ON c.id = s.customer_id ';
        $join = $structureJoin . ($dateWhere !== [] ? ' JOIN inspection i ON i.device_id = d.id ' : '');
        if ($dateWhere !== []) $where[] = '(' . implode(' AND ', $dateWhere) . ')';
        $whereSql = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);
        $total = (int) R::getCell('SELECT COUNT(DISTINCT d.id) FROM device d' . $join . $whereSql, $paramsQuery);
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $pages);
        $offset = ($page - 1) * $perPage;
        $devices = array_values(R::getAll('SELECT DISTINCT d.* FROM device d' . $join . $whereSql . ' ORDER BY ' . $orderBy . ' LIMIT ' . $perPage . ' OFFSET ' . $offset, $paramsQuery));
        $deviceBeans = [];
        foreach ($devices as $row) { $bean = R::load('device', (int) $row['id']); $deviceBeans[] = $bean; }
        $devices = $deviceBeans;
        $inspections = [];
        foreach ($devices as $device) {
            $inspections[(int) $device->id] = array_values(R::findAll('inspection', ' device_id = ? ORDER BY test_date DESC, id DESC ', [(int) $device->id]));
        }
        $customers = array_values(R::findAll('customer', ' ORDER BY name '));
        $sites = array_values(R::findAll('site', ' ORDER BY name '));
        $buildings = array_values(R::findAll('building', ' ORDER BY name '));
        $floors = array_values(R::findAll('floor', ' ORDER BY sort_order, name '));
        $entityLabel = static function ($entity): string {
            $code = trim((string) ($entity->code ?? ''));
            $name = trim((string) ($entity->name ?? ''));
            return $code !== '' && $name !== '' && strcasecmp($code, $name) !== 0 ? $code . ' · ' . $name : ($name ?: $code);
        };
        $siteLabels = $buildingLabels = $floorLabels = [];
        $siteCustomerIds = $buildingSiteIds = $floorBuildingIds = $roomFloorIds = [];
        foreach ($sites as $site) $siteLabels[(int) $site->id] = $entityLabel($site);
        foreach ($sites as $site) $siteCustomerIds[(int) $site->id] = (int) $site->customer_id;
        foreach ($buildings as $building) { $buildingLabels[(int) $building->id] = $entityLabel($building); $buildingSiteIds[(int) $building->id] = (int) $building->site_id; }
        foreach ($floors as $floor) { $floorLabels[(int) $floor->id] = $entityLabel($floor); $floorBuildingIds[(int) $floor->id] = (int) $floor->building_id; }
        foreach ($rooms as $room) $roomFloorIds[(int) $room->id] = (int) $room->floor_id;
        return [200, [], render_template('layout.php', [
            'title' => 'Geräte',
            'content' => render_template('device_index.php', [
                'devices' => $devices,
                'inspections' => $inspections,
                'rooms' => $rooms,
                'roomLabels' => $roomLabels,
                'customers' => $customers,
                'sites' => $sites,
                'buildings' => $buildings,
                'floors' => $floors,
                'siteLabels' => $siteLabels,
                'buildingLabels' => $buildingLabels,
                'floorLabels' => $floorLabels,
                'siteCustomerIds' => $siteCustomerIds,
                'buildingSiteIds' => $buildingSiteIds,
                'floorBuildingIds' => $floorBuildingIds,
                'roomFloorIds' => $roomFloorIds,
                'canManage' => current_user_can_manage_courses(),
                'inspectionReportUrl' => static fn(int $id): string => url_for('admin/pruefungen/' . $id . '/bericht'),
                'page' => $page,
                'pages' => $pages,
                'total' => $total,
                'filters' => ['q' => $query, 'year' => $year, 'from' => $from, 'to' => $to, 'customer_id' => $customerId, 'site_id' => $siteId, 'building_id' => $buildingId, 'floor_id' => $floorId, 'room_id' => $roomId, 'per_page' => $perPage, 'sort' => $sort],
            ]),
        ])];
    }

    public static function save(array $params, bool $isHx): array
    {
        if (!current_user_can_manage_courses()) return forbidden_response();
        $id = (int) ($_POST['id'] ?? 0);
        $device = $id > 0 ? R::load('device', $id) : R::dispense('device');
        $name = trim((string) ($_POST['name'] ?? ''));
        $roomId = (int) ($_POST['room_id'] ?? 0);
        if ($name === '' || !$roomId || !R::load('room', $roomId)->id) {
            $_SESSION['fehlermeldung'] = 'Gerätename und Raum sind erforderlich.';
            return [303, ['Location' => url_for('geraete')], ''];
        }
        $metadata = trim((string) ($_POST['metadata_json'] ?? ''));
        $decoded = $metadata === '' ? new \stdClass() : json_decode($metadata);
        if (!$decoded instanceof \stdClass) {
            $_SESSION['fehlermeldung'] = 'Metadaten müssen ein JSON-Objekt sein.';
            return [303, ['Location' => url_for('geraete')], ''];
        }
        $device->name = $name;
        $device->room_id = $roomId;
        $device->serial_number = trim((string) ($_POST['serial_number'] ?? ''));
        $device->device_model = trim((string) ($_POST['device_model'] ?? ''));
        $device->manufacturer = trim((string) ($_POST['manufacturer'] ?? ''));
        $device->warming_device = isset($_POST['warming_device']) ? 1 : 0;
        $device->inventory_number = trim((string) ($_POST['inventory_number'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? ''));
        if (mb_strlen($description) > 240) {
            $_SESSION['fehlermeldung'] = 'Die Kurzbeschreibung darf maximal 240 Zeichen enthalten.';
            return [303, ['Location' => url_for('geraete')], ''];
        }
        $device->description = $description;
        $device->comment = trim((string) ($_POST['comment'] ?? ''));
        $device->metadata_json = json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $device->updated_at = date(DATE_ATOM);
        if (!$device->created_at) $device->created_at = $device->updated_at;
        R::store($device);
        audit_log('geraet_gespeichert', ['id' => (int) $device->id, 'name' => $name]);
        $_SESSION['meldung'] = 'Gerät gespeichert.';
        return [303, ['Location' => url_for('geraete')], ''];
    }

    private static function token($bean): string
    {
        return trim((string) ($bean->code ?? '')) ?: (string) ($bean->name ?? '');
    }
}
