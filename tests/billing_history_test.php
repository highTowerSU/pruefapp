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
$maintenance = (string) file_get_contents(dirname(__DIR__) . '/lib/MaintenanceJobHandler.php');
$cron = (string) file_get_contents(dirname(__DIR__) . '/bin/cron.php');

$checks = [
    [str_contains($schema, 'inspectionbillingbackup') && str_contains($schema, 'public_id') && str_contains($schema, 'billinginvoiceposition'), 'Die gesicherte Historienmigration oder Positionsspeicherung fehlt.'],
    [str_contains($reports, 'inspectionBillingCsv') && str_contains($reports, "'pruefung_id'") && str_contains($reports, "'regiezeit_minuten'") && str_contains($reports, "'abrechnungs_batch_id'"), 'Der vollständige Prüfungs-CSV enthält nicht die erforderlichen Abrechnungsfelder.'],
    [str_contains($reports, 'WHERE i.device_id IN') && str_contains($reports, "ORDER BY i.test_date, i.id"), 'Der CSV exportiert keine vollständige, stabile Prüfungshistorie.'],
    [str_contains($inspectionController, "public_id = 'prf_'") && str_contains($importer, "public_id = 'prf_'"), 'Neue manuelle oder importierte Prüfungen erhalten keine unveränderliche Prüf-ID.'],
    [str_contains($client, 'function invoicesByNumbers') && str_contains($client, 'function invoicePositions'), 'Der SevDesk-Client kann Rechnungen und Positionen nicht lesend abgleichen.'],
    [str_contains($billing, 'syncSevDeskInvoices') && str_contains($billing, 'Zuordnungen bleiben bis zur Bestätigung unverbindlich'), 'Der historische SevDesk-Import ist nicht bewusst bestätigungspflichtig.'],
    [str_contains($billing, "\$invoice->tenant_id = (int) (get_branding()['company_id'] ?? 0)") && str_contains($billing, 'sevdesk_invoice_number IN'), 'Eingelesene SevDesk-Rechnungen werden nicht mandantensicher in der Übersicht geführt.'],
    [str_contains($billing, 'Bitte für diese Rechnungsposition Geräte, Regie oder Sonstige festlegen.') && str_contains($billing, "self::invoice(['id' => \$invoiceId], true)"), 'Die Positionsklassifizierung liefert keine verständliche HTMX-Rückmeldung.'],
    [str_contains($detail, 'Vorschlag – bitte prüfen') && str_contains($detail, 'hx-target="#billing-reconciliation"'), 'Die Positionsvorschläge oder die Teilaktualisierung des Abgleichs fehlen.'],
    [str_contains($billing, 'reconciliation') && str_contains($billing, 'Geräteanzahl abweichend') && str_contains($billing, 'Regiezeit abweichend') && str_contains($billing, 'vollständig passend'), 'Der Rechnungsabgleich deckt die geforderten Ergebnisse nicht ab.'],
    [str_contains($billing, "(string) \$item->kind !== 'other'") && str_contains($billing, 'round((float) $item->quantity * 3)') && str_contains($billing, 'round((float) $item->quantity * 60)') && str_contains($billing, "preg_match('/min(?:ute)?/i'"), 'Historische Regiezeiten werden nicht einheitlich in Minuten normalisiert.'],
    [str_contains($billing, "'1' => 'Stk.'") && str_contains($billing, "'9' => 'h'") && str_contains($detail, 'displaySevDeskUnit'), 'SevDesk-Einheiten werden nicht lesbar dargestellt.'],
    [str_contains($billing, 'assignHistorical') && str_contains($billing, 'historical_confirmed') && str_contains($routes, '/historisch-zuordnen'), 'Die bestätigte historische Zuordnung fehlt.'],
    [str_contains($billing, 'assignHistoricalBatch') && str_contains($billing, 'historical_batch_confirmed') && str_contains($routes, '/historisch-zuordnen-sammeln'), 'Die bestätigte Sammelzuordnung historischer Prüfungen fehlt.'],
    [str_contains($billing, 'historical_batch_regie_allocated') && str_contains($billing, 'round((float) $item->quantity * 3)') && str_contains($detail, 'Zusätzliche bestätigte Regie'), 'Historische Rechnungsregie kann nicht nachvollziehbar im Sammelabgleich zugeordnet werden.'],
    [str_contains($billing, 'billed_regie_minutes') && str_contains($detail, 'Regie aus Import') && str_contains($detail, 'Es wird nichts aus der Rechnung auf Prüfungen verteilt.'), 'Historische Rechnungsregie wird nicht sauber von importierter Prüfungsregie getrennt.'],
    [str_contains($billing, 'effectivePositionKind') && str_contains($detail, 'SevDesk-Positionen') && str_contains($detail, 'Min. Regie'), 'Importierte Regiepositionen sind vor der separaten Positionsbestätigung nicht sichtbar.'],
    [str_contains($importer, "['Regiezeit', 'Regiezeit (Min.)', 'Regiezeit Minuten']") && str_contains($importer, 'ods_regiezeit') && str_contains($importer, 'normalizeRegieMinutes'), 'Die Regiezeit aus gekoppelten Phoenix-/ODS-Importen wird nicht übernommen und gesichert.'],
    [str_contains($maintenance, 'allReportRegeneration') && str_contains($cron, 'benning-import-regie:v1') && str_contains($cron, 'all-reports-after-benning-regie:v1') && str_contains($cron, 'benning_import_regie_reimport_version') && str_contains($cron, 'benning_import_regie_reports_version'), 'Der Regie-Reimport stößt keine fortsetzbare Neuerzeugung aller aktuellen Prüfberichte an.'],
    [str_contains($billing, 'historicalSuggestion') && str_contains($detail, 'Zuordnungsvorschlag prüfen') && str_contains($detail, 'Auswahl gesammelt zuordnen'), 'Ein prüfbarer Sammelvorschlag für historische Rechnungen fehlt.'],
    [str_contains($billing, 'sevdesk_customer_id=? OR sevdesk_customer_number=?') && str_contains($billing, 'remote_address_name') && str_contains($billing, 'suggestion_reason'), 'Die SevDesk-Kundenverknüpfung oder ihre Debug-Diagnose ist nicht robust genug.'],
    [str_contains($billing, 'contactById($remoteCustomerId)') && str_contains($billing, "['parent']['id']"), 'SevDesk-Ansprechpersonen werden nicht bis zum Hauptkunden aufgelöst.'],
    [str_contains($billing, 'sevDeskInvoiceCustomerId') && str_contains($billing, 'parent_customer_id'), 'Unterkunden werden beim SevDesk-Export nicht sauber gegen den Hauptkunden aufgelöst.'],
    [str_contains($billing, 'normalizedCustomerLabel') && str_contains($billing, 'raw_unity'), 'Rechnungsempfänger mit Kontaktpräfix oder SevDesk-Einheiten sind nicht diagnostizierbar.'],
    [str_contains($billing, 'releaseCancelledInvoice') && str_contains($billing, 'SevDesk-Rechnung wurde storniert'), 'Stornierte SevDesk-Rechnungen geben Prüfungen nicht nachvollziehbar frei.'],
    [str_contains($billing, 'classifyPosition') && str_contains($detail, 'positionen-klassifizieren'), 'Rechnungspositionen können nicht überprüfbar klassifiziert werden.'],
    [str_contains($billing, 'classifyPositions') && str_contains($routes, '/positionen-klassifizieren') && str_contains($detail, 'Alle Positionen übernehmen'), 'Rechnungspositionen können nicht gesammelt per HTMX gespeichert werden.'],
];

foreach ($checks as [$ok, $message]) {
    if (!$ok) throw new RuntimeException($message);
}

echo "PASS: billing history CSV, secure migration and SevDesk reconciliation are present\n";
