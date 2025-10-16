<?php

use \RedBeanPHP\R as R;

session_start();

$baseDir = dirname(__DIR__);

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

initialize_database();

function initialize_database(): void
{
    static $initialized = false;

    if ($initialized) {
        return;
    }

    global $baseDir;

    $dbCandidates = [
        $baseDir . '/../../data/moodle_user_gen/db.sqlite',
        $baseDir . '/data/moodle_user_gen/db.sqlite',
        dirname($baseDir) . '/data/moodle_user_gen/db.sqlite',
        $baseDir . '/db.sqlite',
    ];

    $dbPath = null;
    foreach ($dbCandidates as $candidate) {
        $dir = dirname($candidate);
        if (file_exists($candidate) || is_dir($dir)) {
            $dbPath = $candidate;
            break;
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
    R::freeze(false);

    if (method_exists(R::class, 'createRevisionSupport')) {
        try {
            foreach (['nutzer', 'kurs', 'teilnehmer', 'uebermittlungslink', 'oauthuser', 'auditlog'] as $table) {
                R::createRevisionSupport(R::dispense($table));
            }
        } catch (\Throwable $throwable) {
            error_log('Failed to enable RedBean revision support: ' . $throwable->getMessage());
        }
    } else {
        error_log('Failed to enable RedBean revision support: extension createRevisionSupport not available.');
    }

    $initialized = true;
}

function env_value(string $name): ?string
{
    $value = getenv($name);
    if ($value === false) {
        $value = $_ENV[$name] ?? null;
    }

    if ($value === null) {
        return null;
    }

    $value = trim((string) $value);

    return $value === '' ? null : $value;
}

function base_path(): string
{
    static $basePath = null;

    if ($basePath !== null) {
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
    $configuredEmails = getenv('APP_ADMIN_EMAILS') ?: '';
    $emailCandidates = array_filter(array_map('trim', explode(',', $configuredEmails)), static function ($value) {
        return $value !== '';
    });
    $emailCandidates = array_map('strtolower', $emailCandidates);

    $email = strtolower((string) ($userData['email'] ?? ''));
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

function current_user_role(): ?string
{
    $user = current_user();

    if ($user === null) {
        return null;
    }

    $role = (string) ($user->role ?? '');

    return $role !== '' ? $role : null;
}

function current_user_has_role(string ...$roles): bool
{
    $role = current_user_role();
    if ($role === null) {
        return false;
    }

    $role = strtolower($role);
    $roles = array_map('strtolower', $roles);

    if (in_array($role, $roles, true)) {
        return true;
    }

    // Administrator:innen haben automatisch alle Rechte.
    if ($role === 'admin' && !empty($roles)) {
        return true;
    }

    return false;
}

function available_user_roles(): array
{
    return [
        'admin' => 'Administrator/in',
        'user' => 'Betrachter/in',
    ];
}

function role_label(string $role): string
{
    $roles = available_user_roles();
    $normalized = strtolower($role);

    return $roles[$normalized] ?? ucfirst($role);
}

function keycloak_admin_console_base_url(): ?string
{
    $configured = env_value('APP_KEYCLOAK_ADMIN_CONSOLE_BASE_URL');
    if ($configured !== null) {
        return rtrim($configured, '/');
    }

    $serverUrl = env_value('APP_KEYCLOAK_SERVER_URL') ?? 'https://login.koenigsbl.au';
    $realm = env_value('APP_KEYCLOAK_REALM') ?? 'koenigsbl.au';

    $serverUrl = rtrim($serverUrl, '/');
    if ($serverUrl === '') {
        return null;
    }

    return $serverUrl . '/admin/master/console/#/realms/' . rawurlencode($realm);
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

function initialisiere_oidc(bool $force = false): void {

    if ($force || !isset($_SESSION['user'])) {
        $oidc = new \Jumbojett\OpenIDConnectClient(
            'https://login.koenigsbl.au/realms/koenigsbl.au',
            'moodle-user-gen',
            'ThDCoZOf8xzFoGkpzA9AUSzNmDQftNGa'
        );
        $oidc->setRedirectURL(absolute_url_for('callback.php'));
        $oidc->authenticate();

        $userInfo = $oidc->requestUserInfo();

        try {
            $user = sync_authenticated_user($userInfo);
            $_SESSION['user'] = $userInfo;
            $_SESSION['auth_user_id'] = (int) $user->id;
            $_SESSION['user_role'] = (string) ($user->role ?? '');
        } catch (\Throwable $throwable) {
            unset($_SESSION['user'], $_SESSION['auth_user_id'], $_SESSION['user_role']);
            $_SESSION['fehlermeldung'] = 'Die Anmeldung war nicht erfolgreich. Bitte versuche es erneut oder kontaktiere den Support.';
            header('Location: ' . url_for());
            exit;
        }

        header('Location: ' . url_for('kurse'));
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

// Seiten ohne Login-Anforderung
$freieSeiten = ['callback.php', 'login.php', 'logout.php'];
$aktuelleSeite = basename($_SERVER['PHP_SELF']);
$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '/';

$freiePfade = [
    '#^/uebermitteln(/|$)#',
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
    initialisiere_oidc();
}

initialize_database();
if (isset($_SESSION['auth_user_id'])) {
    current_user();
}

function generate_username($firstname, $lastname) {
    $base = strtolower(substr($firstname, 0, 1) . $lastname);
    $username = $base;
    $i = 1;
    while (R::findOne('teilnehmer', ' benutzername = ? ', [$username])) {
        $username = $base . $i;
        $i++;
    }
    return $username;
}

function generate_password($length = 10) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!?$%&';
    return substr(str_shuffle(str_repeat($chars, $length)), 0, $length);
}

function generate_email($username) {
    return $username . '@lernen.koenigsbl.au';
}

function render_template($file, $vars = []) {
    global $baseDir;
    extract($vars);
    ob_start();
    include $baseDir . '/templates/' . $file;
    return ob_get_clean();
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
