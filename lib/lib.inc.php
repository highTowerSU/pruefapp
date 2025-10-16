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

if (!class_exists('RedBeanPHP\\R') && file_exists($baseDir . '/rb.php')) {
    require_once $baseDir . '/rb.php';
}

require_once __DIR__ . '/htmx.php';
require_once __DIR__ . '/router.php';

function initialisiere_oidc(bool $force = false): void {
    $seitenname = basename($_SERVER['PHP_SELF']);

    // Nur bei Bedarf oder wenn keine Session existiert
    if ($force || !isset($_SESSION['user'])) {
        $oidc = new \Jumbojett\OpenIDConnectClient(
            'https://login.koenigsbl.au/realms/koenigsbl.au',
            'moodle-user-gen',
            'ThDCoZOf8xzFoGkpzA9AUSzNmDQftNGa'
        );
        $oidc->setRedirectURL('https://vserver2.koenigsbl.au/moodle_user_gen/callback.php');
        $oidc->authenticate();
        $_SESSION['user'] = $oidc->requestUserInfo();
        header('Location: /kurse');
        exit;
    }
}

// Seiten ohne Login-Anforderung
$freieSeiten = ['uebermitteln.php', 'callback.php', 'login.php', 'logout.php'];
$aktuelleSeite = basename($_SERVER['PHP_SELF']);

// callback.php braucht OIDC zwingend
if ($aktuelleSeite === 'callback.php') {
    initialisiere_oidc(force: true);
} elseif (!in_array($aktuelleSeite, $freieSeiten)) {
    initialisiere_oidc();
}

// DB-Verbindung (weiter wie bisher)
$dbCandidates = [
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
try {
  R::createRevisionSupport(R::dispense("nutzer"));
  R::createRevisionSupport(R::dispense("kurs"));
  R::createRevisionSupport(R::dispense("teilnehmer"));
} catch(Exception $e) {

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
