<?php

declare(strict_types=1);

use RedBeanPHP\R;

$root = sys_get_temp_dir() . '/pruefapp-vocabulary-storage-' . bin2hex(random_bytes(5));
mkdir($root, 0770, true);
mkdir($root . '/sessions', 0770, true);
session_save_path($root . '/sessions');
$config = $root . '/config.php';
file_put_contents($config, '<?php return ' . var_export([
    'APP_STORAGE_NAMESPACE' => 'vocabulary_storage_test',
    'APP_DATABASE_PATH' => $root . '/db.sqlite',
    'APP_OIDC_ISSUER_URL' => 'https://login.example.test/realms/test',
    'APP_OIDC_CLIENT_ID' => 'test-client',
    'APP_OIDC_CLIENT_SECRET' => 'test-secret',
], true) . ';');
define('CENEOS_CONFIG_FILE', $config);
$_SERVER['SCRIPT_NAME'] = '/pruefapp/index.php';
$_SERVER['PHP_SELF'] = '/pruefapp/index.php';
$_SERVER['REQUEST_URI'] = '/pruefapp/';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

require_once dirname(__DIR__) . '/lib/lib.inc.php';

try {
    $reviewId = DeviceVocabularyService::storeSuggestion('manufacturer', 'Laybo', [
        'canonical_value' => 'Laybo GmbH', 'confidence' => 0.95, 'reason' => 'Eindeutig', 'provider_model' => 'test',
    ]);
    if ($reviewId < 1 || (int) R::getCell('SELECT COUNT(*) FROM device_vocabulary_review') !== 1) {
        throw new RuntimeException('Stammdatenvorschläge werden nicht in der vorhandenen Review-Tabelle gespeichert.');
    }
    $sameReviewId = DeviceVocabularyService::storeSuggestion('manufacturer', 'Laybo', [
        'canonical_value' => 'Laybo', 'confidence' => 0.96, 'reason' => 'Aktualisiert', 'provider_model' => 'test',
    ]);
    if ($sameReviewId !== $reviewId || (int) R::getCell('SELECT COUNT(*) FROM device_vocabulary_review') !== 1) {
        throw new RuntimeException('Ein bestehender Stammdatenvorschlag wird nicht aktualisiert.');
    }
    $device = R::dispense('device');
    $device->room_id = 0; $device->name = 'Testgerät'; $device->manufacturer = 'Laybo';
    R::store($device);
    $result = DeviceVocabularyService::applyAlias('manufacturer', 'Laybo', 'Laybo GmbH', 7);
    if ($result['updated_devices'] !== 1 || DeviceVocabularyService::canonicalize('manufacturer', 'Laybo') !== 'Laybo GmbH') {
        throw new RuntimeException('Freigegebene Stammdatenalias werden nicht ohne ungültige RedBean-Beans angewendet.');
    }
} finally {
    R::close();
}

echo "PASS: Stammdaten-Review- und Alias-Speicher funktionieren mit den SQL-Tabellen\n";
