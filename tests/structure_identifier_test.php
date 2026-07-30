<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/controllers/StructureController.php';

use RedBeanPHP\R;

function current_user_can_manage_courses(): bool
{
    return true;
}

function url_for(string $path): string
{
    return '/' . ltrim($path, '/');
}

function audit_log(string $event, array $data = []): void
{
}

R::setup('sqlite::memory:');

$metadataMethod = new ReflectionMethod(StructureController::class, 'metadata');
$metadataMethod->setAccessible(true);
if ($metadataMethod->invoke(null, '') !== '{}' || $metadataMethod->invoke(null, '{}') !== '{}') {
    throw new RuntimeException('Leere Metadaten und ein leeres JSON-Objekt müssen akzeptiert werden.');
}

$makeHierarchy = static function (string $buildingCode, string $pattern = 'auto'): array {
    $customer = R::dispense('customer');
    $customer->name = 'Testkunde';
    $customer->room_code_pattern = $pattern;
    R::store($customer);
    $site = R::dispense('site');
    $site->name = 'Standort';
    $site->customer_id = $customer->id;
    R::store($site);
    $building = R::dispense('building');
    $building->name = 'Gebäude';
    $building->code = $buildingCode;
    $building->site_id = $site->id;
    R::store($building);
    return [$customer, $site, $building];
};

[, , $building] = $makeHierarchy('AB');
$floor = R::dispense('floor');
$floor->building_id = $building->id;
$floor->code = '1';
R::store($floor);
$room = R::dispense('room');
$room->number = '24';
if (StructureController::roomIdentifier($room, $floor) !== '1.24') throw new RuntimeException('1.24 wurde nicht gebildet.');

$floor->code = 'E';
$area = R::dispense('area');
$area->code = 'E';
$area->floor_id = $floor->id;
R::store($area);
$room->number = '10';
if (StructureController::roomIdentifier($room, $floor, $area) !== 'E10') throw new RuntimeException('E10 wurde nicht gebildet.');

[, , $buildingN] = $makeHierarchy('N');
$floorU = R::dispense('floor');
$floorU->building_id = $buildingN->id;
$floorU->code = 'U';
R::store($floorU);
$room->number = '07';
if (StructureController::roomIdentifier($room, $floorU) !== 'NU07') throw new RuntimeException('NU07 wurde nicht gebildet.');

[, , $buildingK] = $makeHierarchy('K', '{building}{floor}{room}');
$floorK = R::dispense('floor');
$floorK->building_id = $buildingK->id;
$floorK->code = '1';
R::store($floorK);
$room->number = '81';
if (StructureController::roomIdentifier($room, $floorK) !== 'K181') throw new RuntimeException('K181 wurde nicht gebildet.');

$parent = R::dispense('customer');
$parent->name = 'Mutterkunde';
$parent->room_code_pattern = '{building}{floor}{room}';
R::store($parent);
$child = R::dispense('customer');
$child->name = 'Tochterkunde';
$child->parent_customer_id = $parent->id;
$child->room_code_pattern = 'auto';
R::store($child);
$inheritedSite = R::dispense('site');
$inheritedSite->name = 'Standort Tochter';
$inheritedSite->customer_id = $child->id;
R::store($inheritedSite);
$buildingN = R::dispense('building');
$buildingN->name = 'Haus Malta';
$buildingN->code = 'N';
$buildingN->site_id = $inheritedSite->id;
R::store($buildingN);
$legacyFloor = R::dispense('floor');
$legacyFloor->name = 'N1';
$legacyFloor->code = '';
$legacyFloor->building_id = $buildingN->id;
R::store($legacyFloor);
$room->number = '81';
if (StructureController::roomIdentifier($room, $legacyFloor) !== 'N181') {
    throw new RuntimeException('Das Raumkennzeichen des Oberkunden muss auf den Unterkunden vererbt werden.');
}

$customerCount = R::count('customer');
$_POST = [
    'name' => 'Neuer Hauptkunde',
    'parent_customer_id' => '0',
    'metadata_json' => '',
    'room_code_pattern' => '',
];
$saveMethod = new ReflectionMethod(StructureController::class, 'save');
$saveMethod->setAccessible(true);
$response = $saveMethod->invoke(null, 'customer');
if (($response[0] ?? 0) !== 303 || R::count('customer') !== $customerCount + 1) {
    throw new RuntimeException('Ein neuer Kunde ohne übergeordneten Kunden muss gespeichert werden können.');
}

R::close();
