<?php

declare(strict_types=1);
require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/lib/InspectionFilterService.php';
use RedBeanPHP\R;
R::setup('sqlite::memory:');
R::exec('CREATE TABLE device(id INTEGER PRIMARY KEY,external_number TEXT)');
foreach(['10','2','ABC','12A','1']as$number)R::exec('INSERT INTO device(external_number)VALUES(?)',[$number]);
$numbers=R::getCol('SELECT d.external_number FROM device d ORDER BY '.InspectionFilterService::deviceNumberOrder());
if($numbers!==['1','2','10','12A','ABC'])throw new RuntimeException('Device export lost numeric-prefix ordering.');
echo "PASS: Shared device-number ordering executes on the active database driver\n";
