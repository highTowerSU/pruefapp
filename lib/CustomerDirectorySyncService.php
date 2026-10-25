<?php

declare(strict_types=1);

use Ceneos\PhpBase\Central\CentralDatabase;
use Ceneos\PhpBase\Customer\CustomerDirectoryRepository;
use RedBeanPHP\R;

/** Keeps PrüFapp's structure IDs while mirroring shared customer records centrally. */
final class CustomerDirectorySyncService
{
    private static bool $synchronized = false;

    public static function synchronizeAll(): void
    {
        if (self::$synchronized) return;
        self::ensureLocalColumns();
        $directory = new CustomerDirectoryRepository(new CentralDatabase());
        $customers = array_values(R::findAll('customer', ' ORDER BY id '));
        $centralIds = [];

        foreach ($customers as $customer) {
            $tenantId = (int) ($customer->tenant_id ?? 0);
            if ($tenantId < 1) continue;
            $localId = (int) $customer->id;
            $centralId = (int) ($customer->ceneos_customer_id ?? 0);
            if ($centralId < 1) {
                $link = $directory->systemLink($tenantId, 'customer', 'pruefapp', (string) $localId);
                $centralId = (int) ($link['entity_id'] ?? 0);
            }
            $central = $centralId > 0 ? $directory->customer($tenantId, $centralId) : null;
            if ($central === null) {
                $centralId = $directory->upsertLegacyCustomer($tenantId, $localId, self::customerFields($customer));
                $central = $directory->customer($tenantId, $centralId);
            } else {
                $directory->linkSystemRecord($tenantId, 'customer', $centralId, 'pruefapp', (string) $localId);
                self::copyCustomerToLocal($customer, $central);
            }
            if ($central !== null) {
                $customer->ceneos_customer_id = $centralId;
                R::store($customer);
                $centralIds[$tenantId . ':' . $localId] = $centralId;
            }
        }

        foreach ($customers as $customer) {
            $tenantId = (int) ($customer->tenant_id ?? 0);
            $parentLocalId = (int) ($customer->parent_customer_id ?? 0);
            $customerCentralId = (int) ($customer->ceneos_customer_id ?? 0);
            if ($tenantId < 1 || $parentLocalId < 1 || $customerCentralId < 1) continue;
            $parentCentralId = $centralIds[$tenantId . ':' . $parentLocalId] ?? 0;
            if ($parentCentralId < 1) continue;
            $central = $directory->customer($tenantId, $customerCentralId);
            if ($central === null || (int) ($central['parent_customer_id'] ?? 0) === $parentCentralId) continue;
            $central['parent_customer_id'] = $parentCentralId;
            $directory->saveCustomer($tenantId, $central, $customerCentralId);
        }

        foreach (R::findAll('site', ' ORDER BY id ') as $site) {
            $localCustomer = R::load('customer', (int) $site->customer_id);
            $tenantId = (int) ($localCustomer->tenant_id ?? 0);
            $centralCustomerId = (int) ($localCustomer->ceneos_customer_id ?? 0);
            if ($tenantId < 1 || $centralCustomerId < 1) continue;
            $localId = (int) $site->id;
            $centralSiteId = (int) ($site->ceneos_site_id ?? 0);
            if ($centralSiteId < 1) {
                $link = $directory->systemLink($tenantId, 'site', 'pruefapp', (string) $localId);
                $centralSiteId = (int) ($link['entity_id'] ?? 0);
            }
            $centralSite = $centralSiteId > 0 ? $directory->site($tenantId, $centralSiteId) : null;
            if ($centralSite === null) {
                $centralSiteId = $directory->upsertLegacySite($tenantId, $centralCustomerId, $localId, self::siteFields($site));
                $centralSite = $directory->site($tenantId, $centralSiteId);
            } else {
                $directory->linkSystemRecord($tenantId, 'site', $centralSiteId, 'pruefapp', (string) $localId);
                self::copySiteToLocal($site, $centralSite);
            }
            if ($centralSite !== null) {
                $site->ceneos_site_id = $centralSiteId;
                R::store($site);
            }
        }
        self::pullSharedRecords($directory);
        self::$synchronized = true;
    }

    /** New central records become available without granting users additional customer access. */
    public static function pullSharedRecords(CustomerDirectoryRepository $directory): void
    {
        self::ensureLocalColumns();
        $lock = fopen(sys_get_temp_dir().'/pruefapp-central-directory-pull.lock','c');
        if (!$lock || !flock($lock,LOCK_EX | LOCK_NB)) return;
        try {
        foreach ((new \Ceneos\PhpBase\Tenant\TenantRepository())->all() as $tenant) {
            if (!(int)($tenant->is_active ?? 1)) continue;
            $tenantId = (int)$tenant->id;
            $localIds = [];
            $records = $directory->customers($tenantId);
            foreach ($records as $central) {
                $local = R::findOne('customer','tenant_id=? AND ceneos_customer_id=?',[$tenantId,(int)$central['id']]);
                if (!$local) {
                    $local = R::dispense('customer');
                    $local->tenant_id = $tenantId;
                    $local->created_at = date(DATE_ATOM);
                    self::copyCustomerToLocal($local,$central);
                    $directory->linkSystemRecord($tenantId,'customer',(int)$central['id'],'pruefapp',(string)$local->id);
                }
                $localIds[(int)$central['id']] = (int)$local->id;
            }
            foreach ($records as $central) {
                $localId = $localIds[(int)$central['id']];
                $parentId = $localIds[(int)($central['parent_customer_id'] ?? 0)] ?? null;
                R::exec('UPDATE customer SET parent_customer_id=? WHERE id=? AND tenant_id=?',[$parentId,$localId,$tenantId]);
                foreach ($directory->sites($tenantId,(int)$central['id']) as $centralSite) {
                    $site = R::findOne('site','ceneos_site_id=?',[(int)$centralSite['id']]);
                    if ($site && (int)$site->customer_id !== $localId) throw new RuntimeException('Gemeinsamer Standort ist lokal einem anderen Kunden zugeordnet. Bitte im Controlcenter prüfen.');
                    if (!$site) {
                        $site = R::dispense('site');
                        $site->customer_id = $localId;
                        $site->created_at = date(DATE_ATOM);
                        self::copySiteToLocal($site,$centralSite);
                        $directory->linkSystemRecord($tenantId,'site',(int)$centralSite['id'],'pruefapp',(string)$site->id);
                    }
                }
            }
        }
        } finally { flock($lock,LOCK_UN);fclose($lock); }
    }

    public static function syncSaved(string $type, int $localId): void
    {
        if (!in_array($type, ['customer', 'site'], true) || $localId < 1) return;
        self::ensureLocalColumns();
        $directory = new CustomerDirectoryRepository(new CentralDatabase());
        if ($type === 'customer') {
            $customer = R::load('customer', $localId);
            if (!$customer->id) return;
            $tenantId = (int) ($customer->tenant_id ?? 0);
            if ($tenantId < 1) return;
            $centralId = (int) ($customer->ceneos_customer_id ?? 0);
            if ($centralId > 0 && $directory->customer($tenantId, $centralId) !== null) {
                $centralId = $directory->saveCustomer($tenantId, self::customerFields($customer), $centralId);
                $directory->linkSystemRecord($tenantId, 'customer', $centralId, 'pruefapp', (string) $localId);
            } else {
                $centralId = $directory->upsertLegacyCustomer($tenantId, $localId, self::customerFields($customer));
            }
            $customer->ceneos_customer_id = $centralId;
            R::store($customer);
            return;
        }

        $site = R::load('site', $localId);
        if (!$site->id) return;
        $customer = R::load('customer', (int) $site->customer_id);
        if ((int) ($customer->ceneos_customer_id ?? 0) < 1) {
            self::syncSaved('customer', (int) $customer->id);
            $customer = R::load('customer', (int) $site->customer_id);
        }
        $tenantId = (int) ($customer->tenant_id ?? 0);
        $centralCustomerId = (int) ($customer->ceneos_customer_id ?? 0);
        if ($tenantId < 1 || $centralCustomerId < 1) return;
        $centralSiteId = (int) ($site->ceneos_site_id ?? 0);
        if ($centralSiteId > 0 && $directory->site($tenantId, $centralSiteId) !== null) {
            $centralSiteId = $directory->saveSite($tenantId, $centralCustomerId, self::siteFields($site), $centralSiteId);
            $directory->linkSystemRecord($tenantId, 'site', $centralSiteId, 'pruefapp', (string) $localId);
        } else {
            $centralSiteId = $directory->upsertLegacySite($tenantId, $centralCustomerId, $localId, self::siteFields($site));
        }
        $site->ceneos_site_id = $centralSiteId;
        R::store($site);
    }

    private static function ensureLocalColumns(): void
    {
        foreach (['customer' => 'ceneos_customer_id', 'site' => 'ceneos_site_id'] as $table => $column) {
            $columns = R::getColumns($table);
            if (!isset($columns[$column])) R::exec("ALTER TABLE {$table} ADD COLUMN {$column} INTEGER NULL");
        }
    }

    /** @return array<string,mixed> */
    private static function customerFields(object $customer): array
    {
        $parentId = (int) ($customer->parent_customer_id ?? 0);
        $parent = $parentId > 0 ? R::load('customer', $parentId) : null;
        return [
            'parent_customer_id' => (int) ($parent->ceneos_customer_id ?? 0) ?: null,
            'name' => (string) ($customer->name ?? ''),
            'code' => (string) ($customer->code ?? ''),
            'description' => (string) ($customer->description ?? ''),
            'comment' => (string) ($customer->comment ?? ''),
            'invoice_recipient_name' => (string) ($customer->invoice_recipient_name ?? ''),
            'invoice_contact_name' => (string) ($customer->invoice_contact_name ?? ''),
            'invoice_address_street' => (string) ($customer->invoice_address_street ?? ''),
            'invoice_address_zip' => (string) ($customer->invoice_address_zip ?? ''),
            'invoice_address_city' => (string) ($customer->invoice_address_city ?? ''),
            'invoice_address_country' => (string) ($customer->invoice_address_country ?? 'Deutschland'),
            'sevdesk_customer_id' => (string) ($customer->sevdesk_customer_id ?? ''),
            'sevdesk_customer_number' => (string) ($customer->sevdesk_customer_number ?? ''),
        ];
    }

    /** @return array<string,mixed> */
    private static function siteFields(object $site): array
    {
        return [
            'name' => (string) ($site->name ?? ''),
            'code' => (string) ($site->code ?? ''),
            'street' => (string) ($site->street ?? ''),
            'postal_code' => (string) ($site->postal_code ?? ''),
            'city' => (string) ($site->city ?? ''),
            'country' => (string) ($site->country ?? 'Deutschland'),
            'description' => (string) ($site->description ?? ''),
            'comment' => (string) ($site->comment ?? ''),
        ];
    }

    /** @param array<string,mixed> $central */
    private static function copyCustomerToLocal(object $local, array $central): void
    {
        foreach ([
            'name', 'code', 'description', 'comment', 'sevdesk_customer_id',
            'sevdesk_customer_number', 'invoice_recipient_name', 'invoice_contact_name',
            'invoice_address_street', 'invoice_address_zip', 'invoice_address_city',
            'invoice_address_country',
        ] as $field) {
            if (array_key_exists($field, $central)) $local->{$field} = $central[$field];
        }
        $local->ceneos_customer_id = (int) $central['id'];
        $local->updated_at = date(DATE_ATOM);
        R::store($local);
    }

    /** @param array<string,mixed> $central */
    private static function copySiteToLocal(object $local, array $central): void
    {
        foreach (['name', 'code', 'street', 'postal_code', 'city', 'description', 'comment'] as $field) {
            if (array_key_exists($field, $central)) $local->{$field} = $central[$field];
        }
        $local->ceneos_site_id = (int) $central['id'];
        $local->updated_at = date(DATE_ATOM);
        R::store($local);
    }
}
