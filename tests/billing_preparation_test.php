<?php
declare(strict_types=1);

namespace Ceneos\PhpBase\Tenant {
    final class TenantRepository
    {
        public function find(int $id): object
        {
            return (object) ['id' => $id, 'sevdesk_inspection_rate' => 6, 'sevdesk_regie_rate' => 0, 'sevdesk_tax_rate' => 19, 'sevdesk_tax_rule' => 1];
        }
        public function sevDeskToken(int $id): string
        {
            return 'isolated-test-token';
        }
    }
}
namespace {
    use RedBeanPHP\R;
    require_once dirname(__DIR__) . '/vendor/autoload.php';
    require_once dirname(__DIR__) . '/controllers/BillingController.php';
    require_once dirname(__DIR__) . '/lib/InspectionEvaluationService.php';
    require_once dirname(__DIR__) . '/lib/InspectionFilterService.php';

    function current_user_can_manage_billing(): bool { return true; }
    function current_user_is_superadmin(): bool { return false; }
    function get_branding(): array { return ['company_id' => 2, 'key' => 'ceneos', 'url_prefix' => 'ceneos']; }
    function public_base_url_for_tenant($id): string { return 'https://pruef.example.test'; }
    function display_examiner_name($name): string { return $name; }
    function audit_log($action, $details): void {}
    function current_user(): object { return (object) ['id' => 1, 'name' => 'Isolated test']; }
    final class SevDeskClient
    {
        public static int $calls = 0;
        public function __construct(...$args) {}
        public function createDraftInvoice(...$args): array
        {
            self::$calls++;
            ensurePreparation(count($args[3]) === 140, 'Wrong number of exported inspections');
            return ['objects' => ['id' => '12345', 'invoiceNumber' => 'RE-TEST']];
        }
    }
    function url_for(string $path = ''): string
    {
        if (str_starts_with($path, 'admin/abrechnung') && !CentralBillingModule::isPreparationPath($path)) return CentralBillingModule::url($path);
        return '/' . $path;
    }
    function render_template($file, $vars = []): string
    {
        if ($file === 'layout.php') return $vars['content'];
        extract($vars);
        ob_start();
        require dirname(__DIR__) . '/templates/' . $file;
        return ob_get_clean();
    }
    final class StructureController
    {
        public static function roomIdentifier($room, $floor, $area = null): string { return 'TEST'; }
    }
    function ensurePreparation(bool $condition, string $message): void
    {
        if (!$condition) throw new \RuntimeException($message);
    }
    R::setup('sqlite::memory:');
    $source = file_get_contents(CentralBillingModule::root() . '/modules/pruef/BillingController.php');
    $map = ['i' => 'inspection', 'd' => 'device', 'r' => 'room', 'f' => 'floor', 'b' => 'building', 's' => 'site', 'c' => 'customer', 'bi' => 'billinginvoiceitem', 'inv' => 'billinginvoice', 'p' => 'billinginvoiceposition'];
    foreach ($map as $alias => $table) {
        preg_match_all('/\\b' . $alias . '\\.([a-z_]+)/', $source, $matches);
        $fields = array_unique(array_merge($matches[1], ['name', 'sort_order', 'area_id', 'parent_customer_id', 'sevdesk_api_url', 'inspection_id', 'active', 'duplicate_of_inspection_id', 'archived_at', 'classification', 'status', 'billable', 'billing_not_billable_comment', 'updated_at', 'billing_exported_at', 'billing_export_id', 'billing_exported_by']));
        $fields = array_diff($fields, ['id']);
        R::exec('CREATE TABLE ' . $table . ' (id INTEGER PRIMARY KEY,' . implode(',', array_map(static fn(string $field): string => $field . (str_ends_with($field, '_id') ? ' INTEGER DEFAULT NULL' : " TEXT DEFAULT ''"), $fields)) . ')');
    }
    R::exec("INSERT INTO customer(id,name,tenant_id,sevdesk_customer_id) VALUES(3,'AK',2,'123'),(4,'Foreign',99,'999')");
    R::exec("INSERT INTO site(id,customer_id) VALUES(1,3),(2,4)");
    R::exec("INSERT INTO building(id,site_id) VALUES(1,1),(2,2)");
    R::exec("INSERT INTO floor(id,building_id) VALUES(1,1),(2,2)");
    R::exec("INSERT INTO room(id,floor_id) VALUES(1,1),(2,2)");
    R::exec("INSERT INTO device(id,room_id,name) VALUES(1,1,'Device'),(2,2,'Foreign')");
    for ($id = 1; $id <= 202; $id++) {
        R::exec("INSERT INTO inspection(id,device_id,test_date,result_status,status,billing_eligibility,billing_status,classification,external_number) VALUES(?,?,'2026-08-01','passed','completed','not_billable','not_exported','native',?)", [$id, $id === 201 ? 2 : 1, 'TEST-' . $id]);
    }
    R::exec("UPDATE inspection SET archived_at='2026-11-15',duplicate_of_inspection_id=1 WHERE id=202");
    $_GET = [];
    $_POST = [];
    $_SESSION = [];
    $response = BillingController::index([], true);
    ensurePreparation($response[0] === 200 && str_contains($response[2], 'Abrechnung vorbereiten'), 'Preparation missing');
    $doc = new \DOMDocument();
    @$doc->loadHTML('<?xml encoding="UTF-8">' . $response[2]);
    $xpath = new \DOMXPath($doc);
    ensurePreparation($xpath->query('//form[@id="billing-actions-panel"]//input[contains(@class,"billing-check")]')->length === 50, 'Selection form lost rows due to nested forms');
    ensurePreparation($xpath->query('//form[@id="billing-actions-panel"]//form')->length === 0, 'Nested forms');
    ensurePreparation($xpath->query('//form[@id="billing-actions-panel"]/input[@name="billing_csrf"]')->length === 1, 'CSRF token missing');
    ensurePreparation(!str_contains($response[2], 'Foreign'), 'Foreign tenant leaked');
    $_POST = ['action' => 'set_billable', 'inspection_ids' => [1]];
    ensurePreparation(BillingController::export([], false)[0] === 403, 'CSRF not enforced');
    $_POST['billing_csrf'] = $_SESSION['billing_csrf'];
    $_POST['inspection_ids'] = [201];
    ensurePreparation(BillingController::export([], false)[0] === 403, 'Foreign tenant mutation allowed');
    $_POST['inspection_ids'] = [202];
    ensurePreparation(BillingController::export([], false)[0] === 403, 'Archived duplicate released');
    $_POST['inspection_ids'] = range(1, 200);
    BillingController::export([], false);
    ensurePreparation((int) R::getCell("SELECT COUNT(*) FROM inspection WHERE billing_eligibility='billable'") === 200, 'Explicit selected release failed');
    $_GET = ['suggest_batch' => '1'];
    $response = BillingController::index([], true);
    ensurePreparation(str_contains($response[2], '999,60 €') && str_contains($response[2], '140 Prüfungen'), 'Gross preview missing');
    $_POST = ['action' => 'sevdesk', 'inspection_ids' => [], 'billing_csrf' => $_SESSION['billing_csrf']];
    ensurePreparation(BillingController::export([], false)[0] === 303, 'Empty selection must not create invoices');
    ensurePreparation(SevDeskClient::$calls === 0, 'Preview or empty selection called SevDesk');
    @$doc->loadHTML('<?xml encoding="UTF-8">' . $response[2]);
    $batchDocument = new \DOMDocument();
    $_GET = ['suggest_batch' => '1'];
    $preview = BillingController::index([], true);
    @$batchDocument->loadHTML('<?xml encoding="UTF-8">' . $preview[2]);
    $batchXPath = new \DOMXPath($batchDocument);
    $fingerprint = $batchXPath->evaluate('string(//input[@name="batch_fingerprint"]/@value)');
    $_POST = ['action' => 'sevdesk', 'inspection_ids' => range(1, 140), 'selection_scope' => 'selection', 'billing_csrf' => $_SESSION['billing_csrf'], 'batch_min' => '500', 'batch_max' => '1000', 'batch_fingerprint' => $fingerprint];
    $created = BillingController::export([], false);
    ensurePreparation(SevDeskClient::$calls === 1 && $created[0] === 303 && str_contains($created[1]['Location'] ?? '', '/ausgangsrechnungen/pruef/rechnung/'), 'Created draft did not open its central invoice');
    define('CENEOS_BILLING_HOSTED', true);
    $GLOBALS['billing_context'] = ['tenant_id' => 2];
    $GLOBALS['billing_url'] = static fn(string $path): string => '/central/' . $path;
    $response = BillingController::index([], true);
    ensurePreparation(str_contains($response[2], 'Offene Prüfungen in Prüfapp vorbereiten'), 'Central preparation link missing');
    ensurePreparation(!str_contains($response[2], 'billing-actions-panel') && !str_contains($response[2], 'Nächste Rechnung vorschlagen'), 'Open preparation leaked into central invoice overview');
    echo "PASS: local open queue, complete selection form, CSRF, tenant scope, archived duplicates, explicit release, gross preview, empty selection and central invoice-only view\n";
}
