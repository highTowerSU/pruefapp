<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/controllers/AdminController.php';

$assignments = AdminController::normalizeCustomerAssignments(
    [1, 2, 3, 4],
    [1, 2],
    [1 => 0, 2 => 1, 3 => 2, 4 => 0]
);
if ($assignments !== [1 => true, 4 => false]) {
    throw new RuntimeException('Unterkundenzuordnungen werden serverseitig nicht kanonisiert.');
}

$selectiveAssignments = AdminController::normalizeCustomerAssignments(
    [1, 2],
    [],
    [1 => 0, 2 => 1]
);
if ($selectiveAssignments !== [1 => false, 2 => false]) {
    throw new RuntimeException('Einzelne Unterkunden sind ohne Vererbung nicht mehr auswählbar.');
}

$template = (string) file_get_contents(dirname(__DIR__) . '/templates/admin_user_list.php');
 $controller = (string) file_get_contents(dirname(__DIR__) . '/controllers/AdminController.php');
if (!str_contains($template, 'data-customer-access-item') || !str_contains($template, 'coveredByParent')) {
    throw new RuntimeException('Die Nutzeroberfläche deaktiviert abgedeckte Unterkunden nicht.');
}
if (!str_contains($template, 'Prüferlaubnis') || !str_contains($template, 'inspection_permissions') || !str_contains($controller, 'requirementsByType')) {
    throw new RuntimeException('Die Benutzerverwaltung zeigt die serverseitig ermittelte Prüferlaubnis nicht an.');
}

echo "PASS: Customer access hierarchy is canonicalized in UI and backend\n";
