<?php

declare(strict_types=1);

$schema = (string) file_get_contents(dirname(__DIR__) . '/lib/lib.inc.php');
$billing = (string) file_get_contents(dirname(__DIR__) . '/controllers/BillingController.php');
$reports = (string) file_get_contents(dirname(__DIR__) . '/controllers/ReportController.php');
$inspectionController = (string) file_get_contents(dirname(__DIR__) . '/controllers/InspectionController.php');
$importer = (string) file_get_contents(dirname(__DIR__) . '/lib/ElectricalInspectionImportService.php');
$routes = (string) file_get_contents(dirname(__DIR__) . '/index.php');
$detail = (string) file_get_contents(dirname(__DIR__) . '/templates/billing_invoice.php');
$client = (string) file_get_contents(dirname(__DIR__, 2) . '/ceneos-php-base/src/Integration/SevDeskClient.php');

$checks = [
    [str_contains($schema, 'inspectionbillingbackup') && str_contains($schema, 'public_id') && str_contains($schema, 'billinginvoiceposition'), 'Die gesicherte Historienmigration oder Positionsspeicherung fehlt.'],
    [str_contains($reports, 'inspectionBillingCsv') && str_contains($reports, "'pruefung_id'") && str_contains($reports, "'regiezeit_minuten'") && str_contains($reports, "'abrechnungs_batch_id'"), 'Der vollständige Prüfungs-CSV enthält nicht die erforderlichen Abrechnungsfelder.'],
    [str_contains($reports, 'WHERE i.device_id IN') && str_contains($reports, "ORDER BY i.test_date, i.id"), 'Der CSV exportiert keine vollständige, stabile Prüfungshistorie.'],
    [str_contains($inspectionController, "public_id = 'prf_'") && str_contains($importer, "public_id = 'prf_'"), 'Neue manuelle oder importierte Prüfungen erhalten keine unveränderliche Prüf-ID.'],
    [str_contains($client, 'function invoicesByNumbers') && str_contains($client, 'function invoicePositions'), 'Der SevDesk-Client kann Rechnungen und Positionen nicht lesend abgleichen.'],
    [str_contains($billing, 'syncSevDeskInvoices') && str_contains($billing, 'Zuordnungen bleiben bis zur Bestätigung unverbindlich'), 'Der historische SevDesk-Import ist nicht bewusst bestätigungspflichtig.'],
    [str_contains($billing, 'reconciliation') && str_contains($billing, 'Geräteanzahl abweichend') && str_contains($billing, 'Regiezeit abweichend') && str_contains($billing, 'vollständig passend'), 'Der Rechnungsabgleich deckt die geforderten Ergebnisse nicht ab.'],
    [str_contains($billing, 'round((float) $item->quantity * 60)') && str_contains($billing, "preg_match('/min(?:ute)?/i'"), 'Historische Regiezeiten werden nicht einheitlich in Minuten normalisiert.'],
    [str_contains($billing, 'assignHistorical') && str_contains($billing, 'historical_confirmed') && str_contains($routes, '/historisch-zuordnen'), 'Die bestätigte historische Zuordnung fehlt.'],
    [str_contains($billing, 'classifyPosition') && str_contains($detail, 'position-klassifizieren'), 'Rechnungspositionen können nicht überprüfbar klassifiziert werden.'],
];

foreach ($checks as [$ok, $message]) {
    if (!$ok) throw new RuntimeException($message);
}

echo "PASS: billing history CSV, secure migration and SevDesk reconciliation are present\n";
