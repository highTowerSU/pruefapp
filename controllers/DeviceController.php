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
        return [200, [], render_template('layout.php', [
            'title' => 'Geräte',
            'content' => render_template('device_index.php', [
                'devices' => array_values(R::findAll('device', ' ORDER BY name ')),
                'rooms' => $rooms,
                'roomLabels' => $roomLabels,
                'canManage' => current_user_can_manage_courses(),
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
        $device->inventory_number = trim((string) ($_POST['inventory_number'] ?? ''));
        $device->description = trim((string) ($_POST['description'] ?? ''));
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
