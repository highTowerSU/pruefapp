<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/lib/InspectionEvaluationService.php';
require_once dirname(__DIR__) . '/lib/UserReminderService.php';

foreach (['editor' => true, 'admin' => true, 'superadmin' => true, 'user' => false, 'customer' => false] as $role => $expected) {
    $user = (object) ['role' => $role];
    if (UserReminderService::canPerformInspections($user) !== $expected) {
        throw new RuntimeException("Prüferbefähigung für Rolle {$role} ist falsch.");
    }
}

$layout = (string) file_get_contents(dirname(__DIR__) . '/templates/layout.php');
$lib = (string) file_get_contents(dirname(__DIR__) . '/lib/lib.inc.php');
$service = (string) file_get_contents(dirname(__DIR__) . '/lib/UserReminderService.php');
$inspection = (string) file_get_contents(dirname(__DIR__) . '/controllers/InspectionController.php');
foreach (['login_reminders', 'loginReminder'] as $needle) {
    if (!str_contains($layout, $needle)) throw new RuntimeException("Login-Hinweis fehlt im Layout: {$needle}");
}
if (!str_contains($lib, 'UserReminderService::afterLogin')) {
    throw new RuntimeException('Login-Hinweise werden nicht serverseitig nach der Anmeldung vorbereitet.');
}
if (!str_contains($service, 'Unterschrift im Profil ergänzen') || !str_contains($service, 'Prüfdaten vom Vortag fehlen')) {
    throw new RuntimeException('Die beiden persönlichen Prüferhinweise fehlen.');
}
if (!str_contains($inspection, "result_status'] === 'open'")) {
    throw new RuntimeException('Der Link für offene Vortagsprüfungen besitzt keinen kombinierten Statusfilter.');
}

echo "PASS: Prüferhinweise werden rollenbezogen und serverseitig vorbereitet\n";
