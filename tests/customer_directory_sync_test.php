<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

$localPath = tempnam(sys_get_temp_dir(), 'pruefapp-customer-');
$centralPath = tempnam(sys_get_temp_dir(), 'ceneos-customers-');
$configPath = tempnam(sys_get_temp_dir(), 'pruefapp-config-');
file_put_contents($configPath, '<?php return ' . var_export([
    'APP_TENANT_DATABASE_PATH' => $centralPath,
], true) . ';');
\Ceneos\PhpBase\Config\Config::load(dirname(__DIR__), 'pruefapp', $configPath, false);
\RedBeanPHP\R::setup('sqlite:' . $localPath);
\RedBeanPHP\R::freeze(false);
\RedBeanPHP\R::exec("CREATE TABLE customer (
    id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, parent_customer_id INTEGER NULL,
    tenant_id INTEGER NOT NULL DEFAULT 73, ceneos_customer_id INTEGER NULL, code TEXT NOT NULL DEFAULT '',
    description TEXT NULL, comment TEXT NULL, sevdesk_customer_id TEXT NOT NULL DEFAULT '',
    sevdesk_customer_number TEXT NOT NULL DEFAULT '', invoice_recipient_name TEXT NOT NULL DEFAULT '',
    invoice_contact_name TEXT NOT NULL DEFAULT '', invoice_address_street TEXT NOT NULL DEFAULT '',
    invoice_address_zip TEXT NOT NULL DEFAULT '', invoice_address_city TEXT NOT NULL DEFAULT '',
    invoice_address_country TEXT NOT NULL DEFAULT 'Deutschland', created_at TEXT NULL, updated_at TEXT NULL
)");
\RedBeanPHP\R::exec("CREATE TABLE site (
    id INTEGER PRIMARY KEY AUTOINCREMENT, customer_id INTEGER NOT NULL, name TEXT NOT NULL,
    ceneos_site_id INTEGER NULL, code TEXT NOT NULL DEFAULT '', street TEXT NOT NULL DEFAULT '',
    postal_code TEXT NOT NULL DEFAULT '', city TEXT NOT NULL DEFAULT '', description TEXT NULL,
    comment TEXT NULL, created_at TEXT NULL, updated_at TEXT NULL
)");
require dirname(__DIR__) . '/lib/CustomerDirectorySyncService.php';

try {
    $customer = \RedBeanPHP\R::dispense('customer');
    $customer->tenant_id = 73;
    $customer->name = 'Local Test';
    $customer->sevdesk_customer_number = '4400';
    $customerId = (int) \RedBeanPHP\R::store($customer);

    $site = \RedBeanPHP\R::dispense('site');
    $site->customer_id = $customerId;
    $site->name = 'Zentrale';
    $site->street = 'Testweg 2';
    $siteId = (int) \RedBeanPHP\R::store($site);

    CustomerDirectorySyncService::synchronizeAll();
    $localCustomer = \RedBeanPHP\R::load('customer', $customerId);
    $localSite = \RedBeanPHP\R::load('site', $siteId);
    if ((int) $localCustomer->ceneos_customer_id < 1 || (int) $localSite->ceneos_site_id < 1) {
        throw new RuntimeException('Customer or site was not mapped to a CENEOS ID.');
    }

    $directory = new \Ceneos\PhpBase\Customer\CustomerDirectoryRepository(
        new \Ceneos\PhpBase\Central\CentralDatabase($centralPath)
    );
    $centralCustomer = $directory->customer(73, (int) $localCustomer->ceneos_customer_id);
    $centralSite = $directory->site(73, (int) $localSite->ceneos_site_id);
    if (($centralCustomer['name'] ?? '') !== 'Local Test' || ($centralSite['street'] ?? '') !== 'Testweg 2') {
        throw new RuntimeException('PrüFapp customer or site was not mirrored centrally.');
    }
    if ($directory->systemLink(73, 'customer', 'pruefapp', (string) $customerId) === null) {
        throw new RuntimeException('PrüFapp customer source link is missing.');
    }

    $localCustomer->name = 'Changed from PrüFapp';
    \RedBeanPHP\R::store($localCustomer);
    CustomerDirectorySyncService::syncSaved('customer', $customerId);
    if (($directory->customer(73, (int) $localCustomer->ceneos_customer_id)['name'] ?? '') !== 'Changed from PrüFapp') {
        throw new RuntimeException('Saved PrüFapp changes were not mirrored centrally.');
    }

    echo "PASS: PrüFapp customer/site directory sync\n";
} finally {
    foreach ([$localPath, $localPath . '-wal', $localPath . '-shm', $centralPath, $centralPath . '-wal', $centralPath . '-shm', $configPath] as $path) {
        if (is_file($path)) unlink($path);
    }
}
