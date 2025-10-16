<?php
session_start();

require_once 'vendor/autoload.php';
require_once 'lib/htmx.php';
require_once 'lib/router.php';

use \RedBeanPHP\R as R;

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
        header('Location: index.php');
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
R::setup('sqlite:' . __DIR__ . '/../../../data/moodle_user_gen/db.sqlite');
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
    extract($vars);
    ob_start();
    include __DIR__ . '/..//templates/' . $file;
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
