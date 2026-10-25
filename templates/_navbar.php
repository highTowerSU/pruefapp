<?php
$branding = $branding ?? get_branding();
$navColors = $branding['nav_colors'] ?? [];
$navBackgroundColor = $navColors['background'] ?? '#0D6EFD';
$navTextColor = $navColors['text'] ?? '#FFFFFF';
$navStyle = sprintf('--navbar-bg:%s; --navbar-color:%s;', $navBackgroundColor, $navTextColor);
$loginHeaderUser = current_user();
if ($loginHeaderUser === null) {
    $currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $helpUrl = url_for('hilfe');
    $backgroundHex = ltrim((string) $navBackgroundColor, '#');
    if (strlen($backgroundHex) === 3) {
        $backgroundHex = $backgroundHex[0] . $backgroundHex[0] . $backgroundHex[1] . $backgroundHex[1] . $backgroundHex[2] . $backgroundHex[2];
    }
    $luminance = strlen($backgroundHex) === 6
        ? (0.299 * hexdec(substr($backgroundHex, 0, 2)) + 0.587 * hexdec(substr($backgroundHex, 2, 2)) + 0.114 * hexdec(substr($backgroundHex, 4, 2)))
        : 0;
    $logoVariant = $luminance > 150 ? 'light' : 'dark';
    $brandLogo = $branding['logos'][$logoVariant] ?? ($branding['header_logo']['path'] ?? '');
    $brandLogoUrl = $brandLogo === '' ? '' : (preg_match('#^https?://#i', $brandLogo) ? $brandLogo : url_for($brandLogo));
    echo \Ceneos\PhpBase\View\NavbarRenderer::renderLogin([
        'brand' => [
            'url' => url_for(),
            'logo_url' => $brandLogoUrl,
            'logo_alt' => $branding['header_logo']['alt'] ?? ($branding['company_name'] ?? ''),
            'logo_height' => 36,
            'label' => $branding['app_title'] ?? 'Prüfapp',
        ],
        'nav_colors' => ['background' => $navBackgroundColor, 'text' => $navTextColor],
        'nav_items' => [[
            'type' => 'link',
            'label' => 'Hilfe',
            'url' => $helpUrl,
            'icon' => 'fa-solid fa-circle-question',
            'active' => $currentPath === $helpUrl || str_starts_with($currentPath, rtrim($helpUrl, '/') . '/'),
        ]],
        'theme_key' => 'pruefapp-theme',
        'theme_button_class' => 'btn-outline-navbar',
    ]);
    return;
}
?>
<header class="sticky-top app-navbar-shell noprint">
<nav class="navbar navbar-expand-lg navbar-themed" style="<?= htmlspecialchars($navStyle, ENT_QUOTES) ?>">
  <div class="container-fluid app-nav-container">
    <?php
    $currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$coursesUrl = url_for('kurse');
$auditLogUrl = url_for('admin/audit-log');
$userAdminUrl = config_value('APP_CONTROLCENTER_URL') ? rtrim((string) config_value('APP_CONTROLCENTER_URL'), '/') . '/benutzer' : url_for('admin/nutzer');
$settingsUrl = url_for('admin/konfiguration');
$vocabularyUrl = url_for('admin/stammdaten');
$helpUrl = url_for('hilfe');
$structureUrl = url_for('struktur');
$devicesUrl = url_for('geraete');
$inspectionsUrl = url_for('pruefungen');
$inspectionImportUrl = url_for('admin/pruefungen/import');
$billingUrl = url_for('admin/abrechnung');
$companyUrl = url_for('mandanten');
$downloadsUrl = url_for('downloads');
$inspectionTypesUrl = url_for('admin/pruefarten');

$pathIsActive = static function (string $url) use ($currentPath): bool {
    $prefix = rtrim($url, '/');
    return $prefix === '' ? ($currentPath === '/' || $currentPath === '') : ($currentPath === $url || strpos($currentPath, $prefix . '/') === 0);
};

$coursesPrefix = rtrim($coursesUrl, '/');
if ($coursesPrefix === '') {
    $coursesActive = $currentPath === '/' || $currentPath === '';
} else {
    $coursesActive = $currentPath === $coursesUrl || strpos($currentPath, $coursesPrefix . '/') === 0;
}

$auditPrefix = rtrim($auditLogUrl, '/');
if ($auditPrefix === '') {
    $auditActive = $currentPath === '/' || $currentPath === '';
} else {
    $auditActive = $currentPath === $auditLogUrl || strpos($currentPath, $auditPrefix . '/') === 0;
}

$userAdminPrefix = rtrim($userAdminUrl, '/');
if ($userAdminPrefix === '') {
    $userAdminActive = $currentPath === '/' || $currentPath === '';
} else {
    $userAdminActive = $currentPath === $userAdminUrl || strpos($currentPath, $userAdminPrefix . '/') === 0;
}
$settingsPrefix = rtrim($settingsUrl, '/');
if ($settingsPrefix === '') {
    $settingsActive = $currentPath === '/' || $currentPath === '';
} else {
    $settingsActive = $currentPath === $settingsUrl || strpos($currentPath, $settingsPrefix . '/') === 0;
}

$helpPrefix = rtrim($helpUrl, '/');
if ($helpPrefix === '') {
    $helpActive = $currentPath === '/' || $currentPath === '';
} else {
    $helpActive = $currentPath === $helpUrl || strpos($currentPath, $helpPrefix . '/') === 0;
}

$structurePrefix = rtrim($structureUrl, '/');
if ($structurePrefix === '') {
    $structureActive = $currentPath === '/' || $currentPath === '';
} else {
    $structureActive = $currentPath === $structureUrl || strpos($currentPath, $structurePrefix . '/') === 0;
}
$devicesActive = $currentPath === $devicesUrl || strpos($currentPath, rtrim($devicesUrl, '/') . '/') === 0;
$inspectionsActive = $pathIsActive($inspectionsUrl) || strpos($currentPath, rtrim(url_for('admin/pruefungen'), '/') . '/') === 0;
$devicesMenuActive = $devicesActive || $inspectionsActive;
$inspectionImportActive = $pathIsActive($inspectionImportUrl);
$billingActive = $pathIsActive($billingUrl);
$companyActive = $pathIsActive($companyUrl);
$vocabularyActive = $pathIsActive($vocabularyUrl);
$inspectionTypesActive = $pathIsActive($inspectionTypesUrl);
$adminMenuActive = $auditActive || $userAdminActive || $settingsActive || $companyActive || $vocabularyActive || $inspectionTypesActive;

?>
    <a class="navbar-brand d-flex align-items-center gap-2" href="<?= htmlspecialchars(url_for(), ENT_QUOTES) ?>">
      <?php
    $hex = ltrim($navBackgroundColor, '#');
if (strlen($hex) === 3) {
    $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
}
$luminance = strlen($hex) === 6
    ? (0.299 * hexdec(substr($hex, 0, 2)) + 0.587 * hexdec(substr($hex, 2, 2)) + 0.114 * hexdec(substr($hex, 4, 2)))
    : 0;
$logoVariant = $luminance > 150 ? 'light' : 'dark';
$brandLogo = $branding['logos'][$logoVariant] ?? ($branding['header_logo']['path'] ?? '');
?>
      <?php if (!empty($brandLogo)): ?>
        <?php $brandLogoUrl = preg_match('#^https?://#i', $brandLogo) ? $brandLogo : url_for($brandLogo); ?>
        <img src="<?= htmlspecialchars($brandLogoUrl, ENT_QUOTES) ?>"
             alt="<?= htmlspecialchars($branding['header_logo']['alt'] ?? ($branding['company_name'] ?? '')) ?>"
             class="navbar-brand-logo img-fluid" style="max-height:1.5em">
      <?php endif; ?>
      <span><?= htmlspecialchars($branding['nav_brand'] ?? 'Prüf-Doku') ?></span>
    </a>

    <button class="navbar-toggler ms-auto" type="button" data-bs-toggle="collapse" data-bs-target="#appNavbarContent" aria-controls="appNavbarContent" aria-expanded="false" aria-label="Navigation öffnen">
      <i class="fa-solid fa-bars" aria-hidden="true"></i>
    </button>

    <div id="appNavbarContent" class="navbar-collapse collapse d-lg-flex align-items-lg-center ms-lg-auto gap-lg-4 flex-wrap justify-content-lg-end">
      <?php $authUser = current_user(); ?>
      <?php if ($authUser !== null): ?>
        <ul class="navbar-nav align-items-lg-center gap-lg-2 flex-wrap justify-content-end">
          <li class="nav-item"><a href="<?= htmlspecialchars($structureUrl, ENT_QUOTES) ?>" class="nav-link<?= $structureActive ? ' active fw-semibold' : '' ?>"><i class="fa-solid fa-sitemap me-1" aria-hidden="true"></i>Struktur</a></li>
          <li class="nav-item dropdown">
            <button class="nav-link dropdown-toggle border-0 bg-transparent<?= $devicesMenuActive ? ' active fw-semibold' : '' ?>" type="button" id="deviceNavigationDropdown" data-bs-toggle="dropdown" aria-expanded="false"><i class="fa-solid fa-plug me-1" aria-hidden="true"></i>Geräte</button>
            <ul class="dropdown-menu" aria-labelledby="deviceNavigationDropdown">
              <li><a class="dropdown-item" href="<?= htmlspecialchars($devicesUrl . '#device-inspection-lookup', ENT_QUOTES) ?>"><i class="fa-solid fa-barcode icon-slot me-2" aria-hidden="true"></i>Neue Prüfung</a></li>
              <li><hr class="dropdown-divider"></li>
              <li><a class="dropdown-item<?= $devicesActive ? ' active' : '' ?>" href="<?= htmlspecialchars($devicesUrl, ENT_QUOTES) ?>"><i class="fa-solid fa-plug icon-slot me-2" aria-hidden="true"></i>Geräteübersicht</a></li>
              <li><a class="dropdown-item<?= $inspectionsActive ? ' active' : '' ?>" href="<?= htmlspecialchars($inspectionsUrl, ENT_QUOTES) ?>"><i class="fa-solid fa-clipboard-check icon-slot me-2" aria-hidden="true"></i>Prüfungen</a></li>
            </ul>
          </li>
          <?php if (current_user_has_role('admin')): ?>
            <li class="nav-item dropdown">
              <button class="nav-link dropdown-toggle border-0 bg-transparent<?= $inspectionImportActive ? ' active fw-semibold' : '' ?>" type="button" id="importNavigationDropdown" data-bs-toggle="dropdown" aria-expanded="false"><i class="fa-solid fa-file-import me-1" aria-hidden="true"></i>Import</button>
              <ul class="dropdown-menu" aria-labelledby="importNavigationDropdown">
                <li><a class="dropdown-item<?= $inspectionImportActive ? ' active' : '' ?>" href="<?= htmlspecialchars($inspectionImportUrl, ENT_QUOTES) ?>"><i class="fa-solid fa-arrows-rotate icon-slot me-2" aria-hidden="true"></i>Import &amp; Sync</a></li>
                <li><a class="dropdown-item" href="<?= htmlspecialchars($inspectionImportUrl . '?view=measurement', ENT_QUOTES) ?>"><i class="fa-solid fa-vial icon-slot me-2" aria-hidden="true"></i>Messdaten importieren</a></li>
                <?php if (current_user_is_superadmin()): ?>
                  <li><hr class="dropdown-divider"></li>
                  <li><a class="dropdown-item" href="<?= htmlspecialchars($inspectionImportUrl . '#report-regeneration', ENT_QUOTES) ?>"><i class="fa-solid fa-file-pdf icon-slot me-2" aria-hidden="true"></i>Berichte neu erzeugen</a></li>
                <?php endif; ?>
              </ul>
            </li>
          <?php endif; ?>
          <?php if (current_user_can_manage_billing()): ?>
            <li class="nav-item"><a href="<?= htmlspecialchars($billingUrl, ENT_QUOTES) ?>" class="nav-link<?= $billingActive ? ' active fw-semibold' : '' ?>"><i class="fa-solid fa-coins me-1" aria-hidden="true"></i>Abrechnung</a></li>
          <?php endif; ?>
          <?php if (current_user_has_role('admin') || current_user_is_superadmin()): ?>
            <?php $adminMenuId = 'adminNavigationDropdown'; ?>
            <li class="nav-item dropdown">
              <button class="nav-link dropdown-toggle border-0 bg-transparent<?= $adminMenuActive ? ' active fw-semibold' : '' ?>" type="button" id="<?= $adminMenuId ?>" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="fa-solid fa-screwdriver-wrench me-1" aria-hidden="true"></i>Verwaltung
              </button>
              <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="<?= $adminMenuId ?>">
                <li><h6 class="dropdown-header">Administration</h6></li>
                <li><a class="dropdown-item" href="<?= htmlspecialchars($auditLogUrl, ENT_QUOTES) ?>"><i class="fa-solid fa-clock-rotate-left me-2" aria-hidden="true"></i>Audit &amp; Revisionen</a></li>
                <?php if (current_user_has_role('admin')): ?>
                  <li><a class="dropdown-item" href="<?= htmlspecialchars($userAdminUrl, ENT_QUOTES) ?>"><i class="fa-solid fa-users-gear me-2" aria-hidden="true"></i>Benutzer &amp; Rollen</a></li>
                  <?php if (config_value('APP_CONTROLCENTER_URL')): ?><li><a class="dropdown-item" href="<?= htmlspecialchars(url_for('admin/nutzer'), ENT_QUOTES) ?>"><i class="fa-solid fa-user-shield me-2" aria-hidden="true"></i>Prüfapp-Zugriffe &amp; Befähigungen</a></li><?php endif; ?>
                <?php endif; ?>
                <?php if (current_user_has_role('admin')): ?><li><a class="dropdown-item" href="<?= htmlspecialchars(url_for('admin/base'), ENT_QUOTES) ?>"><i class="fa-solid fa-building-user me-2" aria-hidden="true"></i>Mandanten, Gruppen &amp; Rollen</a></li><?php endif; ?>
                <?php if (current_user_is_superadmin()): ?>
                  <li><hr class="dropdown-divider"></li>
                  <li><a class="dropdown-item" href="<?= htmlspecialchars($companyUrl, ENT_QUOTES) ?>"><i class="fa-solid fa-building me-2" aria-hidden="true"></i>Mandanten</a></li>
                  <li><a class="dropdown-item<?= $vocabularyActive ? ' active' : '' ?>" href="<?= htmlspecialchars($vocabularyUrl, ENT_QUOTES) ?>"><i class="fa-solid fa-spell-check me-2" aria-hidden="true"></i>Stammdaten bereinigen</a></li>
                  <li><a class="dropdown-item<?= $inspectionTypesActive ? ' active' : '' ?>" href="<?= htmlspecialchars($inspectionTypesUrl, ENT_QUOTES) ?>"><i class="fa-solid fa-clipboard-check me-2" aria-hidden="true"></i>Prüfarten &amp; Befähigungen</a></li>
                  <li><a class="dropdown-item" href="<?= htmlspecialchars($settingsUrl, ENT_QUOTES) ?>"><i class="fa-solid fa-gear me-2" aria-hidden="true"></i>Konfiguration</a></li>
                <?php endif; ?>
              </ul>
            </li>
          <?php endif; ?>
          <li class="nav-item"><a href="<?= htmlspecialchars($helpUrl, ENT_QUOTES) ?>" class="nav-link<?= $helpActive ? ' active fw-semibold' : '' ?>"><i class="fa-solid fa-circle-question me-1" aria-hidden="true"></i>Hilfe</a></li>
        </ul>
      <?php else: ?>
        <div class="d-flex align-items-center gap-3 flex-wrap justify-content-end">
          <a href="<?= htmlspecialchars($helpUrl, ENT_QUOTES) ?>" class="nav-link px-0<?= $helpActive ? ' active fw-semibold text-decoration-underline' : '' ?>">Hilfe</a>
        </div>
      <?php endif; ?>

      <div class="d-flex align-items-center ms-auto gap-3">
        <?php if ($authUser !== null): ?>
          <?php
      $displayName = $authUser->name ?: ($authUser->preferred_username ?: ($authUser->email ?: 'Nutzer'));
            $roleLabel = current_user_role() ? role_label((string) current_user_role()) : null;
            $userMenuId = 'userMenuDropdown';
            $userManagementUrl = $branding['user_management_url'] ?? null;
            if (empty($userManagementUrl)) {
                $userManagementUrl = config_value('APP_USER_MANAGEMENT_URL');
            }
            if (is_string($userManagementUrl)) {
                $userManagementUrl = trim($userManagementUrl);
                if ($userManagementUrl === '') {
                    $userManagementUrl = null;
                } elseif (!preg_match('#^[a-z]+://#i', $userManagementUrl) && !str_starts_with($userManagementUrl, '//')) {
                    $userManagementUrl = url_for($userManagementUrl);
                }
            } else {
                $userManagementUrl = null;
            }
$keycloakAccountUrl = keycloak_account_console_base_url();
$hasManagementLink = $userManagementUrl !== null && current_user_has_role('admin');
$hasKeycloakLink = $keycloakAccountUrl !== null;
?>
          <div class="d-flex align-items-center gap-2">
            <div class="dropdown navbar-hover-dropdown">
              <button class="btn btn-outline-navbar dropdown-toggle d-flex align-items-center gap-2"
                      type="button"
                      id="<?= htmlspecialchars($userMenuId, ENT_QUOTES) ?>"
                      data-bs-toggle="dropdown"
                      aria-expanded="false"
                      aria-haspopup="true">
                <i class="fa-solid fa-user" aria-hidden="true"></i>
                <span><?= htmlspecialchars($displayName) ?></span>
                <?php if ($roleLabel !== null): ?>
                  <span class="badge text-bg-secondary ms-1" title="Rolle: <?= htmlspecialchars($roleLabel) ?>">
                    <?= htmlspecialchars($roleLabel) ?>
                  </span>
                <?php endif; ?>
              </button>
              <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="<?= htmlspecialchars($userMenuId, ENT_QUOTES) ?>">
                <?= \Ceneos\PhpBase\Auth\SessionRole::form(app_storage_namespace(), (string) current_user_true_role(), url_for()) ?>
                <li>
                  <a class="dropdown-item" href="<?= htmlspecialchars(url_for('profil'), ENT_QUOTES) ?>"><i class="fa-solid fa-user-pen me-2" aria-hidden="true"></i>Mein Profil</a>
                </li>
                <li>
                  <a class="dropdown-item" href="<?= htmlspecialchars(url_for('profil') . '#companion-sessions', ENT_QUOTES) ?>"><i class="fa-solid fa-mobile-screen-button me-2" aria-hidden="true"></i>Companion-Geräte</a>
                </li>
                <li>
                  <a class="dropdown-item" href="<?= htmlspecialchars($downloadsUrl, ENT_QUOTES) ?>"><i class="fa-solid fa-download me-2" aria-hidden="true"></i>Meine Downloads</a>
                </li>
                <?php if (config_value('APP_CONTROLCENTER_URL')): ?><li><a class="dropdown-item" href="<?= htmlspecialchars(rtrim((string) config_value('APP_CONTROLCENTER_URL'), '/') . '/benutzer', ENT_QUOTES) ?>"><i class="fa-solid fa-id-card me-2" aria-hidden="true"></i>Gemeinsames Profil</a></li><?php endif; ?>
                  <?php if ($hasManagementLink): ?>
                  <li>
                    <a class="dropdown-item" href="<?= htmlspecialchars($userManagementUrl, ENT_QUOTES) ?>"><i class="fa-solid fa-users-gear me-2" aria-hidden="true"></i>Nutzerverwaltung</a>
                  </li>
                <?php endif; ?>
                <?php if ($hasKeycloakLink): ?>
                  <li>
                    <a class="dropdown-item" href="<?= htmlspecialchars($keycloakAccountUrl, ENT_QUOTES) ?>" target="_blank" rel="noopener">
                      <i class="fa-solid fa-arrow-up-right-from-square me-2" aria-hidden="true"></i>Keycloak-Konto
                    </a>
                  </li>
                <?php endif; ?>
                <?php if ($hasManagementLink || $hasKeycloakLink): ?>
                  <li><hr class="dropdown-divider"></li>
                <?php endif; ?>
                <li>
                  <a class="dropdown-item" href="<?= htmlspecialchars(url_for('logout.php'), ENT_QUOTES) ?>"><i class="fa-solid fa-right-from-bracket me-2" aria-hidden="true"></i>Logout</a>
                </li>
              </ul>
            </div>
          </div>
        <?php endif; ?>

        <?php if ($authUser !== null): ?>
          <div class="dropdown navbar-hover-dropdown">
            <?= render_template('_notifications_dropdown.php', ['notifications' => current_user_notifications(6), 'downloadsUrl' => $downloadsUrl]) ?>
          </div>
        <?php endif; ?>

        <?= \Ceneos\PhpBase\View\ApplicationSwitcher::render((string)($branding['key'] ?? '')) ?>
        <?= \Ceneos\PhpBase\View\ThemeSwitcher::render('pruefapp-theme', 'btn-outline-navbar') ?>
        <?php if ($authUser !== null): ?><a class="btn btn-outline-navbar" href="<?= htmlspecialchars(url_for('profil') . '#display-preferences', ENT_QUOTES) ?>" title="Darstellung &amp; Barrierefreiheit" aria-label="Darstellung &amp; Barrierefreiheit"><i class="fa-solid fa-universal-access" aria-hidden="true"></i></a><?php endif; ?>
      </div>
    </div>
  </div>
</nav>
</header>
