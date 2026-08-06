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
$admin = (string) file_get_contents(dirname(__DIR__) . '/controllers/AdminController.php');
$users = (string) file_get_contents(dirname(__DIR__) . '/templates/admin_user_list.php');
$index = (string) file_get_contents(dirname(__DIR__) . '/index.php');
$inspection = (string) file_get_contents(dirname(__DIR__) . '/controllers/InspectionController.php');
$device = (string) file_get_contents(dirname(__DIR__) . '/controllers/DeviceController.php');
foreach (['login_reminders', 'loginReminder'] as $needle) {
    if (!str_contains($layout, $needle)) throw new RuntimeException("Login-Hinweis fehlt im Layout: {$needle}");
}
if (!str_contains($lib, 'UserReminderService::afterLogin')) {
    throw new RuntimeException('Login-Hinweise werden nicht serverseitig nach der Anmeldung vorbereitet.');
}
if (!str_contains($service, 'Unterschrift im Profil ergänzen') || !str_contains($service, 'Offene Prüfdaten') || !str_contains($service, 'missingInspections') || !str_contains($service, 'array_slice(array_keys($grouped), 0, 10)') || !str_contains($service, 'weitere ') || !str_contains($service, 'self::markDedupeRead($dedupeKey, $userId)')) {
    throw new RuntimeException('Die beiden persönlichen Prüferhinweise fehlen.');
}
foreach (['loginAs', 'stopLoginAs', 'impersonator_user_id'] as $needle) {
    if (!str_contains($admin, $needle) && !str_contains($layout, $needle)) {
        throw new RuntimeException("Nutzeranmeldung fehlt: {$needle}");
    }
}
if (!str_contains($users, 'Als Nutzer/in anmelden') || !str_contains($index, "'/admin/nutzer/{id}/login-as'")) {
    throw new RuntimeException('Superadmin-Aktion für Nutzeranmeldung fehlt.');
}
if (!str_contains($admin, 'UserReminderService::afterLogin($target)') || !str_contains($admin, "'Location' => url_for()")) {
    throw new RuntimeException('Nutzeranmeldung führt nicht mit Login-Hinweisen zur Startseite.');
}
if (!str_contains($inspection, "result_status'] === 'open'")) {
    throw new RuntimeException('Der Link für offene Vortagsprüfungen besitzt keinen kombinierten Statusfilter.');
}
if (!str_contains($lib, 'function current_user_is_customer')
    || !str_contains($device, 'if (current_user_is_customer())')
    || !str_contains($inspection, 'if (current_user_is_customer())')
) {
    throw new RuntimeException('Offene Prüfungen werden für Admins nicht zuverlässig von Kundensicht getrennt.');
}

echo "PASS: Prüferhinweise werden rollenbezogen und serverseitig vorbereitet\n";
