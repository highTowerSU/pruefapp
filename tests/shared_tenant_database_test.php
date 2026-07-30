<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Ceneos\PhpBase\Tenant\TenantRepository;
use RedBeanPHP\R;

$path = sys_get_temp_dir() . '/ceneos-tenants-' . bin2hex(random_bytes(4)) . '.sqlite';
R::setup('sqlite::memory:');
$repository = new TenantRepository($path);
$repository->seed(['test' => ['company_name' => 'Gemeinsamer Mandant', 'is_default' => true]]);

if (($repository->default()?->name ?? '') !== 'Gemeinsamer Mandant') {
    throw new RuntimeException('Der Mandant wurde nicht in der gemeinsamen Datenbank gespeichert.');
}
if (R::getWriter()->tableExists('company')) {
    throw new RuntimeException('Die App-Datenbank darf keine eigene Mandantentabelle erhalten.');
}

R::close();
unlink($path);
