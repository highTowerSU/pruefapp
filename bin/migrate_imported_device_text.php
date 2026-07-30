#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/lib.inc.php';

$migrated = 0;
foreach (RedBeanPHP\R::findAll('device') as $device) {
    $description = trim((string) ($device->description ?? ''));
    if ($description === '' || trim((string) ($device->comment ?? '')) !== '') continue;
    $device->comment = $description;
    $device->description = '';
    $device->updated_at = date(DATE_ATOM);
    RedBeanPHP\R::store($device);
    $migrated++;
}
echo $migrated . " Gerät(e) migriert.\n";
