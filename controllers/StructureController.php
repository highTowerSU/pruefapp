<?php

declare(strict_types=1);

use RedBeanPHP\R as R;

class StructureController
{
    public static function index(array $params, bool $isHx): array
    {
        if (!current_user()) {
            return [303, ['Location' => url_for('login.php')], ''];
        }

        $content = render_template('structure_index.php', [
            'customers' => self::customers(),
            'sites' => self::entities('site'),
            'buildings' => self::entities('building'),
            'floors' => self::entities('floor'),
            'rooms' => self::entities('room'),
            'devices' => self::entities('device'),
            'canManage' => current_user_can_manage_courses(),
        ]);

        $body = render_template('layout.php', [
            'title' => 'Kundenstruktur',
            'content' => $content,
        ]);

        return [200, [], $body];
    }

    public static function createCustomer(array $params, bool $isHx): array
    {
        if (!current_user_can_manage_courses()) {
            return forbidden_response();
        }

        $name = trim((string) ($_POST['name'] ?? ''));
        if ($name === '') {
            $_SESSION['fehlermeldung'] = 'Bitte einen Kundennamen angeben.';
            return [303, ['Location' => url_for('struktur')], ''];
        }

        $parentId = (int) ($_POST['parent_customer_id'] ?? 0);
        $customer = R::dispense('customer');
        $customer->name = $name;
        if ($parentId > 0) {
            $parent = R::load('customer', $parentId);
            if ($parent->id) {
                $customer->parent_customer_id = (int) $parent->id;
            }
        }

        R::store($customer);
        $_SESSION['meldung'] = 'Kunde gespeichert.';

        return [303, ['Location' => url_for('struktur')], ''];
    }

    public static function createSite(array $params, bool $isHx): array
    {
        return self::createChildEntity('site', 'customer_id', 'Bitte einen Standortnamen angeben.');
    }

    public static function createBuilding(array $params, bool $isHx): array
    {
        return self::createChildEntity('building', 'site_id', 'Bitte einen Gebäudenamen angeben.');
    }

    public static function createFloor(array $params, bool $isHx): array
    {
        return self::createChildEntity('floor', 'building_id', 'Bitte eine Etagenbezeichnung angeben.');
    }

    public static function createRoom(array $params, bool $isHx): array
    {
        return self::createChildEntity('room', 'floor_id', 'Bitte eine Raumnummer/-bezeichnung angeben.');
    }

    public static function createDevice(array $params, bool $isHx): array
    {
        return self::createChildEntity('device', 'room_id', 'Bitte eine Gerätebezeichnung angeben.');
    }

    private static function createChildEntity(string $type, string $parentField, string $errorMessage): array
    {
        if (!current_user_can_manage_courses()) {
            return forbidden_response();
        }

        $name = trim((string) ($_POST['name'] ?? ''));
        $parentId = (int) ($_POST[$parentField] ?? 0);

        if ($name === '' || $parentId <= 0) {
            $_SESSION['fehlermeldung'] = $errorMessage;
            return [303, ['Location' => url_for('struktur')], ''];
        }

        $entity = R::dispense($type);
        $entity->name = $name;
        $entity->{$parentField} = $parentId;
        R::store($entity);

        $_SESSION['meldung'] = 'Eintrag gespeichert.';

        return [303, ['Location' => url_for('struktur')], ''];
    }

    private static function customers(): array
    {
        return array_values(R::findAll('customer', ' ORDER BY name '));
    }

    private static function entities(string $table): array
    {
        return array_values(R::findAll($table, ' ORDER BY name '));
    }
}
