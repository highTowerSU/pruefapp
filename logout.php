<?php
session_start();
session_destroy();

// Keycloak Logout-URL mit Redirect zurück zur Startseite
$redirect = urlencode('https://vserver5.koenigsbl.au/moodle_user_gen/');
header('Location: https://login.koenigsbl.au/realms/koenigsbl.au/protocol/openid-connect/logout?redirect_uri=' . $redirect);
exit;
