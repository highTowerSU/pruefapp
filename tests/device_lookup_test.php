<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/controllers/DeviceController.php';

use RedBeanPHP\R;

function current_user(): object
{
    return (object) ['id' => 1];
}

function current_user_has_role(string ...$roles): bool
{
    return in_array('admin', $roles, true);
}

function url_for(string $path): string
{
    return '/' . ltrim($path, '/');
}

R::setup('sqlite::memory:');
R::exec('CREATE TABLE device (id INTEGER PRIMARY KEY, external_number TEXT NOT NULL, legacy_number TEXT NOT NULL, name TEXT NOT NULL, manufacturer TEXT NOT NULL, device_model TEXT NOT NULL, inventory_number TEXT NOT NULL)');
R::exec('CREATE INDEX idx_device_external_number ON device (external_number)');
R::exec('CREATE INDEX idx_device_legacy_number ON device (legacy_number)');
R::exec("INSERT INTO device (external_number, legacy_number, name, manufacturer, device_model, inventory_number) VALUES ('01234567', '76543210', 'Testgerät', 'Ceneos', 'Modell X', 'INV-1')");

foreach ([
    '01234567' => true,
    '76543210' => true,
    '00000000' => false,
] as $number => $expectedFound) {
    $_GET['number'] = $number;
    [$status, $headers, $body] = DeviceController::lookup([], false);
    $result = json_decode((string) $body, true);
    if ($status !== 200 || ($result['found'] ?? null) !== $expectedFound) {
        throw new RuntimeException('Die Suche nach Geräte- oder Altnummer lieferte ein falsches Ergebnis.');
    }
    if ($expectedFound && (($result['manufacturer'] ?? '') !== 'Ceneos' || ($result['model'] ?? '') !== 'Modell X' || ($result['inventory_number'] ?? '') !== 'INV-1')) {
        throw new RuntimeException('Die Gerätesuche enthält nicht die Stammdaten für die Prüfungsauswahl.');
    }
    if (!str_contains((string) ($headers['Server-Timing'] ?? ''), 'db;dur=')) {
        throw new RuntimeException('Die Suchdauer fehlt im Server-Timing-Header.');
    }
}

$plan = implode(' ', array_column(R::getAll("EXPLAIN QUERY PLAN SELECT * FROM device WHERE external_number = '01234567' OR legacy_number = '01234567' LIMIT 1"), 'detail'));
if (!str_contains($plan, 'idx_device_external_number') || !str_contains($plan, 'idx_device_legacy_number')) {
    throw new RuntimeException('Die Datenbank verwendet nicht beide Nummern-Indizes.');
}

echo "PASS: Barcode-Suche nutzt beide Nummern-Indizes und meldet ihre Laufzeit\n";
