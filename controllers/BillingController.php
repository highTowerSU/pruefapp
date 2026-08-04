<?php

declare(strict_types=1);

use RedBeanPHP\R;
use Ceneos\PhpBase\Tenant\TenantRepository;

final class BillingController
{
    public static function index(array $params, bool $isHx): array
    {
        if (!current_user_has_role('admin')) return forbidden_response();
        $rows = self::rows();
        $tenant = (new TenantRepository())->find((int) (get_branding()['company_id'] ?? 0));
        $configured = $tenant && trim((string) ($tenant->sevdesk_api_token ?? '')) !== '';
        $content = render_template('billing_index.php', ['rows' => $rows, 'tenant' => $tenant, 'configured' => $configured, 'message' => $_SESSION['billing_message'] ?? null]);
        unset($_SESSION['billing_message']);
        return [200, [], render_template('layout.php', ['title' => 'Abrechnung', 'content' => $content])];
    }

    public static function export(array $params, bool $isHx): array
    {
        if (!current_user_has_role('admin')) return forbidden_response();
        $ids = array_values(array_filter(array_map('intval', (array) ($_POST['inspection_ids'] ?? [])), static fn(int $id): bool => $id > 0));
        $rows = self::rows($ids);
        if ($rows === []) { $_SESSION['billing_message'] = 'Bitte mindestens eine nicht exportierte, abrechenbare Prüfung auswählen.'; return [303, ['Location' => url_for('admin/abrechnung')], '']; }
        $action = (string) ($_POST['action'] ?? 'csv');
        if ($action === 'csv') return self::csv($rows);
        if ($action === 'mark') {
            foreach ($rows as $row) R::exec('UPDATE inspection SET billing_exported_at = ?, billing_export_id = ? WHERE id = ?', [date(DATE_ATOM), 'manual', $row['id']]);
            $_SESSION['billing_message'] = count($rows) . ' Prüfung(en) als exportiert markiert.';
        } elseif ($action === 'sevdesk') {
            $tenant = (new TenantRepository())->find((int) (get_branding()['company_id'] ?? 0));
            $client = new SevDeskClient((string) ($tenant->sevdesk_api_url ?? 'https://my.sevdesk.de/api/v1'), (string) ($tenant->sevdesk_api_token ?? ''));
            $byCustomer = [];
            foreach ($rows as $row) $byCustomer[(string) $row['sevdesk_customer_id']][] = $row;
            foreach ($byCustomer as $customerId => $items) {
                if ($customerId === '') throw new RuntimeException('Kundenverknüpfung zu SevDesk fehlt: ' . $items[0]['customer_name']);
                $response = $client->createDraftInvoice($customerId, 'PR-' . date('Ymd-His'), date('Y-m-d'), $items, (float) ($tenant->sevdesk_inspection_rate ?? 0), (float) ($tenant->sevdesk_regie_rate ?? 0));
                $exportId = (string) ($response['objects']['id'] ?? $response['id'] ?? 'sevdesk');
                foreach ($items as $row) R::exec('UPDATE inspection SET billing_exported_at = ?, billing_export_id = ? WHERE id = ?', [date(DATE_ATOM), $exportId, $row['id']]);
            }
            $_SESSION['billing_message'] = count($rows) . ' Prüfung(en) an SevDesk übertragen.';
        }
        return [303, ['Location' => url_for('admin/abrechnung')], ''];
    }

    /** @return list<array<string,mixed>> */
    private static function rows(array $ids = []): array
    {
        $where = "i.billable = 1 AND (i.billing_exported_at IS NULL OR i.billing_exported_at = '') AND i.result_status IN ('bestanden','durchgefallen') AND TRIM(COALESCE(i.report_path, '')) <> ''";
        $args = [];
        if ($ids !== []) { $where .= ' AND i.id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')'; $args = $ids; }
        return R::getAll("SELECT i.id, i.external_number, i.test_date, i.next_due_date, i.result_status, i.regie_minutes, i.regie_reason, d.external_number AS device_number, d.name AS device_name, c.id AS customer_id, c.name AS customer_name, c.sevdesk_customer_id, s.name AS site_name, b.name AS building_name, f.name AS floor_name, r.number AS room_number FROM inspection i JOIN device d ON d.id=i.device_id LEFT JOIN room r ON r.id=d.room_id LEFT JOIN floor f ON f.id=r.floor_id LEFT JOIN building b ON b.id=f.building_id LEFT JOIN site s ON s.id=b.site_id LEFT JOIN customer c ON c.id=s.customer_id WHERE {$where} ORDER BY c.name, i.test_date, i.id", $args);
    }

    private static function csv(array $rows): array
    {
        $filename = 'abrechnung-' . date('Y-m-d') . '.csv';
        header('Content-Type: text/csv; charset=utf-8'); header('Content-Disposition: attachment; filename="' . $filename . '"');
        $out = fopen('php://output', 'wb'); fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['Prüfung', 'Datum', 'Kunde', 'Gerät', 'Raum', 'Ergebnis', 'Regiezeit (Min.)', 'Regiebegründung', 'SevDesk-Kunde'], ';');
        foreach ($rows as $row) fputcsv($out, [$row['external_number'], $row['test_date'], $row['customer_name'], $row['device_number'] . ' · ' . $row['device_name'], trim(implode(' · ', array_filter([$row['site_name'], $row['building_name'], $row['floor_name'], $row['room_number']]))) ?: '—', $row['result_status'], $row['regie_minutes'], $row['regie_reason'], $row['sevdesk_customer_id'] ?: 'FEHLT'], ';');
        fclose($out); return [200, [], ''];
    }
}
