<?php

use \RedBeanPHP\R as R;
use Ceneos\PhpBase\Config\Config;
use Ceneos\PhpBase\Database\RevisionSupport;
use Ceneos\PhpBase\Database\Migrator;
use Ceneos\PhpBase\Http\BootstrapErrorPage;

$baseDir = dirname(__DIR__);
$appConfigCache = [];

$autoloadCandidates = [
    $baseDir . '/vendor/autoload.php',
    __DIR__ . '/vendor/autoload.php',
    dirname($baseDir) . '/vendor/autoload.php',
];

foreach ($autoloadCandidates as $autoloadPath) {
    if (file_exists($autoloadPath)) {
        require_once $autoloadPath;
        break;
    }
}

if (!class_exists(Config::class)) {
    throw new \RuntimeException(
        'CENEOS PHP Base konnte nicht geladen werden. Bitte Composer-Abhängigkeiten installieren.'
    );
}

try {
    ob_start();
    Config::load(
        $baseDir,
        'pruefapp',
        defined('CENEOS_CONFIG_FILE') ? CENEOS_CONFIG_FILE : null
    );
    ob_end_clean();
} catch (\Throwable $exception) {
    if (ob_get_level() > 0) {
        ob_end_clean();
    }
    BootstrapErrorPage::emit('PrüfApp', $exception);
}

if (PHP_SAPI !== 'cli') {
    configure_session();
    session_start();
}

if (!class_exists('RedBeanPHP\\R')) {
    throw new \RuntimeException('RedBeanPHP konnte nicht geladen werden. Bitte Composer-Abhängigkeiten installieren.');
}

spl_autoload_register(function (string $class): void {
    if (strpos($class, 'Model_') !== 0) {
        return;
    }

    $file = __DIR__ . '/models/' . substr($class, 6) . '.php';
    if (is_file($file)) {
        require_once $file;
    }
});

require_once __DIR__ . '/htmx.php';
require_once __DIR__ . '/router.php';
require_once __DIR__ . '/branding.php';
require_once __DIR__ . '/audit_log.php';
require_once __DIR__ . '/DisplayPreferenceService.php';
require_once __DIR__ . '/InspectionEvaluationService.php';
require_once __DIR__ . '/InspectionTypeService.php';
require_once __DIR__ . '/DeviceFindingService.php';
require_once __DIR__ . '/InspectionFilterService.php';
require_once __DIR__ . '/UserReminderService.php';
require_once __DIR__ . '/InspectionDataService.php';
require_once __DIR__ . '/InspectionMigrationService.php';
require_once __DIR__ . '/AiProviderService.php';
require_once __DIR__ . '/DeviceVocabularyService.php';
require_once __DIR__ . '/DeviceMediaService.php';
require_once __DIR__ . '/RoomMediaService.php';
require_once __DIR__ . '/StructureMediaService.php';
require_once __DIR__ . '/DeviceDraftMediaService.php';
require_once __DIR__ . '/InspectionCompanionService.php';
require_once __DIR__ . '/InspectionCompanionInboxService.php';
require_once __DIR__ . '/ServerQrCodeService.php';
require_once __DIR__ . '/VocabularyOAuthService.php';
require_once __DIR__ . '/ElectricalInspectionImportService.php';
require_once __DIR__ . '/PhoenixSyncService.php';
require_once __DIR__ . '/BackgroundJobService.php';
require_once __DIR__ . '/MaintenanceJobHandler.php';

initialize_database();

/**
 * Retrieves a stored application configuration value.
 */
function get_app_config(string $key, ?string $default = null): ?string
{
    global $appConfigCache;

    $normalizedKey = trim($key);
    if ($normalizedKey === '') {
        return $default;
    }

    if (array_key_exists($normalizedKey, $appConfigCache)) {
        return $appConfigCache[$normalizedKey];
    }

    if (!R::testConnection()) {
        return $default;
    }

    $bean = R::findOne('appconfig', ' name = ? ', [$normalizedKey]);
    if ($bean === null) {
        $appConfigCache[$normalizedKey] = $default;

        return $default;
    }

    $value = (string) ($bean->value ?? '');
    $appConfigCache[$normalizedKey] = $value;

    return $value;
}

/**
 * Stores a configuration value in the application database.
 */
function set_app_config(string $key, ?string $value): void
{
    global $appConfigCache;

    $normalizedKey = trim($key);
    if ($normalizedKey === '') {
        throw new \InvalidArgumentException('Configuration key must not be empty.');
    }

    if (!R::testConnection()) {
        throw new \RuntimeException('Keine Datenbankverbindung für Konfigurationsspeicherung verfügbar.');
    }

    $normalizedValue = $value !== null ? trim((string) $value) : null;

    R::begin();
    try {
        $bean = R::findOne('appconfig', ' name = ? ', [$normalizedKey]);

        if ($normalizedValue === null || $normalizedValue === '') {
            if ($bean !== null) {
                R::trash($bean);
            }
        } else {
            if ($bean === null) {
                $bean = R::dispense('appconfig');
                $bean->name = $normalizedKey;
                $bean->created_at = date('c');
            }

            $bean->value = $normalizedValue;
            $bean->updated_at = date('c');
            R::store($bean);
        }

        R::commit();
    } catch (\Throwable $exception) {
        R::rollback();
        throw $exception;
    }

    unset($appConfigCache[$normalizedKey]);
}

function moodle_root_path(): string
{
    $fileRoot = config_value('MOODLE_PATH');
    if ($fileRoot !== null) {
        return rtrim($fileRoot, DIRECTORY_SEPARATOR);
    }

    $configured = get_app_config('moodle_path', '');
    $configured = is_string($configured) ? trim($configured) : '';

    if ($configured === '') {
        return '';
    }

    return rtrim($configured, DIRECTORY_SEPARATOR);
}

function moodle_webservice_base_url(): string
{
    $fileUrl = config_value('MOODLE_WEBSERVICE_URL');
    if ($fileUrl !== null && $fileUrl !== '') {
        return rtrim($fileUrl, '/');
    }

    $configured = get_app_config('moodle_webservice_url', '');
    if (!is_string($configured)) {
        return '';
    }

    $configured = trim($configured);

    return $configured === '' ? '' : rtrim($configured, '/');
}

function moodle_webservice_token(): string
{
    $fileToken = config_value('MOODLE_WEBSERVICE_TOKEN');
    if ($fileToken !== null && $fileToken !== '') {
        return $fileToken;
    }

    $configured = get_app_config('moodle_webservice_token', '');
    if (!is_string($configured)) {
        return '';
    }

    return trim($configured);
}

function initialize_database(): void
{
    static $initialized = false;

    if ($initialized) {
        return;
    }

    global $baseDir;

    $storageNamespace = app_storage_namespace();
    $legacyNamespace = 'pruefapp';

    $configuredDsn = trim((string) (config_value('APP_DATABASE_DSN') ?? ''));
    if ($configuredDsn !== '') {
        $dbUser = (string) (config_value('APP_DATABASE_USER') ?? '');
        $dbPassword = (string) (config_value('APP_DATABASE_PASSWORD') ?? '');
        R::setup($configuredDsn, $dbUser, $dbPassword);
        $GLOBALS['pruefapp_database_path'] = $configuredDsn;
        R::freeze(false);
        ensure_structure_schema();
        AiProviderService::ensureSchema();
        RevisionSupport::enableFor(
            ['nutzer', 'kurs', 'teilnehmer', 'uebermittlungslink', 'oauthuser', 'customer', 'site', 'building', 'floor', 'area', 'room', 'device', 'inspection', 'inspectionanswer', 'inspectionmeasurement', 'inspectiondiagnostic', 'inspectionsourcesnapshot', 'inspectionreportasset', 'customerinfo']
        );
        $initialized = true;
        return;
    }

    $dbCandidates = [];
    $configuredDatabasePath = config_value('APP_DATABASE_PATH');
    if ($configuredDatabasePath !== null) {
        if (!str_starts_with($configuredDatabasePath, '/')
            && preg_match('/^[A-Za-z]:[\\\\\\/]/', $configuredDatabasePath) !== 1
        ) {
            throw new \RuntimeException('APP_DATABASE_PATH muss ein absoluter Pfad sein.');
        }
        $dbCandidates[] = $configuredDatabasePath;
    }

    // Keep the established PrüfApp database locations first. The shared data
    // root also contains reports/import logs and must not become an implicit
    // database location merely because its directory exists.
    $dbCandidates = array_merge($dbCandidates, [
        $baseDir . '/../../data/' . $storageNamespace . '/db.sqlite',
        $baseDir . '/data/' . $storageNamespace . '/db.sqlite',
        dirname($baseDir) . '/data/' . $storageNamespace . '/db.sqlite',
        $baseDir . '/../../data/' . $legacyNamespace . '/db.sqlite',
        $baseDir . '/data/' . $legacyNamespace . '/db.sqlite',
        dirname($baseDir) . '/data/' . $legacyNamespace . '/db.sqlite',
        $baseDir . '/db.sqlite',
    ]);

    $dbPath = null;
    foreach ($dbCandidates as $candidate) {
        if (is_file($candidate) && (int) @filesize($candidate) > 0) {
            $dbPath = $candidate;
            break;
        }
    }
    if ($dbPath === null) {
        foreach ($dbCandidates as $candidate) {
            if (is_file($candidate)) { $dbPath = $candidate; break; }
        }
    }

    if ($dbPath === null) {
        $primaryDir = dirname($dbCandidates[0]);
        if (!is_dir($primaryDir)) {
            @mkdir($primaryDir, 0777, true);
        }
        $dbPath = $dbCandidates[0];
    }

    R::setup('sqlite:' . $dbPath);
    $GLOBALS['pruefapp_database_path'] = $dbPath;
    R::freeze(false);
    ensure_structure_schema();
    AiProviderService::ensureSchema();

    RevisionSupport::enableFor(
        [
            'nutzer',
            'kurs',
            'teilnehmer',
            'uebermittlungslink',
            'oauthuser',
            'customer',
            'site',
            'building',
            'floor',
            'area',
            'room',
            'device',
            'inspection',
            'inspectionanswer',
            'inspectionmeasurement',
            'inspectiondiagnostic',
            'inspectionsourcesnapshot',
            'inspectionreportasset',
            'customerinfo',
        ]
    );

    $initialized = true;
}

function app_database_path(): string
{
    return (string) ($GLOBALS['pruefapp_database_path'] ?? '');
}


function ensure_structure_schema(): void
{
    $isMysql = str_starts_with(strtolower((string) ($GLOBALS['pruefapp_database_path'] ?? '')), 'mysql:') || str_starts_with(strtolower((string) ($GLOBALS['pruefapp_database_path'] ?? '')), 'mariadb:');
    $statements = [
        'CREATE TABLE IF NOT EXISTS customer (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, parent_customer_id INTEGER NULL, created_at TEXT NULL, updated_at TEXT NULL)',
        'CREATE TABLE IF NOT EXISTS oauthuser_customer (id INTEGER PRIMARY KEY AUTOINCREMENT, oauthuser_id INTEGER NOT NULL, customer_id INTEGER NOT NULL, created_at TEXT NULL, UNIQUE(oauthuser_id, customer_id))',
        'CREATE TABLE IF NOT EXISTS site (id INTEGER PRIMARY KEY AUTOINCREMENT, customer_id INTEGER NOT NULL, name TEXT NOT NULL, created_at TEXT NULL, updated_at TEXT NULL)',
        'CREATE TABLE IF NOT EXISTS building (id INTEGER PRIMARY KEY AUTOINCREMENT, site_id INTEGER NOT NULL, name TEXT NOT NULL, created_at TEXT NULL, updated_at TEXT NULL)',
        'CREATE TABLE IF NOT EXISTS floor (id INTEGER PRIMARY KEY AUTOINCREMENT, building_id INTEGER NOT NULL, name TEXT NOT NULL, created_at TEXT NULL, updated_at TEXT NULL)',
        'CREATE TABLE IF NOT EXISTS area (id INTEGER PRIMARY KEY AUTOINCREMENT, floor_id INTEGER NOT NULL, name TEXT NOT NULL, code TEXT NOT NULL, comment TEXT NULL, metadata_json TEXT NULL, created_at TEXT NULL, updated_at TEXT NULL)',
        'CREATE TABLE IF NOT EXISTS room (id INTEGER PRIMARY KEY AUTOINCREMENT, floor_id INTEGER NOT NULL, name TEXT NOT NULL, created_at TEXT NULL, updated_at TEXT NULL)',
        'CREATE TABLE IF NOT EXISTS device (id INTEGER PRIMARY KEY AUTOINCREMENT, room_id INTEGER NOT NULL, name TEXT NOT NULL, serial_number TEXT NULL, inventory_number TEXT NULL, created_at TEXT NULL, updated_at TEXT NULL)',
        'CREATE TABLE IF NOT EXISTS inspection (id INTEGER PRIMARY KEY AUTOINCREMENT, device_id INTEGER NOT NULL, dedupe_key TEXT NOT NULL UNIQUE, source_type TEXT NOT NULL, source_file TEXT NULL, external_number TEXT NULL, storage_slot TEXT NULL, test_date TEXT NULL, next_due_date TEXT NULL, result_status TEXT NULL, device_type TEXT NULL, manufacturer TEXT NULL, device_model TEXT NULL, room_snapshot TEXT NULL, measurements_json TEXT NULL, checklist_json TEXT NULL, raw_json TEXT NULL, report_path TEXT NULL, created_at TEXT NULL, updated_at TEXT NULL)',
        "CREATE TABLE IF NOT EXISTS inspection_catalog_version (id INTEGER PRIMARY KEY AUTOINCREMENT, code TEXT NOT NULL UNIQUE, name TEXT NOT NULL, active INTEGER NOT NULL DEFAULT 0, locked_at TEXT NULL, created_at TEXT NULL)",
        "CREATE TABLE IF NOT EXISTS inspection_type (id INTEGER PRIMARY KEY AUTOINCREMENT, code TEXT NOT NULL UNIQUE, name TEXT NOT NULL, icon TEXT NOT NULL DEFAULT '', default_interval_days INTEGER NOT NULL DEFAULT 365, active INTEGER NOT NULL DEFAULT 1, sort_order INTEGER NOT NULL DEFAULT 0, created_at TEXT NULL, updated_at TEXT NULL)",
        "CREATE TABLE IF NOT EXISTS inspection_type_requirement (id INTEGER PRIMARY KEY AUTOINCREMENT, inspection_type_code TEXT NOT NULL, code TEXT NOT NULL, name TEXT NOT NULL, validity_days INTEGER NULL, requires_confirmation INTEGER NOT NULL DEFAULT 0, active INTEGER NOT NULL DEFAULT 1, sort_order INTEGER NOT NULL DEFAULT 0, UNIQUE(inspection_type_code, code))",
        "CREATE TABLE IF NOT EXISTS user_qualification (id INTEGER PRIMARY KEY AUTOINCREMENT, oauthuser_id INTEGER NOT NULL, requirement_code TEXT NOT NULL, issued_at TEXT NULL, expires_at TEXT NULL, proof_path TEXT NOT NULL DEFAULT '', proof_name TEXT NOT NULL DEFAULT '', confirmed_by INTEGER NULL, confirmed_at TEXT NULL, notes TEXT NOT NULL DEFAULT '', created_at TEXT NULL, updated_at TEXT NULL)",
        "CREATE TABLE IF NOT EXISTS userdisplaypreference (id INTEGER PRIMARY KEY AUTOINCREMENT, oauthuser_id INTEGER NOT NULL UNIQUE, theme TEXT NOT NULL DEFAULT 'auto', contrast TEXT NOT NULL DEFAULT 'standard', font_scale TEXT NOT NULL DEFAULT 'standard', font_weight TEXT NOT NULL DEFAULT 'standard', font_family TEXT NOT NULL DEFAULT 'system', motion TEXT NOT NULL DEFAULT 'system', updated_at TEXT NULL)",
        "CREATE TABLE IF NOT EXISTS device_attribute (id INTEGER PRIMARY KEY AUTOINCREMENT, device_id INTEGER NOT NULL, inspection_type_code TEXT NOT NULL, attribute_key TEXT NOT NULL, value_json TEXT NOT NULL DEFAULT '{}', updated_at TEXT NULL, UNIQUE(device_id, inspection_type_code, attribute_key))",
        "CREATE TABLE IF NOT EXISTS device_finding (id INTEGER PRIMARY KEY AUTOINCREMENT, device_id INTEGER NOT NULL, inspection_id INTEGER NOT NULL, inspection_type_code TEXT NOT NULL, item_key TEXT NOT NULL DEFAULT '', severity TEXT NOT NULL DEFAULT 'green', state TEXT NOT NULL DEFAULT 'open', action TEXT NOT NULL DEFAULT '', due_date TEXT NULL, blocked INTEGER NOT NULL DEFAULT 0, description TEXT NOT NULL DEFAULT '', resolution_note TEXT NOT NULL DEFAULT '', resolved_at TEXT NULL, created_at TEXT NULL, updated_at TEXT NULL)",
        "CREATE TABLE IF NOT EXISTS inspection_catalog_item (id INTEGER PRIMARY KEY AUTOINCREMENT, version_id INTEGER NOT NULL, item_key TEXT NOT NULL, category TEXT NOT NULL DEFAULT '', question TEXT NOT NULL DEFAULT '', criterion TEXT NOT NULL DEFAULT '', input_type TEXT NOT NULL DEFAULT 'boolean', measurement_key TEXT NOT NULL DEFAULT '', required INTEGER NOT NULL DEFAULT 1, applies_to_json TEXT NOT NULL DEFAULT '{}', rule_key TEXT NOT NULL DEFAULT '', sort_order INTEGER NOT NULL DEFAULT 0, UNIQUE(version_id, item_key))",
        "CREATE TABLE IF NOT EXISTS inspection_answer (id INTEGER PRIMARY KEY AUTOINCREMENT, inspection_id INTEGER NOT NULL, catalog_version_id INTEGER NULL, item_key TEXT NOT NULL, category TEXT NOT NULL DEFAULT '', question_snapshot TEXT NOT NULL DEFAULT '', criterion_snapshot TEXT NOT NULL DEFAULT '', answer_value TEXT NOT NULL DEFAULT '', outcome TEXT NOT NULL DEFAULT 'missing', remark TEXT NOT NULL DEFAULT '', required INTEGER NOT NULL DEFAULT 1, skip_reason TEXT NOT NULL DEFAULT '', sort_order INTEGER NOT NULL DEFAULT 0, created_at TEXT NULL, updated_at TEXT NULL, UNIQUE(inspection_id, item_key))",
        "CREATE TABLE IF NOT EXISTS inspection_measurement (id INTEGER PRIMARY KEY AUTOINCREMENT, inspection_id INTEGER NOT NULL, measurement_key TEXT NOT NULL, name_snapshot TEXT NOT NULL DEFAULT '', numeric_value REAL NULL, text_value TEXT NOT NULL DEFAULT '', unit TEXT NOT NULL DEFAULT '', outcome TEXT NOT NULL DEFAULT 'missing', limit_value REAL NULL, limit_unit TEXT NOT NULL DEFAULT '', rule_key TEXT NOT NULL DEFAULT '', rule_version TEXT NOT NULL DEFAULT '1', voltage TEXT NOT NULL DEFAULT '', raw_json TEXT NOT NULL DEFAULT '{}', sort_order INTEGER NOT NULL DEFAULT 0, created_at TEXT NULL, updated_at TEXT NULL)",
        "CREATE TABLE IF NOT EXISTS inspection_diagnostic (id INTEGER PRIMARY KEY AUTOINCREMENT, inspection_id INTEGER NOT NULL, code TEXT NOT NULL, severity TEXT NOT NULL DEFAULT 'warning', message TEXT NOT NULL DEFAULT '', details_json TEXT NOT NULL DEFAULT '{}', created_at TEXT NULL)",
        "CREATE TABLE IF NOT EXISTS inspection_source_snapshot (id INTEGER PRIMARY KEY AUTOINCREMENT, inspection_id INTEGER NOT NULL UNIQUE, classification TEXT NOT NULL DEFAULT '', source_type TEXT NOT NULL DEFAULT '', source_file TEXT NOT NULL DEFAULT '', source_row_json TEXT NOT NULL DEFAULT '{}', legacy_row_json TEXT NOT NULL DEFAULT '{}', original_report_path TEXT NOT NULL DEFAULT '', original_report_checksum TEXT NOT NULL DEFAULT '', created_at TEXT NULL)",
        "CREATE TABLE IF NOT EXISTS inspection_report_asset (id INTEGER PRIMARY KEY AUTOINCREMENT, inspection_id INTEGER NOT NULL, asset_type TEXT NOT NULL, path TEXT NOT NULL DEFAULT '', checksum TEXT NOT NULL DEFAULT '', active INTEGER NOT NULL DEFAULT 0, created_at TEXT NULL, UNIQUE(inspection_id, asset_type))",
        "CREATE TABLE IF NOT EXISTS customerinfo (id INTEGER PRIMARY KEY AUTOINCREMENT, customer_id INTEGER NOT NULL, title TEXT NOT NULL DEFAULT '', slug TEXT NOT NULL DEFAULT '', markdown TEXT NOT NULL DEFAULT '', file_path TEXT NOT NULL DEFAULT '', file_name TEXT NOT NULL DEFAULT '', file_mime TEXT NOT NULL DEFAULT '', created_at TEXT NULL, updated_at TEXT NULL)",
        "CREATE TABLE IF NOT EXISTS billing_invoice (id INTEGER PRIMARY KEY AUTOINCREMENT, customer_id INTEGER NULL, tenant_id INTEGER NULL, sevdesk_invoice_id TEXT NOT NULL DEFAULT '', sevdesk_invoice_number TEXT NOT NULL DEFAULT '', sevdesk_url TEXT NOT NULL DEFAULT '', invoice_number TEXT NOT NULL DEFAULT '', invoice_date TEXT NULL, status TEXT NOT NULL DEFAULT 'draft', error_details TEXT NOT NULL DEFAULT '', created_by INTEGER NULL, exported_by TEXT NOT NULL DEFAULT '', exported_at TEXT NULL, amount_net REAL NULL, amount_gross REAL NULL, created_at TEXT NULL, updated_at TEXT NULL)",
        "CREATE TABLE IF NOT EXISTS billing_invoice_item (id INTEGER PRIMARY KEY AUTOINCREMENT, invoice_id INTEGER NOT NULL, inspection_id INTEGER NOT NULL, device_id INTEGER NOT NULL, quantity REAL NOT NULL DEFAULT 1, description TEXT NOT NULL DEFAULT '', active INTEGER NOT NULL DEFAULT 1, assigned_at TEXT NULL, deactivated_at TEXT NULL, deactivation_reason TEXT NOT NULL DEFAULT '', source TEXT NOT NULL DEFAULT 'sevdesk', created_at TEXT NULL)",
        "CREATE TABLE IF NOT EXISTS billing_export (id INTEGER PRIMARY KEY AUTOINCREMENT, idempotency_key TEXT NOT NULL UNIQUE, tenant_id INTEGER NULL, owner_user_id INTEGER NULL, status TEXT NOT NULL DEFAULT 'pending', inspection_ids_json TEXT NOT NULL DEFAULT '[]', invoice_id INTEGER NULL, sevdesk_invoice_id TEXT NOT NULL DEFAULT '', sevdesk_invoice_number TEXT NOT NULL DEFAULT '', error_details TEXT NOT NULL DEFAULT '', created_at TEXT NULL, updated_at TEXT NULL)",
        "CREATE TABLE IF NOT EXISTS appconfig (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL UNIQUE, value TEXT NOT NULL DEFAULT '', created_at TEXT NULL, updated_at TEXT NULL)",
        "CREATE TABLE IF NOT EXISTS device_vocabulary_alias (id INTEGER PRIMARY KEY AUTOINCREMENT, field_name TEXT NOT NULL, source_key TEXT NOT NULL, canonical_value TEXT NOT NULL, active INTEGER NOT NULL DEFAULT 1, approved_by INTEGER NULL, created_at TEXT NULL, updated_at TEXT NULL, UNIQUE(field_name, source_key))",
        "CREATE TABLE IF NOT EXISTS device_vocabulary_review (id INTEGER PRIMARY KEY AUTOINCREMENT, field_name TEXT NOT NULL, source_value TEXT NOT NULL, suggested_value TEXT NOT NULL DEFAULT '', confidence REAL NULL, reason TEXT NOT NULL DEFAULT '', provider_model TEXT NOT NULL DEFAULT '', status TEXT NOT NULL DEFAULT 'pending', decided_by INTEGER NULL, created_at TEXT NULL, updated_at TEXT NULL)",
        "CREATE TABLE IF NOT EXISTS device_media (id INTEGER PRIMARY KEY AUTOINCREMENT, device_id INTEGER NOT NULL, inspection_id INTEGER NULL, device_finding_id INTEGER NULL, media_type TEXT NOT NULL DEFAULT 'condition', caption TEXT NOT NULL DEFAULT '', path TEXT NOT NULL, original_name TEXT NOT NULL DEFAULT '', mime TEXT NOT NULL DEFAULT '', bytes INTEGER NOT NULL DEFAULT 0, created_by INTEGER NULL, created_at TEXT NULL)",
        "CREATE TABLE IF NOT EXISTS room_media (id INTEGER PRIMARY KEY AUTOINCREMENT, room_id INTEGER NOT NULL, media_type TEXT NOT NULL DEFAULT 'condition', caption TEXT NOT NULL DEFAULT '', path TEXT NOT NULL, original_name TEXT NOT NULL DEFAULT '', mime TEXT NOT NULL DEFAULT '', bytes INTEGER NOT NULL DEFAULT 0, created_by INTEGER NULL, created_at TEXT NULL)",
        "CREATE TABLE IF NOT EXISTS structure_media (id INTEGER PRIMARY KEY AUTOINCREMENT, structure_type TEXT NOT NULL, structure_id INTEGER NOT NULL, media_type TEXT NOT NULL DEFAULT 'condition', caption TEXT NOT NULL DEFAULT '', path TEXT NOT NULL, original_name TEXT NOT NULL DEFAULT '', mime TEXT NOT NULL DEFAULT '', bytes INTEGER NOT NULL DEFAULT 0, created_by INTEGER NULL, created_at TEXT NULL)",
        "CREATE TABLE IF NOT EXISTS device_media_analysis (id INTEGER PRIMARY KEY AUTOINCREMENT, media_id INTEGER NOT NULL UNIQUE, status TEXT NOT NULL DEFAULT 'pending', provider_model TEXT NOT NULL DEFAULT '', proposal_json TEXT NOT NULL DEFAULT '{}', error_message TEXT NOT NULL DEFAULT '', created_at TEXT NULL, updated_at TEXT NULL)",
        "CREATE TABLE IF NOT EXISTS device_draft_media (id INTEGER PRIMARY KEY AUTOINCREMENT, token_hash TEXT NOT NULL UNIQUE, owner_user_id INTEGER NOT NULL, media_type TEXT NOT NULL DEFAULT 'condition', caption TEXT NOT NULL DEFAULT '', path TEXT NOT NULL, original_name TEXT NOT NULL DEFAULT '', mime TEXT NOT NULL DEFAULT '', bytes INTEGER NOT NULL DEFAULT 0, proposal_json TEXT NOT NULL DEFAULT '', created_at TEXT NOT NULL, expires_at TEXT NOT NULL)",
        "CREATE TABLE IF NOT EXISTS inspection_companion_session (id INTEGER PRIMARY KEY AUTOINCREMENT, inspection_id INTEGER NOT NULL, owner_user_id INTEGER NOT NULL, token_hash TEXT NOT NULL UNIQUE, state TEXT NOT NULL DEFAULT 'pending', companion_user_id INTEGER NULL, latest_barcode TEXT NOT NULL DEFAULT '', created_at TEXT NOT NULL, connected_at TEXT NULL, last_activity_at TEXT NULL, expires_at TEXT NOT NULL, disconnected_at TEXT NULL)",
        "CREATE TABLE IF NOT EXISTS inspection_companion_item (id INTEGER PRIMARY KEY AUTOINCREMENT, session_id INTEGER NOT NULL, kind TEXT NOT NULL, value TEXT NOT NULL DEFAULT '', media_type TEXT NOT NULL DEFAULT '', caption TEXT NOT NULL DEFAULT '', path TEXT NOT NULL DEFAULT '', original_name TEXT NOT NULL DEFAULT '', mime TEXT NOT NULL DEFAULT '', bytes INTEGER NOT NULL DEFAULT 0, status TEXT NOT NULL DEFAULT 'pending', used_target TEXT NOT NULL DEFAULT '', used_at TEXT NULL, created_at TEXT NOT NULL)",
        "CREATE TABLE IF NOT EXISTS cron_log (id INTEGER PRIMARY KEY AUTOINCREMENT, run_at TEXT NOT NULL, level TEXT NOT NULL DEFAULT 'info', message TEXT NOT NULL DEFAULT '')",
        'CREATE INDEX IF NOT EXISTS idx_customer_parent ON customer (parent_customer_id)',
        'CREATE INDEX IF NOT EXISTS idx_site_customer ON site (customer_id)',
        'CREATE INDEX IF NOT EXISTS idx_building_site ON building (site_id)',
        'CREATE INDEX IF NOT EXISTS idx_floor_building ON floor (building_id)',
        'CREATE INDEX IF NOT EXISTS idx_room_floor ON room (floor_id)',
        'CREATE INDEX IF NOT EXISTS idx_area_floor ON area (floor_id)',
        'CREATE INDEX IF NOT EXISTS idx_device_room ON device (room_id)',
        'CREATE INDEX IF NOT EXISTS idx_inspection_device ON inspection (device_id)',
        'CREATE INDEX IF NOT EXISTS idx_inspection_date ON inspection (test_date)',
        'CREATE INDEX IF NOT EXISTS idx_inspection_answer_inspection ON inspection_answer (inspection_id, sort_order)',
        'CREATE INDEX IF NOT EXISTS idx_inspection_measurement_inspection ON inspection_measurement (inspection_id, sort_order)',
        'CREATE INDEX IF NOT EXISTS idx_inspection_diagnostic_inspection ON inspection_diagnostic (inspection_id)',
        'CREATE INDEX IF NOT EXISTS idx_inspection_report_asset_inspection ON inspection_report_asset (inspection_id, active)',
        'CREATE INDEX IF NOT EXISTS idx_device_vocabulary_alias_lookup ON device_vocabulary_alias (field_name, source_key, active)',
        'CREATE INDEX IF NOT EXISTS idx_device_vocabulary_review_status ON device_vocabulary_review (status, field_name)',
        'CREATE INDEX IF NOT EXISTS idx_device_media_device ON device_media (device_id, created_at)',
        'CREATE INDEX IF NOT EXISTS idx_room_media_room ON room_media (room_id, created_at)',
        'CREATE INDEX IF NOT EXISTS idx_structure_media_entity ON structure_media (structure_type, structure_id, created_at)',
        'CREATE INDEX IF NOT EXISTS idx_device_media_inspection ON device_media (inspection_id, created_at)',
        'CREATE INDEX IF NOT EXISTS idx_device_draft_media_owner ON device_draft_media (owner_user_id, expires_at)',
        'CREATE INDEX IF NOT EXISTS idx_inspection_companion_inspection ON inspection_companion_session (inspection_id, state, expires_at)',
        'CREATE INDEX IF NOT EXISTS idx_inspection_companion_item_session ON inspection_companion_item (session_id, status, id)',
    ];

    foreach ($statements as $statement) {
        if ($isMysql) {
            $statement = str_replace('INTEGER PRIMARY KEY AUTOINCREMENT', 'INTEGER PRIMARY KEY AUTO_INCREMENT', $statement);
            // MySQL does not support SQLite's IF NOT EXISTS syntax for indexes.
            if (str_starts_with(strtoupper(trim($statement)), 'CREATE INDEX IF NOT EXISTS')) continue;
        }
        R::exec($statement);
    }

    DisplayPreferenceService::migrateLegacyRows();

    // Room photos existed before photos became available on every structure level.
    // Keep their files and metadata, but expose them through the common gallery.
    if (R::count('room_media') > 0) {
        R::exec("INSERT INTO structure_media (structure_type, structure_id, media_type, caption, path, original_name, mime, bytes, created_by, created_at)
            SELECT 'room', room_id, media_type, caption, path, original_name, mime, bytes, created_by, created_at
            FROM room_media legacy
            WHERE NOT EXISTS (SELECT 1 FROM structure_media current WHERE current.structure_type = 'room' AND current.path = legacy.path)");
    }

    $answerColumns = R::getColumns('inspection_answer');
    if (!isset($answerColumns['remark'])) R::exec("ALTER TABLE inspection_answer ADD COLUMN remark TEXT NOT NULL DEFAULT ''");
    $draftMediaColumns = R::getColumns('device_draft_media');
    if (!isset($draftMediaColumns['proposal_json'])) R::exec("ALTER TABLE device_draft_media ADD COLUMN proposal_json TEXT NOT NULL DEFAULT ''");

    if (class_exists(Migrator::class)) {
        Migrator::mark('schema_migration', 1);
    } else {
        // Keep an older deployed ceneos-php-base usable during rolling
        // deployments; the next Composer update will use the shared ledger.
        R::exec('CREATE TABLE IF NOT EXISTS schema_migration (id INTEGER PRIMARY KEY AUTOINCREMENT, migration_key TEXT NOT NULL UNIQUE, applied_at TEXT NOT NULL)');
        $insert = $isMysql ? 'INSERT IGNORE' : 'INSERT OR IGNORE';
        R::exec($insert . " INTO schema_migration (migration_key, applied_at) VALUES ('schema_migration', ?)", [date(DATE_ATOM)]);
    }

    $columns = [
        'site' => ['code' => "TEXT NOT NULL DEFAULT ''", 'description' => 'TEXT NULL', 'comment' => 'TEXT NULL', 'metadata_json' => 'TEXT NULL'],
        'building' => ['code' => "TEXT NOT NULL DEFAULT ''", 'description' => 'TEXT NULL', 'comment' => 'TEXT NULL', 'metadata_json' => 'TEXT NULL'],
        'floor' => ['code' => "TEXT NOT NULL DEFAULT ''", 'sort_order' => 'INTEGER NOT NULL DEFAULT 0', 'room_code_pattern' => "TEXT NOT NULL DEFAULT ''", 'description' => 'TEXT NULL', 'comment' => 'TEXT NULL', 'metadata_json' => 'TEXT NULL'],
        'area' => ['description' => 'TEXT NULL'],
        'room' => ['area_id' => 'INTEGER NULL', 'number' => "TEXT NOT NULL DEFAULT ''", 'description' => 'TEXT NULL', 'comment' => 'TEXT NULL', 'metadata_json' => 'TEXT NULL'],
        'device' => ['description' => 'TEXT NULL', 'comment' => 'TEXT NULL', 'metadata_json' => 'TEXT NULL', 'external_number' => "TEXT NOT NULL DEFAULT ''", 'legacy_number' => "TEXT NOT NULL DEFAULT ''", 'storage_slot' => "TEXT NOT NULL DEFAULT ''", 'room_snapshot' => "TEXT NOT NULL DEFAULT ''", 'device_model' => 'TEXT NULL', 'manufacturer' => 'TEXT NULL', 'warming_device' => 'INTEGER NOT NULL DEFAULT 0', 'archived_at' => 'TEXT NULL'],
        'inspection_catalog_version' => ['inspection_type_code' => "TEXT NOT NULL DEFAULT 'electrical'"],
        'inspection' => [
            'status' => "TEXT NOT NULL DEFAULT 'in_progress'",
            'classification' => "TEXT NOT NULL DEFAULT ''",
            'catalog_version_id' => 'INTEGER NULL',
            'result_reason_code' => "TEXT NOT NULL DEFAULT ''",
            'result_reason_text' => "TEXT NOT NULL DEFAULT ''",
            'metadata_notes' => "TEXT NOT NULL DEFAULT ''",
            'protection_class' => "TEXT NOT NULL DEFAULT ''",
            'inspection_type' => "TEXT NOT NULL DEFAULT ''",
            'examiner' => "TEXT NOT NULL DEFAULT ''",
            'warming_device_snapshot' => 'INTEGER NOT NULL DEFAULT 0',
            'cable_length_m' => 'REAL NULL',
            'rsl_limit_ohm' => 'REAL NULL',
            'csv_row_json' => 'TEXT NULL',
            'regie_reason' => "TEXT NOT NULL DEFAULT ''",
            'regie_minutes' => 'INTEGER NOT NULL DEFAULT 0',
            'legacy_number' => "TEXT NOT NULL DEFAULT ''",
            'billable' => 'INTEGER NOT NULL DEFAULT 0',
            'billing_exported_at' => 'TEXT NULL',
            'billing_export_id' => "TEXT NOT NULL DEFAULT ''",
            'billing_exported_by' => "TEXT NOT NULL DEFAULT ''",
            'billing_eligibility' => "TEXT NOT NULL DEFAULT 'not_billable'",
            'billing_not_billable_reason' => "TEXT NOT NULL DEFAULT ''",
            'billing_not_billable_comment' => "TEXT NOT NULL DEFAULT ''",
            'billing_status' => "TEXT NOT NULL DEFAULT 'not_exported'",
            'billing_active_invoice_item_id' => 'INTEGER NULL',
            'billing_last_error' => "TEXT NOT NULL DEFAULT ''",
            'inspection_type_code' => "TEXT NOT NULL DEFAULT 'electrical'",
            'device_attributes_snapshot_json' => "TEXT NOT NULL DEFAULT '{}'",
            'failed_action' => "TEXT NOT NULL DEFAULT ''",
            'customer_hint' => "TEXT NOT NULL DEFAULT ''",
        ],
        'oauthuser_customer' => ['include_descendants' => 'INTEGER NOT NULL DEFAULT 1'],
        'customer' => [
            'code' => "TEXT NOT NULL DEFAULT ''", 'room_code_pattern' => "TEXT NOT NULL DEFAULT 'auto'", 'description' => 'TEXT NULL', 'comment' => 'TEXT NULL', 'metadata_json' => 'TEXT NULL',
            'sevdesk_customer_id' => "TEXT NOT NULL DEFAULT ''", 'sevdesk_customer_number' => "TEXT NOT NULL DEFAULT ''", 'tenant_id' => 'INTEGER NOT NULL DEFAULT 0',
        ],
        'customerinfo' => ['customer_id' => 'INTEGER NOT NULL DEFAULT 0', 'title' => "TEXT NOT NULL DEFAULT ''", 'slug' => "TEXT NOT NULL DEFAULT ''", 'markdown' => "TEXT NOT NULL DEFAULT ''", 'file_path' => "TEXT NOT NULL DEFAULT ''", 'file_name' => "TEXT NOT NULL DEFAULT ''", 'file_mime' => "TEXT NOT NULL DEFAULT ''", 'created_at' => 'TEXT NULL', 'updated_at' => 'TEXT NULL'],
        'billing_invoice' => ['customer_id' => 'INTEGER NULL', 'tenant_id' => 'INTEGER NULL', 'sevdesk_invoice_id' => "TEXT NOT NULL DEFAULT ''", 'sevdesk_invoice_number' => "TEXT NOT NULL DEFAULT ''", 'sevdesk_url' => "TEXT NOT NULL DEFAULT ''", 'invoice_number' => "TEXT NOT NULL DEFAULT ''", 'invoice_date' => 'TEXT NULL', 'status' => "TEXT NOT NULL DEFAULT 'draft'", 'error_details' => "TEXT NOT NULL DEFAULT ''", 'created_by' => 'INTEGER NULL', 'exported_by' => "TEXT NOT NULL DEFAULT ''", 'exported_at' => 'TEXT NULL', 'amount_net' => 'REAL NULL', 'amount_gross' => 'REAL NULL', 'created_at' => 'TEXT NULL', 'updated_at' => 'TEXT NULL'],
        'billing_invoice_item' => ['invoice_id' => 'INTEGER NOT NULL', 'inspection_id' => 'INTEGER NOT NULL', 'device_id' => 'INTEGER NOT NULL', 'quantity' => 'REAL NOT NULL DEFAULT 1', 'description' => "TEXT NOT NULL DEFAULT ''", 'active' => 'INTEGER NOT NULL DEFAULT 1', 'assigned_at' => 'TEXT NULL', 'deactivated_at' => 'TEXT NULL', 'deactivation_reason' => "TEXT NOT NULL DEFAULT ''", 'source' => "TEXT NOT NULL DEFAULT 'sevdesk'", 'created_at' => 'TEXT NULL'],
        'billing_export' => ['idempotency_key' => "TEXT NOT NULL DEFAULT ''", 'tenant_id' => 'INTEGER NULL', 'owner_user_id' => 'INTEGER NULL', 'status' => "TEXT NOT NULL DEFAULT 'pending'", 'inspection_ids_json' => "TEXT NOT NULL DEFAULT '[]'", 'invoice_id' => 'INTEGER NULL', 'sevdesk_invoice_id' => "TEXT NOT NULL DEFAULT ''", 'sevdesk_invoice_number' => "TEXT NOT NULL DEFAULT ''", 'error_details' => "TEXT NOT NULL DEFAULT ''", 'created_at' => 'TEXT NULL', 'updated_at' => 'TEXT NULL'],
        'cron_log' => ['run_at' => 'TEXT NOT NULL', 'level' => "TEXT NOT NULL DEFAULT 'info'", 'message' => "TEXT NOT NULL DEFAULT ''"],
        'userdisplaypreference' => ['font_weight' => "TEXT NOT NULL DEFAULT 'standard'", 'font_family' => "TEXT NOT NULL DEFAULT 'system'"],
    ];
    foreach ($columns as $table => $definitions) {
        $existing = R::getColumns($table);
        foreach ($definitions as $column => $definition) {
            if (!array_key_exists($column, $existing)) {
                R::exec("ALTER TABLE {$table} ADD COLUMN {$column} {$definition}");
            }
        }
    }
    R::exec("UPDATE room SET number = name WHERE number = ''");
    R::exec('CREATE INDEX IF NOT EXISTS idx_room_area ON room (area_id)');
    R::exec('CREATE INDEX IF NOT EXISTS idx_inspection_billing_status ON inspection (billing_eligibility, billing_status)');
    R::exec('CREATE INDEX IF NOT EXISTS idx_inspection_result ON inspection (result_status, test_date)');
    R::exec('CREATE INDEX IF NOT EXISTS idx_inspection_classification ON inspection (classification, test_date)');
    R::exec('CREATE INDEX IF NOT EXISTS idx_billing_invoice_item_inspection ON billing_invoice_item (inspection_id, active)');
    R::exec('CREATE INDEX IF NOT EXISTS idx_billing_export_status ON billing_export (status, updated_at)');
    R::exec('CREATE INDEX IF NOT EXISTS idx_device_finding_open ON device_finding (device_id, state, blocked)');
    R::exec('CREATE INDEX IF NOT EXISTS idx_user_qualification_requirement ON user_qualification (oauthuser_id, requirement_code, expires_at)');
    // Earlier CSV imports accidentally copied the source column
    // “Bezeichnung” (normally e.g. “Klasse I”) into the measurement table.
    // The immutable raw CSV remains available; only this derived display row
    // is removed so it can neither confuse users nor affect evaluation.
    if (get_app_config('inspection_measurement_metadata_cleanup_v1') !== '1') {
        R::exec("DELETE FROM inspection_measurement WHERE UPPER(TRIM(COALESCE(measurement_key, ''))) IN ('BEZEICHNUNG', 'PRÜFART', 'PRUEFART')");
        set_app_config('inspection_measurement_metadata_cleanup_v1', '1');
    }
    if (get_app_config('billing_v1_initialized') !== '1') {
        R::exec("UPDATE inspection SET billing_eligibility = CASE WHEN billable = 1 THEN 'billable' ELSE 'not_billable' END, billing_status = CASE WHEN billing_exported_at IS NULL OR billing_exported_at = '' THEN 'not_exported' ELSE 'exported' END WHERE billing_eligibility IS NULL OR billing_eligibility = '' OR billing_status IS NULL OR billing_status = ''");
        set_app_config('billing_v1_initialized', '1');
    }
    seed_inspection_catalog();
    seed_inspection_types();
}

/** Seed the immutable first catalog version. Text can later be superseded in the GUI. */
function seed_inspection_catalog(): void
{
    $versionId = (int) R::getCell('SELECT id FROM inspection_catalog_version WHERE code = ?', ['electrical-v1']);
    if ($versionId <= 0) {
        R::exec(
            'INSERT INTO inspection_catalog_version (code, name, inspection_type_code, active, locked_at, created_at) VALUES (?, ?, ?, 1, ?, ?)',
            ['electrical-v1', 'Elektroprüfung Version 1', 'electrical', date(DATE_ATOM), date(DATE_ATOM)]
        );
        $versionId = (int) R::getCell('SELECT id FROM inspection_catalog_version WHERE code = ?', ['electrical-v1']);
    }
    if ((int) R::getCell('SELECT COUNT(*) FROM inspection_catalog_item WHERE version_id = ?', [$versionId]) > 0) return;
    $items = [
        ['identification', 'Inventarisierung', 'Ist das Prüfobjekt eindeutig identifiziert?', 'Gerätenummer, Geräteart und Anschluss sind eindeutig zugeordnet.', 'boolean', '', '', 10],
        ['visual_label', 'Sichtprüfung', 'Sind keine Schäden an der Beschriftung erkennbar?', 'Beschriftungen sind vollständig und eindeutig erkennbar.', 'boolean', '', '', 20],
        ['visual_cable', 'Sichtprüfung', 'Sind keine Schäden an der Anschlussleitung erkennbar?', 'Leitung, Stecker und Zugentlastung sind unbeschädigt.', 'boolean', '', '', 30],
        ['visual_housing', 'Sichtprüfung', 'Sind keine Abweichungen am Gehäuse erkennbar?', 'Gehäuse und Kühlluftöffnungen sind intakt und sauber.', 'boolean', '', '', 40],
        ['rpe', 'Messung', 'Messung des Schutzleiterwiderstands RPE/RSL.', 'Der Grenzwert wird serverseitig anhand der Kabellänge berechnet.', 'measurement', 'RPE', 'rsl_by_cable_length_v1', 50],
        ['riso', 'Messung', 'Messung des Isolationswiderstands RISO.', 'Mindestens 1 MΩ, bei Wärmegeräten mindestens 0,3 MΩ.', 'measurement', 'RISO', 'riso_heating_v1', 60],
        ['ipe', 'Messung', 'Messung des Schutzleiterstroms IPE.', 'Höchstens 3,5 mA.', 'measurement', 'IPE', 'ipe_v1', 70],
        ['iber', 'Messung', 'Messung des Berührungsstroms IB.', 'Höchstens 0,5 mA.', 'measurement', 'IBER', 'iber_v1', 80],
        ['function', 'Funktionsprüfung', 'Arbeiten alle sicherheitsrelevanten Funktionen ordnungsgemäß?', 'Es sind keine sicherheitsrelevanten Abweichungen erkennbar.', 'boolean', '', '', 90],
        ['safe_operation', 'Abschluss', 'Ist ein sicherer Betrieb bis zur nächsten Prüfung zu erwarten?', 'Ein sicherer Betrieb ist bis zur nächsten Prüfung zu erwarten.', 'boolean', '', '', 100],
        ['customer_notice', 'Abschluss', 'Sind alle erforderlichen Hinweise an den Auftraggeber dokumentiert?', 'Abweichungen und erforderliche Hinweise sind vollständig dokumentiert.', 'boolean', '', '', 110],
    ];
    foreach ($items as [$key, $category, $question, $criterion, $type, $measurement, $rule, $sort]) {
        R::exec(
            'INSERT INTO inspection_catalog_item (version_id, item_key, category, question, criterion, input_type, measurement_key, required, applies_to_json, rule_key, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?)',
            [$versionId, $key, $category, $question, $criterion, $type, $measurement, '{}', $rule, $sort]
        );
    }
}

/** Seed the extensible inspection-type catalogue without changing existing records. */
function seed_inspection_types(): void
{
    $now = date(DATE_ATOM);
    foreach ([
        ['electrical', 'Elektroprüfung', 'fa-bolt', 365, 10],
        ['ladder', 'Leitern & Tritte', 'fa-stairs', 365, 20],
    ] as [$code, $name, $icon, $interval, $sort]) {
        if ((int) R::getCell('SELECT id FROM inspection_type WHERE code = ?', [$code]) === 0) {
            R::exec('INSERT INTO inspection_type (code, name, icon, default_interval_days, active, sort_order, created_at, updated_at) VALUES (?, ?, ?, ?, 1, ?, ?, ?)', [$code, $name, $icon, $interval, $sort, $now, $now]);
        } elseif ($code === 'ladder') {
            // Font Awesome does not ship a fa-ladder icon; keep existing installations consistent.
            R::exec('UPDATE inspection_type SET icon = ?, updated_at = ? WHERE code = ?', ['fa-stairs', $now, $code]);
        }
    }
    // Existing installations were seeded with inactive requirements while the
    // qualification workflow was being introduced. Activate the safety gates
    // once; later Superadmin changes in the GUI remain authoritative.
    if (get_app_config('inspection_requirements_v1_activated') !== '1') {
        R::exec("UPDATE inspection_type_requirement SET active = 1 WHERE code IN ('electrical_basic', 'ladder_basic')");
        set_app_config('inspection_requirements_v1_activated', '1');
    }
    // VEFK is a tenant responsibility/assignment, not a personal document type.
    foreach ([
        ['electrical', 'electrical_basic', 'Elektroprüfer-Befähigung', 700, 1, 10],
        ['ladder', 'ladder_basic', 'Befähigte Person Leitern/Tritte', 700, 1, 10],
    ] as [$type, $code, $name, $validity, $confirmation, $sort]) {
        if ((int) R::getCell('SELECT id FROM inspection_type_requirement WHERE inspection_type_code = ? AND code = ?', [$type, $code]) === 0) {
            R::exec('INSERT INTO inspection_type_requirement (inspection_type_code, code, name, validity_days, requires_confirmation, active, sort_order) VALUES (?, ?, ?, ?, ?, 1, ?)', [$type, $code, $name, $validity, $confirmation, $sort]);
        }
    }
    // VEFK is a tenant responsibility/assignment, not a personal document
    // type. Remove the obsolete personal requirement and its old links.
    R::exec("DELETE FROM user_qualification WHERE requirement_code = 'electrical_vefk'");
    R::exec("DELETE FROM inspection_type_requirement WHERE code = 'electrical_vefk'");
    set_app_config('electrical_vefk_document_type_removed', '1');
    // A qualification is the durable permission; follow-up trainings belong
    // to that qualification instead of appearing as a second permission.
    R::exec("UPDATE user_qualification SET requirement_code = 'electrical_basic' WHERE requirement_code = 'electrical_instruction'");
    R::exec("UPDATE user_qualification SET requirement_code = 'ladder_basic' WHERE requirement_code = 'ladder_instruction'");
    R::exec("DELETE FROM inspection_type_requirement WHERE code IN ('electrical_instruction', 'ladder_instruction')");
    if (get_app_config('qualification_followup_model_v1') !== '1') {
        R::exec("UPDATE inspection_type_requirement SET validity_days = 700 WHERE code IN ('electrical_basic', 'ladder_basic') AND (validity_days IS NULL OR validity_days = 0)");
        foreach (R::getAll("SELECT id, instruction_certificates_json FROM oauthuser WHERE instruction_certificates_json IS NOT NULL AND instruction_certificates_json <> ''") as $profile) {
            $certificates = json_decode((string) ($profile['instruction_certificates_json'] ?? ''), true);
            if (!is_array($certificates)) continue;
            foreach ($certificates as $certificate) {
                $followups = is_array($certificate['followups'] ?? null) ? $certificate['followups'] : [];
                usort($followups, static fn(array $a, array $b): int => strcmp((string) ($b['date'] ?? ''), (string) ($a['date'] ?? '')));
                $latest = trim((string) ($followups[0]['date'] ?? ''));
                if ($latest === '') continue;
                foreach (array_values(array_filter(array_map('intval', (array) ($certificate['qualification_ids'] ?? [])))) as $qualificationId) {
                    $days = (int) R::getCell('SELECT r.validity_days FROM inspection_type_requirement r JOIN user_qualification q ON q.requirement_code = r.code WHERE q.id = ? LIMIT 1', [$qualificationId]);
                    if ($days > 0) R::exec('UPDATE user_qualification SET expires_at = ?, updated_at = ? WHERE id = ? AND oauthuser_id = ?', [date('Y-m-d', strtotime($latest . ' +' . $days . ' days')), date(DATE_ATOM), $qualificationId, (int) $profile['id']]);
                }
            }
        }
        set_app_config('qualification_followup_model_v1', '1');
    }
    $ladderCatalog = (int) R::getCell('SELECT id FROM inspection_catalog_version WHERE code = ?', ['ladder-v1']);
    if ($ladderCatalog > 0) return;
    R::exec('INSERT INTO inspection_catalog_version (code, name, inspection_type_code, active, locked_at, created_at) VALUES (?, ?, ?, 1, ?, ?)', ['ladder-v1', 'Leiterprüfung Version 1', 'ladder', $now, $now]);
    $ladderCatalog = (int) R::getCell('SELECT id FROM inspection_catalog_version WHERE code = ?', ['ladder-v1']);
    $items = [
        ['general_state', 'Allgemeiner Zustand', 'Ist die Leiter sauber, unbeschädigt und standsicher?', 'Keine Beschädigungen, Verschmutzungen oder instabilen Bauteile.', 10],
        ['rails', 'Holme und Stützschenkel', 'Sind Holme und Stützschenkel frei von Verformungen, Rissen und scharfen Kanten?', 'Keine Verformungen, Beschädigungen oder Verletzungsgefahr.', 20],
        ['rungs', 'Sprossen und Stufen', 'Sind Sprossen, Stufen und Plattformen sicher und trittsicher?', 'Verbindung zum Holm und Trittflächen sind intakt.', 30],
        ['spreaders', 'Spreizsicherung', 'Ist die Spreizsicherung vollständig, befestigt und funktionsfähig?', 'Spreizsicherung ist bei der Leiterart vorhanden und funktionsfähig.', 40],
        ['fittings', 'Beschlagteile', 'Sind Beschlagteile vollständig und frei von Beschädigung oder Korrosion?', 'Befestigungen und bewegliche Teile sind funktionsfähig.', 50],
        ['feet', 'Leiterfüße und Rollen', 'Sind Füße, Rollen und Zusatzteile für den Untergrund geeignet?', 'Keine Abnutzung oder Beschädigung mit Beeinträchtigung der Standsicherheit.', 60],
        ['extensions', 'Ausschiebbare Teile', 'Funktionieren Einrastung, Zugseil und Rollenführung?', 'Nur bei ausziehbaren Leiterarten erforderlich.', 70],
        ['markings', 'Kennzeichnung', 'Sind Kennzeichnungen und Sicherheitsinformationen vorhanden?', 'Betriebsanleitung/Piktogramme sind erkennbar.', 80],
        ['overall', 'Gesamtbeurteilung', 'Ist die Leiter verwendungsfähig?', 'Gesamtbeurteilung mit dokumentierter Gefahrenstufe.', 90],
    ];
    foreach ($items as [$key, $category, $question, $criterion, $sort]) {
        R::exec('INSERT INTO inspection_catalog_item (version_id, item_key, category, question, criterion, input_type, required, applies_to_json, sort_order) VALUES (?, ?, ?, ?, ?, ?, 1, ?, ?)', [$ladderCatalog, $key, $category, $question, $criterion, 'finding', '{}', $sort]);
    }
}

function config_value(string $name): ?string
{
    return Config::string($name);
}

function app_storage_namespace(): string
{
    $configured = config_value('APP_STORAGE_NAMESPACE') ?? config_value('APP_INSTANCE_ID');

    if ($configured !== null) {
        $sanitized = preg_replace('/[^a-z0-9._-]+/i', '_', strtolower($configured));
        $sanitized = trim((string) $sanitized, '._-');
        if ($sanitized !== '') {
            return $sanitized;
        }
    }

    return 'pruefapp';
}

function app_data_root(): string
{
    $configured = config_value('APP_DATA_ROOT');
    if (is_string($configured) && trim($configured) !== '') return rtrim(trim($configured), '/');
    return '/var/www/data/pruefapp';
}

function app_write_import_log(string $type, array $stats): void
{
    $root = app_data_root() . '/import-logs';
    if (!is_dir($root)) @mkdir($root, 0770, true);
    @file_put_contents($root . '/' . date('Ymd-His') . '-' . bin2hex(random_bytes(3)) . '.json', json_encode(['created_at' => date(DATE_ATOM), 'type' => $type, 'stats' => $stats], JSON_UNESCAPED_UNICODE), LOCK_EX);
}

function app_session_cookie_name(): string
{
    $configured = config_value('APP_SESSION_NAME');
    if ($configured !== null && preg_match('/^[A-Za-z][A-Za-z0-9_-]*$/', $configured) === 1) {
        return $configured;
    }

    $namespace = app_storage_namespace();
    $hash = substr(sha1($namespace), 0, 8);

    return 'pruefapp_' . $hash;
}

function configure_session(): void
{
    if (session_status() !== PHP_SESSION_NONE) {
        return;
    }

    session_name(app_session_cookie_name());
}

require_once __DIR__ . '/version.php';

function base_path(): string
{
    static $basePath = null;

    if ($basePath !== null) {
        return $basePath;
    }

    // Cron/CLI jobs have no public request script. Without an explicit base
    // path PHP would derive URLs from bin/cron.php and create links such as
    // /var/www/html/pruefapp/bin/downloads. Prefer the shared configuration
    // and otherwise derive the deployed application mount from its directory.
    $configured = class_exists(Config::class) ? Config::string('APP_BASE_PATH') : null;
    if ($configured !== null && $configured !== '') {
        $basePath = '/' . trim(str_replace('\\', '/', $configured), '/');
        return $basePath === '/' ? '' : $basePath;
    }
    if (PHP_SAPI === 'cli') {
        $applicationName = basename(dirname(__DIR__));
        $basePath = $applicationName !== '' && $applicationName !== '.' && $applicationName !== DIRECTORY_SEPARATOR
            ? '/' . $applicationName
            : '';
        return $basePath;
    }

    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    $dir = str_replace('\\', '/', dirname($scriptName));

    if ($dir === '/' || $dir === '.' || $dir === '') {
        $dir = '';
    }

    $basePath = rtrim($dir, '/');

    return $basePath;
}

function normalize_request_path(?string $path): string
{
    if ($path === null || $path === '') {
        $path = '/';
    }

    if ($path[0] !== '/') {
        $path = '/' . ltrim($path, '/');
    }

    $base = base_path();
    if ($base !== '') {
        if ($path === $base) {
            $path = '/';
        } elseif (strpos($path, $base . '/') === 0) {
            $path = substr($path, strlen($base));
            if ($path === '') {
                $path = '/';
            }
        }
    }

    if (strpos($path, '/index.php') === 0) {
        $suffix = substr($path, strlen('/index.php'));
        if ($suffix === '' || $suffix === false) {
            $path = '/';
        } else {
            $path = $suffix[0] === '/' ? $suffix : '/' . ltrim($suffix, '/');
        }
    }

    if ($path === '') {
        $path = '/';
    }

    return $path;
}

function sanitize_redirect_target(?string $target): ?string
{
    if ($target === null) {
        return null;
    }

    $target = trim($target);
    if ($target === '') {
        return null;
    }

    if (preg_match('#^[a-z][a-z0-9+.-]*://#i', $target) === 1) {
        return null;
    }

    if (str_starts_with($target, '//')) {
        return null;
    }

    $path = parse_url($target, PHP_URL_PATH);
    $query = parse_url($target, PHP_URL_QUERY);

    $path = normalize_request_path($path);
    if ($path === '') {
        $path = '/';
    }

    $result = $path;
    if ($query !== null && $query !== '') {
        $result .= '?' . $query;
    }

    return $result;
}

function redirect_url_for_target(?string $target): string
{
    if ($target === null || $target === '' || $target === '/') {
        return url_for('kurse');
    }

    [$path, $query] = array_pad(explode('?', $target, 2), 2, '');
    if ($path === '') {
        $path = '/';
    }

    $url = url_for($path);

    if ($query !== '') {
        $url .= (str_contains($url, '?') ? '&' : '?') . $query;
    }

    return $url;
}

function url_for(string $path = ''): string
{
    $base = base_path();

    $normalized = ltrim($path, '/');
    $normalized = $normalized === '' ? '' : '/' . $normalized;

    if ($base === '') {
        return $normalized === '' ? '/' : $normalized;
    }

    return $normalized === '' ? ($base === '' ? '/' : $base) : $base . $normalized;
}

function absolute_url_for(string $path = ''): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

    return sprintf('%s://%s%s', $scheme, $host, url_for($path));
}

/**
 * @param mixed $userInfo
 */
function oidc_userinfo_to_array($userInfo): array
{
    if (is_array($userInfo)) {
        return $userInfo;
    }

    if (is_object($userInfo)) {
        $json = json_encode($userInfo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (is_string($json)) {
            $data = json_decode($json, true);
            if (is_array($data)) {
                return $data;
            }
        }

        return get_object_vars($userInfo);
    }

    return [];
}

function determine_default_role(array $userData): string
{
    $superadminEmails = array_filter(array_map('strtolower', array_map('trim', explode(',', (string) (config_value('APP_SUPERADMIN_EMAILS') ?? '')))));
    $email = strtolower((string) ($userData['email'] ?? ''));
    if ($email !== '' && in_array($email, $superadminEmails, true)) return 'superadmin';
    $configuredEmails = config_value('APP_ADMIN_EMAILS') ?? '';
    $emailCandidates = array_filter(array_map('trim', explode(',', $configuredEmails)), static function ($value) {
        return $value !== '';
    });
    $emailCandidates = array_map('strtolower', $emailCandidates);

    if ($email !== '' && in_array($email, $emailCandidates, true)) {
        return 'admin';
    }

    if (R::count('oauthuser', ' role = ? ', ['admin']) === 0) {
        return 'admin';
    }

    return 'user';
}

/**
 * @param mixed $userInfo
 */
function sync_authenticated_user($userInfo): \RedBeanPHP\OODBBean
{
    $data = oidc_userinfo_to_array($userInfo);
    $sub = trim((string) ($data['sub'] ?? ''));

    if ($sub === '') {
        throw new \InvalidArgumentException('OpenID Connect Userinfo enthält keine eindeutige ID.');
    }

    $user = R::findOne('oauthuser', ' sub = ? ', [$sub]);
    $isNew = false;

    if ($user === null) {
        $user = R::dispense('oauthuser');
        $user->sub = $sub;
        $user->created_at = date('c');
        $isNew = true;
    }

    $user->preferred_username = (string) ($data['preferred_username'] ?? ($data['preferredUsername'] ?? ''));
    $user->email = (string) ($data['email'] ?? '');
    $user->given_name = (string) ($data['given_name'] ?? ($data['givenName'] ?? ''));
    $user->family_name = (string) ($data['family_name'] ?? ($data['familyName'] ?? ''));
    $user->name = (string) ($data['name'] ?? trim($user->given_name . ' ' . $user->family_name));
    $user->locale = (string) ($data['locale'] ?? '');
    $user->last_login_at = date('c');
    $user->updated_at = $user->last_login_at;

    if (!isset($user->role) || trim((string) $user->role) === '') {
        $user->role = determine_default_role($data);
    }

    $user->userinfo_json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $user->login_count = (int) ($user->login_count ?? 0) + 1;

    if ($isNew) {
        $user->first_login_at = $user->last_login_at;
    }

    R::store($user);

    return $user;
}

function current_user_id(): ?int
{
    if (!isset($_SESSION['auth_user_id'])) {
        return null;
    }

    $id = (int) $_SESSION['auth_user_id'];

    return $id > 0 ? $id : null;
}

function current_user(): ?\RedBeanPHP\OODBBean
{
    static $cached = false;
    static $user = null;

    if ($cached) {
        return $user;
    }

    $cached = true;

    $userId = current_user_id();
    if ($userId === null) {
        return $user;
    }

    $bean = R::load('oauthuser', $userId);
    if (!$bean->id) {
        unset($_SESSION['auth_user_id'], $_SESSION['user_role']);
        $user = null;

        return null;
    }

    $_SESSION['user_role'] = (string) ($bean->role ?? '');
    $user = $bean;

    return $user;
}

/** Return a readable examiner name while keeping email values as fallback. */
function display_examiner_name(string $value): string
{
    static $cache = [];
    $value = trim($value);
    if ($value === '' || !filter_var($value, FILTER_VALIDATE_EMAIL)) return $value;
    if (array_key_exists($value, $cache)) return $cache[$value];
    try {
        $user = R::findOne('oauthuser', ' LOWER(email) = LOWER(?) ', [$value]);
        $name = trim((string) ($user->name ?? ''));
        return $cache[$value] = $name !== '' ? $name : $value;
    } catch (Throwable) {
        return $cache[$value] = $value;
    }
}

/** Return a safely stored PNG/JPEG signature for a report examiner as a data URI. */
function examiner_signature_data_uri(string $value): string
{
    $value = trim($value);
    if ($value === '') return '';
    try {
        $user = filter_var($value, FILTER_VALIDATE_EMAIL)
            ? R::findOne('oauthuser', ' LOWER(email) = LOWER(?) ', [$value])
            : R::findOne('oauthuser', ' LOWER(name) = LOWER(?) OR LOWER(preferred_username) = LOWER(?) ', [$value, $value]);
        if (!$user || !$user->id) return '';
        $path = trim((string) ($user->report_signature_path ?? ''));
        if ($path === '' || !is_file($path)) return '';
        $root = realpath(app_data_root() . '/user-signatures');
        $real = realpath($path);
        if ($root === false || $real === false || !str_starts_with($real, $root . DIRECTORY_SEPARATOR)) return '';
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($real);
        if (!in_array($mime, ['image/png', 'image/jpeg'], true)) return '';
        $body = file_get_contents($real);
        return is_string($body) && $body !== '' ? 'data:' . $mime . ';base64,' . base64_encode($body) : '';
    } catch (Throwable) {
        return '';
    }
}

/** A report may only be issued once the assigned examiner has a usable signature. */
function examiner_has_report_signature(string $value): bool
{
    return examiner_signature_data_uri($value) !== '';
}

/**
 * SQL predicate for finished current inspections whose examiner profile has a
 * configured signature. File validation remains server-side at render time.
 */
function inspection_report_signature_sql(string $inspectionAlias = 'inspection'): string
{
    $alias = preg_replace('/[^A-Za-z0-9_]/', '', $inspectionAlias) ?: 'inspection';
    return "EXISTS (SELECT 1 FROM oauthuser report_examiner WHERE TRIM(COALESCE(report_examiner.report_signature_path, '')) <> '' AND (LOWER(TRIM(COALESCE(report_examiner.email, ''))) = LOWER(TRIM(COALESCE({$alias}.examiner, ''))) OR LOWER(TRIM(COALESCE(report_examiner.name, ''))) = LOWER(TRIM(COALESCE({$alias}.examiner, ''))) OR LOWER(TRIM(COALESCE(report_examiner.preferred_username, ''))) = LOWER(TRIM(COALESCE({$alias}.examiner, '')))))";
}

function current_user_role(): ?string
{
    $user = current_user();

    if ($user === null) {
        return null;
    }

    $role = (string) ($user->role ?? '');
    // Keep the emergency/bootstrap superadmin available even if an older
    // deployment accidentally stored the account as a plain admin.
    $configured = config_value('APP_SUPERADMIN_EMAILS') ?? '';
    $superadminEmails = array_filter(array_map('strtolower', array_map('trim', explode(',', $configured))));
    $email = strtolower(trim((string) ($user->email ?? '')));
    if ($email !== '' && in_array($email, $superadminEmails, true)) return 'superadmin';

    return $role !== '' ? $role : null;
}

/** Customer IDs visible to the current user. Admins are unrestricted; an unassigned user sees nothing. */
function current_user_customer_ids(): array
{
    if (current_user_has_role('admin')) return array_map('intval', array_keys(R::findAll('customer')));
    $userId = current_user_id();
    if (!$userId) return [];
    $assignments = R::getAll('SELECT customer_id, include_descendants FROM oauthuser_customer WHERE oauthuser_id = ?', [$userId]);
    $visible = [];
    $expandable = [];
    foreach ($assignments as $assignment) {
        $customerId = (int) ($assignment['customer_id'] ?? 0);
        if ($customerId <= 0) continue;
        $visible[$customerId] = true;
        if (!empty($assignment['include_descendants'])) $expandable[$customerId] = true;
    }
    do {
        $added = false;
        foreach (R::findAll('customer', ' parent_customer_id IS NOT NULL ') as $customer) {
            $parentId = (int) $customer->parent_customer_id;
            if (isset($expandable[$parentId]) && !isset($visible[(int) $customer->id])) {
                $visible[(int) $customer->id] = true;
                $expandable[(int) $customer->id] = true;
                $added = true;
            }
        }
    } while ($added);
    return array_map('intval', array_keys($visible));
}

function current_user_can_access_customer(int $customerId): bool
{
    return current_user_has_role('admin') || in_array($customerId, current_user_customer_ids(), true);
}

function device_customer_id($device): int
{
    $room = R::load('room', (int) ($device->room_id ?? 0));
    $floor = R::load('floor', (int) ($room->floor_id ?? 0));
    $building = R::load('building', (int) ($floor->building_id ?? 0));
    $site = R::load('site', (int) ($building->site_id ?? 0));
    return (int) ($site->customer_id ?? 0);
}

function current_user_has_role(string ...$roles): bool
{
    return \Ceneos\PhpBase\Auth\RolePolicy::allows(current_user_role(), ...$roles);
}

function current_user_is_superadmin(): bool
{
    return strtolower((string) current_user_role()) === 'superadmin';
}

/**
 * Customer visibility rules must not use RolePolicy::allows(): administrators
 * intentionally inherit customer permissions there.  This helper answers the
 * different question whether the signed-in account is actually a customer.
 */
function current_user_is_customer(): bool
{
    return strtolower(trim((string) current_user_role())) === 'customer';
}

/**
 * Returns recent background jobs visible to the current user.
 *
 * Superadmins can see all jobs; other users only see jobs they started.
 *
 * @return array<int, array<string, mixed>>
 */
function current_user_background_jobs(int $limit = 8): array
{
    $user = current_user();
    if ($user === null) return [];
    $all = current_user_is_superadmin();
    $jobs = BackgroundJobService::latest(max(100, $limit), $all ? null : (int) ($user->id ?? 0));
    $notifications = \Ceneos\PhpBase\Notification\NotificationRepository::forUser((int) ($user->id ?? 0), 500);
    $notificationsByJob = [];
    foreach ($notifications as $notification) {
        $jobId = (int) ($notification['job_id'] ?? 0);
        if ($jobId > 0) $notificationsByJob[$jobId] = $notification;
    }
    foreach ($jobs as &$job) {
        $job['historical'] = in_array((string) ($job['state'] ?? ''), ['done', 'error', 'cancelled'], true);
        $job['type_label'] = BackgroundJobService::label((string) ($job['type'] ?? ''));
        $job['downloadable'] = ($job['state'] ?? '') === 'done'
            && in_array((string) ($job['type'] ?? ''), ['pdf_zip', 'pdf_bundle', 'inspection_pdf_zip'], true)
            && is_file((string) ($job['output'] ?? ''));
        $notification = $notificationsByJob[(int) ($job['database_id'] ?? 0)] ?? null;
        $job['notification_unread'] = is_array($notification) && trim((string) ($notification['read_at'] ?? '')) === '';
    }
    unset($job);
    return array_slice($jobs, 0, max(1, $limit));
}

/** @return array<int,array<string,mixed>> */
function current_user_notifications(int $limit = 8): array
{
    $user = current_user();
    if ($user === null) return [];
    $rows = \Ceneos\PhpBase\Notification\NotificationRepository::forUser((int) ($user->id ?? 0), max(1, $limit));
    return array_map(static function (array $row): array {
        $job = (int) ($row['job_id'] ?? 0) > 0 ? BackgroundJobService::findById((int) $row['job_id']) : null;
        return [
            'notification_id' => (int) ($row['id'] ?? 0),
            'title' => (string) ($row['title'] ?? 'Benachrichtigung'),
            'message' => (string) ($row['message'] ?? ''),
            'category' => (string) ($row['category'] ?? 'system'),
            'severity' => (string) ($row['severity'] ?? 'info'),
            'action_url' => (string) ($row['action_url'] ?? ''),
            'created_at' => (string) ($row['created_at'] ?? ''),
            'notification_unread' => trim((string) ($row['read_at'] ?? '')) === '',
            'job' => $job,
        ];
    }, $rows);
}

function current_user_can_manage_courses(): bool
{
    return current_user_has_role('admin', 'editor');
}

function current_user_can_manage_participants(): bool
{
    return current_user_has_role('admin', 'editor');
}

/** Rechnungsdaten dürfen nur Administration und Buchhaltung verwalten. */
function current_user_can_manage_billing(): bool
{
    return current_user_has_role('admin', 'accountant');
}

function available_user_roles(): array
{
    return \Ceneos\PhpBase\Auth\RolePolicy::labels();
}

function role_label(string $role): string
{
    return \Ceneos\PhpBase\Auth\RolePolicy::label($role);
}

function keycloak_admin_console_base_url(): ?string
{
    $configured = config_value('APP_KEYCLOAK_ADMIN_CONSOLE_BASE_URL');
    if ($configured === null) {
        $configured = get_app_config('keycloak_admin_console_base_url');
        if (is_string($configured)) {
            $configured = trim($configured);
            if ($configured === '') {
                $configured = null;
            }
        } else {
            $configured = null;
        }
    }

    if ($configured !== null) {
        return rtrim($configured, '/');
    }

    $realm = config_value('APP_KEYCLOAK_REALM') ?? 'koenigsbl.au';

    $serverUrl = config_value('APP_KEYCLOAK_SERVER_URL') ?? 'https://login.koenigsbl.au';
    $serverUrl = rtrim($serverUrl, '/');

    if ($serverUrl === 'https://login.koenigsbl.au') {
        $serverUrl = 'https://keycloak.koenigsbl.au';
    }

    if ($serverUrl === '') {
        return null;
    }

    return $serverUrl . '/admin/master/console/#/realms/' . rawurlencode($realm);
}

function keycloak_account_console_base_url(): ?string
{
    $configured = config_value('APP_KEYCLOAK_ACCOUNT_CONSOLE_BASE_URL');
    if ($configured === null) {
        $configured = get_app_config('keycloak_account_console_base_url');
        if (is_string($configured)) {
            $configured = trim($configured);
            if ($configured === '') {
                $configured = null;
            }
        } else {
            $configured = null;
        }
    }

    if ($configured !== null) {
        return rtrim($configured, '/');
    }

    $serverUrl = config_value('APP_KEYCLOAK_SERVER_URL') ?? 'https://login.koenigsbl.au';
    $realm = config_value('APP_KEYCLOAK_REALM') ?? 'koenigsbl.au';

    $serverUrl = rtrim($serverUrl, '/');
    if ($serverUrl === 'https://login.koenigsbl.au') {
        $serverUrl = 'https://keycloak.koenigsbl.au';
    }

    if ($serverUrl === '') {
        return null;
    }

    return $serverUrl . '/realms/' . rawurlencode($realm) . '/account';
}

function keycloak_user_admin_url(?string $userId): ?string
{
    $userId = trim((string) $userId);
    if ($userId === '') {
        return null;
    }

    $base = keycloak_admin_console_base_url();
    if ($base === null || $base === '') {
        return null;
    }

    if (!preg_match('#/users/?$#', $base)) {
        $base = rtrim($base, '/') . '/users';
    }

    return $base . '/' . rawurlencode($userId);
}

function render_oidc_error_response(?\Throwable $throwable = null): void
{
    if ($throwable !== null) {
        error_log('OIDC authentication failed: ' . $throwable->getMessage());
    }

    $supportContact = config_value('APP_SUPPORT_CONTACT') ?? config_value('APP_SUPPORT_EMAIL');

    $content = render_template('auth_error.php', [
        'retryUrl' => url_for(),
        'supportContact' => $supportContact,
    ]);

    $body = render_template('layout.php', [
        'title' => 'Anmeldung nicht möglich',
        'content' => $content,
    ]);

    http_response_code(503);
    echo $body;
    exit;
}

function initialisiere_oidc(bool $force = false): void
{
    if ($force || !isset($_SESSION['user'])) {
        try {
            $issuerUrl = config_value('APP_OIDC_ISSUER_URL');
            $clientId = config_value('APP_OIDC_CLIENT_ID');
            $clientSecret = config_value('APP_OIDC_CLIENT_SECRET');
            if ($issuerUrl === null || $clientId === null || $clientSecret === null) {
                throw new \RuntimeException('Die OIDC-Konfiguration ist unvollständig.');
            }

            $oidc = new \Jumbojett\OpenIDConnectClient(
                $issuerUrl,
                $clientId,
                $clientSecret
            );
            $oidc->setRedirectURL(config_value('APP_OIDC_REDIRECT_URL') ?? absolute_url_for('callback.php'));
            $oidc->authenticate();

            $userInfo = $oidc->requestUserInfo();
        } catch (\Throwable $throwable) {
            render_oidc_error_response($throwable);
        }

        try {
            $user = sync_authenticated_user($userInfo);
            $_SESSION['user'] = $userInfo;
            $_SESSION['auth_user_id'] = (int) $user->id;
            $_SESSION['user_role'] = (string) ($user->role ?? '');
            try {
                $_SESSION['login_reminders'] = UserReminderService::afterLogin($user);
            } catch (\Throwable $reminderError) {
                error_log('Prüferhinweise konnten beim Login nicht erstellt werden: ' . $reminderError->getMessage());
                $_SESSION['login_reminders'] = [];
            }
        } catch (\Throwable $throwable) {
            unset($_SESSION['user'], $_SESSION['auth_user_id'], $_SESSION['user_role']);
            $_SESSION['fehlermeldung'] = 'Die Anmeldung war nicht erfolgreich. Bitte versuche es erneut oder kontaktiere den Support.';
            header('Location: ' . url_for());
            exit;
        }

        $redirectTarget = $_SESSION['login_redirect_to'] ?? null;
        if (is_string($redirectTarget)) {
            $redirectTarget = sanitize_redirect_target($redirectTarget);
        } else {
            $redirectTarget = null;
        }

        unset($_SESSION['login_redirect_to']);

        $redirectUrl = redirect_url_for_target($redirectTarget);

        header('Location: ' . $redirectUrl);
        exit;
    }

    if (!isset($_SESSION['auth_user_id']) && isset($_SESSION['user'])) {
        try {
            $user = sync_authenticated_user($_SESSION['user']);
            $_SESSION['auth_user_id'] = (int) $user->id;
            $_SESSION['user_role'] = (string) ($user->role ?? '');
        } catch (\Throwable $throwable) {
            unset($_SESSION['auth_user_id'], $_SESSION['user_role']);
        }
    }
}

// CLI tools (for example the electrical inspection importer) must not run the
// browser authentication flow or emit redirects/headers.
if (PHP_SAPI === 'cli') {
    initialize_database();
    return;
}

// Seiten ohne Login-Anforderung
$freieSeiten = ['callback.php', 'login.php', 'logout.php'];
$aktuelleSeite = basename($_SERVER['PHP_SELF']);
$requestPath = normalize_request_path(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH));

$freiePfade = [
    '#^/uebermitteln(/|$)#',
    // A companion token is a short-lived, high-entropy work-session key.
    // Mobile scanners must be able to open it without a second OIDC login;
    // the companion controller validates the token for every request.
    '#^/companion/[a-f0-9]{48}(/|$)#',
    // This diagnostic endpoint performs its own mandatory header-secret
    // verification in AdminController. It must not trigger the interactive
    // login redirect before that check can run.
    '#^/api/debug/inspection$#',
];

$istFreieRoute = false;
foreach ($freiePfade as $pattern) {
    if (preg_match($pattern, $requestPath)) {
        $istFreieRoute = true;
        break;
    }
}

// callback.php braucht OIDC zwingend
if ($aktuelleSeite === 'callback.php') {
    initialisiere_oidc(force: true);
} elseif (!in_array($aktuelleSeite, $freieSeiten) && !$istFreieRoute) {
    if (!isset($_SESSION['user'])) {
        $requestedTarget = sanitize_redirect_target($_SERVER['REQUEST_URI'] ?? null);
        $loginUrl = url_for('login.php');

        if ($requestedTarget !== null && $requestedTarget !== '/') {
            $loginUrl .= (str_contains($loginUrl, '?') ? '&' : '?') . 'redirect=' . rawurlencode($requestedTarget);
        }

        header('Location: ' . $loginUrl);
        exit;
    }

    initialisiere_oidc();
}

initialize_database();
if (isset($_SESSION['auth_user_id'])) {
    current_user();
}

function transliterate_to_ascii(string $value): string
{
    return \Ceneos\PhpBase\Support\TextHelper::transliterate($value);
}

function sanitize_username(string $username): string
{
    return \Ceneos\PhpBase\Support\TextHelper::username($username);
}

function ensure_unique_username(string $base, ?int $excludeId = null): string
{
    $base = trim($base);
    if ($base === '') {
        return '';
    }

    $username = $base;
    $suffix = 1;

    while (true) {
        $params = [$username];
        $condition = ' benutzername = ? ';
        if ($excludeId !== null && $excludeId > 0) {
            $condition .= ' AND id != ? ';
            $params[] = $excludeId;
        }

        if (R::findOne('teilnehmer', $condition, $params) === null) {
            return $username;
        }

        $username = $base . $suffix;
        $suffix++;
    }
}

function generate_username($firstname, $lastname) {
    $first = sanitize_username((string) $firstname);
    $last = sanitize_username((string) $lastname);

    $base = '';
    if ($first !== '') {
        $base .= substr($first, 0, 1);
    }
    $base .= $last;

    if ($base === '') {
        $base = 'teilnehmer';
    }

    return ensure_unique_username($base);
}

function generate_password($length = 10) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!?$%&';
    return substr(str_shuffle(str_repeat($chars, $length)), 0, $length);
}

function normalize_email_address(string $email): string
{
    $email = trim($email);
    if ($email === '') {
        return '';
    }

    $parts = explode('@', $email, 2);
    if (count($parts) !== 2) {
        return '';
    }

    [$local, $domain] = $parts;
    $local = transliterate_to_ascii($local);
    $local = strtolower($local);
    $local = preg_replace("~[^a-z0-9!#\$%&'*+/=?^_`{|}.\~-]+~", '', $local) ?? '';
    $local = trim($local, '.');

    $domain = transliterate_to_ascii($domain);
    $domain = strtolower($domain);
    $domain = preg_replace('~[^a-z0-9.-]+~', '', $domain) ?? '';
    $domain = trim($domain, '.-');

    if ($domain !== '' && function_exists('idn_to_ascii')) {
        $variant = defined('INTL_IDNA_VARIANT_UTS46') ? INTL_IDNA_VARIANT_UTS46 : 0;
        $converted = @idn_to_ascii($domain, 0, $variant);
        if ($converted !== false) {
            $domain = strtolower($converted);
        }
    }

    if ($local === '' || $domain === '') {
        return '';
    }

    return $local . '@' . $domain;
}

function generate_email($username) {
    $localPart = sanitize_username($username);
    if ($localPart === '') {
        return '';
    }

    return $localPart . '@lernen.koenigsbl.au';
}

function render_template($file, $vars = []) {
    global $baseDir;
    static $renderer = null;

    $renderer ??= new \Ceneos\PhpBase\View\TemplateRenderer($baseDir . '/templates');

    $html = $renderer->render((string) $file, (array) $vars);
    return decorate_form_label_icons(decorate_collapsible_icons($html));
}

function decorate_form_label_icons(string $html): string
{
    $icons = [
        '/filter|prüfstatus|auswahl/i' => 'fa-filter',
        '/suche|suchen/i' => 'fa-magnifying-glass',
        '/kunde|firma|mandant/i' => 'fa-building',
        '/gerät|geraet/i' => 'fa-plug',
        '/raum|standort|gebäude|etage/i' => 'fa-location-dot',
        '/nummer|inventar|serien/i' => 'fa-hashtag',
        '/hersteller|modell|typ/i' => 'fa-tag',
        '/^von$/i' => 'fa-calendar-minus',
        '/^bis$/i' => 'fa-calendar-plus',
        '/sortier|sortierung/i' => 'fa-arrow-down-wide-short',
        '/^name$/i' => 'fa-font',
        '/je seite/i' => 'fa-list-ol',
        '/datum|jahr|prüfung/i' => 'fa-calendar-days',
        '/status|ergebnis/i' => 'fa-circle-check',
        '/kommentar|beschreibung|hinweis/i' => 'fa-comment',
        '/datei|anhang|upload/i' => 'fa-paperclip',
        '/passwort|schlüssel/i' => 'fa-key',
        '/farbe|logo|branding/i' => 'fa-palette',
    ];
    return preg_replace_callback('~<label\b([^>]*)>(.*?)</label>~is', static function (array $match) use ($icons): string {
        $attributes = $match[1];
        if (!preg_match('/\bclass\s*=\s*["\']([^"\']*)["\']/i', $attributes, $classMatch)) return $match[0];
        $classes = preg_split('/\s+/', trim($classMatch[1])) ?: [];
        if (!in_array('form-label', $classes, true) && !in_array('form-check-label', $classes, true)) return $match[0];
        if (in_array('visually-hidden', $classes, true) || preg_match('/<i\b[^>]*class\s*=\s*["\'][^"\']*(?:fa-solid|fas|far|fab)/i', $match[2])) return $match[0];
        $text = trim(preg_replace('/\s+/', ' ', strip_tags($match[2])));
        foreach ($icons as $pattern => $icon) {
            if (!preg_match($pattern, $text)) continue;
            return '<label' . $attributes . '><i class="fa-solid ' . $icon . ' me-1 text-body-secondary" aria-hidden="true"></i>' . $match[2] . '</label>';
        }
        return $match[0];
    }, $html) ?? $html;
}

function decorate_collapsible_icons(string $html): string
{
    $icons = [
        '/ereignisprotokoll/i' => 'fa-clock-rotate-left',
        '/neues gerät|gerät anlegen/i' => 'fa-plus-circle',
        '/auswahl|massenaktion/i' => 'fa-list-check',
        '/import/i' => 'fa-file-import',
        '/cron/i' => 'fa-clock',
        '/audit|revision/i' => 'fa-clock-rotate-left',
        '/filter|suche|prüfstatus/i' => 'fa-filter',
        '/abrechnung/i' => 'fa-coins',
        '/hilfe/i' => 'fa-circle-question',
        '/details anzeigen|anzeigen/i' => 'fa-eye',
        '/prüfung|prüfungen/i' => 'fa-clipboard-check',
        '/gerät|geraet/i' => 'fa-plug',
    ];
    return preg_replace_callback('~<summary\b([^>]*)>(.*?)</summary>~is', static function (array $match) use ($icons): string {
        if (preg_match('/<i\b[^>]*class\s*=\s*["\'][^"\']*(?:fa-solid|fas|far|fab)/i', $match[2])) return $match[0];
        $text = trim(preg_replace('/\s+/', ' ', strip_tags($match[2])));
        foreach ($icons as $pattern => $icon) {
            if (!preg_match($pattern, $text)) continue;
            return '<summary' . $match[1] . '><i class="fa-solid ' . $icon . ' me-2 text-body-secondary" aria-hidden="true"></i>' . $match[2] . '</summary>';
        }
        return $match[0];
    }, $html) ?? $html;
}

function forbidden_response(?string $message = null): array
{
    $content = render_template('forbidden.php', [
        'message' => $message ?? 'Du besitzt nicht die erforderlichen Rechte für diese Aktion.',
    ]);

    $body = render_template('layout.php', [
        'title' => 'Zugriff verweigert',
        'content' => $content,
    ]);

    return [403, [], $body];
}

// Gibt HTML für farbig markiertes Passwort zurück
function render_passwort(string $pw): string {
    $html = '';
    foreach (mb_str_split($pw) as $c) {
        $cls = ctype_digit($c) ? 'digit'
             : (ctype_alpha($c) ? 'letter' : 'symbol');
        $html .= '<span class="pw-char ' . $cls . '">' . htmlspecialchars($c) . '</span>';
    }
    return $html;
}

/**
 * @return DateTimeImmutable|null
 */
function create_strict_date(string $format, string $value): ?\DateTimeImmutable
{
    if ($value === '') {
        return null;
    }

    $date = \DateTimeImmutable::createFromFormat('!' . $format, $value);
    if ($date === false) {
        return null;
    }

    $errors = \DateTimeImmutable::getLastErrors();
    if ($errors === false) {
        return $date;
    }

    if (($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0) {
        return null;
    }

    return $date;
}

function normalize_birthdate(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    $german = create_strict_date('d.m.Y', $value);
    if ($german instanceof \DateTimeImmutable) {
        return $german->format('Y-m-d');
    }

    $iso = create_strict_date('Y-m-d', $value);
    if ($iso instanceof \DateTimeImmutable) {
        return $iso->format('Y-m-d');
    }

    return $value;
}

function format_birthdate_for_display(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    $iso = create_strict_date('Y-m-d', $value);
    if ($iso instanceof \DateTimeImmutable) {
        return $iso->format('d.m.Y');
    }

    return $value;
}

function format_datetime_for_display(?string $value, string $format = 'd.m.Y H:i'): string
{
    $value = trim((string) $value);
    if ($value === '') {
        return '';
    }

    try {
        $date = new \DateTimeImmutable($value);
    } catch (\Throwable $throwable) {
        error_log('Failed to parse datetime for display: ' . $throwable->getMessage());

        return $value;
    }

    return $date->format($format);
}

function app_asset_version(): string
{
    static $version;
    if ($version !== null) return $version;
    $base = dirname(__DIR__);
    $version = trim((string) @shell_exec('git -C ' . escapeshellarg($base) . ' rev-parse --short HEAD 2>/dev/null'));
    if ($version === '') $version = (string) @filemtime($base . '/public/js/search-select.js');
    return $version !== '' ? $version : '1';
}
