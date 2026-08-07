<?php

declare(strict_types=1);

$lib = (string) file_get_contents(dirname(__DIR__) . '/lib/lib.inc.php');
$inspection = (string) file_get_contents(dirname(__DIR__) . '/controllers/InspectionController.php');
$inspectionTypes = (string) file_get_contents(dirname(__DIR__) . '/lib/InspectionTypeService.php');
$profile = (string) file_get_contents(dirname(__DIR__) . '/controllers/ProfileController.php');
$maintenance = (string) file_get_contents(dirname(__DIR__) . '/lib/MaintenanceJobHandler.php');
$worker = (string) file_get_contents(dirname(__DIR__) . '/bin/phoenix_sync_worker.php');
$users = (string) file_get_contents(dirname(__DIR__) . '/templates/admin_user_list.php');
$profileTemplate = (string) file_get_contents(dirname(__DIR__) . '/templates/profile.php');

$checks = [
    [str_contains($lib, 'function examiner_has_report_signature') && str_contains($lib, 'function inspection_report_signature_sql'), 'Die Signaturfreigabe ist nicht zentral im Backend definiert.'],
    [str_contains($inspection, 'InspectionTypeService::permissionForUser') && str_contains($inspectionTypes, 'examiner_has_report_signature') && str_contains($inspection, 'Der eingetragene Prüfer hat keine hinterlegte Unterschrift'), 'Prüfungen werden ohne Prüfer-Unterschrift nicht sicher gesperrt.'],
    [str_contains($maintenance, "inspection_report_signature_sql('inspection')") && str_contains($maintenance, 'examiner_has_report_signature'), 'Der Hintergrundlauf erzeugt Berichte trotz fehlender Signatur.'],
    [str_contains($worker, 'examiner_has_report_signature'), 'Die explizite Berichtsneuerzeugung prüft die Signatur nicht.'],
    [str_contains($profile, 'signature_drawing') && str_contains($profile, 'queueMissingReportsForExaminer'), 'Profil-Signaturen werden nicht als Zeichnung gespeichert oder lösen keine Berichte aus.'],
    [str_contains($profileTemplate, 'id="signature-pad"') && str_contains($profileTemplate, 'pointerdown'), 'Das Profil enthält keine nutzbare Signaturfläche.'],
    [str_contains($users, 'Unterschrift hinterlegt') && str_contains($users, 'Unterschrift fehlt'), 'Die Nutzerverwaltung zeigt den Signaturstatus nicht.'],
    [str_contains($inspectionTypes, 'examinerEligibility($user, $type)') && str_contains($inspectionTypes, 'Technischer Prüfzugang') && !str_contains($inspectionTypes, "current_user_is_superadmin()"), 'Administrative Rollen dürfen Prüfberechtigungen nicht automatisch ersetzen.'],
    [str_contains($inspectionTypes, "if (\$qualification === [])") && str_contains($inspectionTypes, "\$valid = empty(\$requirement['validity_days']) ? true : (\$expiry !== ''"), 'Fehlende oder abgelaufene Nachweise werden nicht zuverlässig gesperrt.'],
    [str_contains($lib, 'inspection_requirements_v1_activated') && str_contains($lib, "SET active = 1 WHERE code IN"), 'Die standardmäßigen Nachweisanforderungen werden nicht einmalig aktiviert.'],
];

foreach ($checks as [$ok, $message]) {
    if (!$ok) throw new RuntimeException($message);
}

echo "PASS: Examiner signatures gate inspections and report generation\n";
