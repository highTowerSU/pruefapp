<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/controllers/StructureController.php';

use RedBeanPHP\R;

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

R::close();
