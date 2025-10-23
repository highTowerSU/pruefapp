<?php

require_once __DIR__ . '/lib/lib.inc.php';

if (isset($_SESSION['auth_user_id'])) {
    header('Location: ' . url_for('kurse'));
    exit;
}

$redirectParam = $_GET['redirect'] ?? null;
$redirectTarget = sanitize_redirect_target(is_string($redirectParam) ? $redirectParam : null);

$flashMessage = $_SESSION['fehlermeldung'] ?? null;
if ($flashMessage !== null) {
    unset($_SESSION['fehlermeldung']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postRedirect = sanitize_redirect_target($_POST['redirect'] ?? null);

    if ($postRedirect !== null) {
        $_SESSION['login_redirect_to'] = $postRedirect;
    } else {
        $_SESSION['login_redirect_to'] = '/';
    }

    initialisiere_oidc(force: true);
    exit;
}

if ($redirectTarget !== null) {
    $_SESSION['login_redirect_to'] = $redirectTarget;
}

$branding = get_branding();

$content = render_template('login.php', [
    'branding' => $branding,
    'redirectTarget' => $redirectTarget,
    'flashMessage' => $flashMessage,
]);

$body = render_template('layout.php', [
    'title' => 'Anmeldung',
    'content' => $content,
    'branding' => $branding,
]);

echo $body;
