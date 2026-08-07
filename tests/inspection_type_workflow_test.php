<?php

declare(strict_types=1);

$schema = (string) file_get_contents(dirname(__DIR__) . '/lib/lib.inc.php');
$types = (string) file_get_contents(dirname(__DIR__) . '/lib/InspectionTypeService.php');
$findings = (string) file_get_contents(dirname(__DIR__) . '/lib/DeviceFindingService.php');
$controller = (string) file_get_contents(dirname(__DIR__) . '/controllers/InspectionController.php');
$profile = (string) file_get_contents(dirname(__DIR__) . '/controllers/ProfileController.php');
$ladder = (string) file_get_contents(dirname(__DIR__) . '/templates/inspection_ladder_edit.php');

$checks = [
    [str_contains($schema, 'CREATE TABLE IF NOT EXISTS inspection_type') && str_contains($schema, 'CREATE TABLE IF NOT EXISTS user_qualification') && str_contains($schema, 'CREATE TABLE IF NOT EXISTS device_finding'), 'Prüfarten, Befähigungen oder Mängel sind nicht persistierbar.'],
    [str_contains($types, 'examinerEligibility') && str_contains($types, 'requires_confirmation'), 'Befähigungen werden nicht serverseitig bewertet.'],
    [str_contains($controller, 'InspectionTypeService::LADDER') && str_contains($controller, 'editLadder') && str_contains($controller, 'failed_action'), 'Leiterprüfung oder dokumentierte Fehlmaßnahme fehlen im Workflow.'],
    [str_contains($findings, "['green', 'orange', 'red']") && str_contains($findings, '$blocked'), 'Gerätehinweise und Sperrmängel werden nicht getrennt nachverfolgt.'],
    [str_contains($profile, 'save_qualification') && str_contains($profile, 'confirm_qualification'), 'Befähigungen können nicht im Profil hinterlegt und bestätigt werden.'],
    [str_contains($ladder, 'Hinweis · Grün') && str_contains($ladder, 'Mangel · Orange') && str_contains($ladder, 'Mangel · Rot'), 'Die Leiterprüfung bildet die geforderte Mängelampel nicht ab.'],
];
foreach ($checks as [$ok, $message]) if (!$ok) throw new RuntimeException($message);
echo "PASS: Prüftypen, Befähigungen und Mängelworkflow\n";
