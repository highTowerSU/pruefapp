<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use RedBeanPHP\R;

function current_user_can_manage_courses(): bool
{
    return true;
}

function url_for(string $path = ''): string
{
    return '/' . ltrim($path, '/');
}

function audit_log(string $action, array $context = []): void
{
}

function audit_log_mask_token(string $token): string
{
    return 'masked';
}

require dirname(__DIR__) . '/controllers/CourseController.php';

R::setup('sqlite::memory:');

$course = R::dispense('kurs');
$course->name = 'Testkurs';
R::store($course);

$company = R::dispense('company');
$company->name = 'Testfirma';
R::store($company);

$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = [
    'action' => 'create',
    'name' => 'Externer Link',
    'company_id' => (string) $company->id,
];

$response = CourseController::linkSettings(['id' => (string) $course->id], false);
$link = R::findOne('uebermittlungslink');

if ($response[0] !== 303 || $link === null) {
    throw new RuntimeException('The public link was not created.');
}

if ((int) $link->company_id !== (int) $company->id) {
    throw new RuntimeException('The selected branding company was not stored on the link.');
}

R::close();
