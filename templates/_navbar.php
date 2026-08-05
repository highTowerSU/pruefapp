<?php
$branding = $branding ?? get_branding();
$navColors = $branding['nav_colors'] ?? [];
$navBackgroundColor = $navColors['background'] ?? '#0D6EFD';
$navTextColor = $navColors['text'] ?? '#FFFFFF';
$navStyle = sprintf('--navbar-bg:%s; --navbar-color:%s;', $navBackgroundColor, $navTextColor);
?>
<nav class="navbar navbar-expand-lg navbar-themed mb-4 noprint" style="<?= htmlspecialchars($navStyle, ENT_QUOTES) ?>">
  <div class="container-fluid app-nav-container">
    <?php
    $currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $coursesUrl = url_for('kurse');
    $auditLogUrl = url_for('admin/audit-log');
    $userAdminUrl = url_for('admin/nutzer');
    $settingsUrl = url_for('admin/konfiguration');
    $helpUrl = url_for('hilfe');
    $structureUrl = url_for('struktur');
    $devicesUrl = url_for('geraete');
    $inspectionImportUrl = url_for('admin/pruefungen/import');
    $billingUrl = url_for('admin/abrechnung');
    $companyUrl = url_for('mandanten');
    $downloadsUrl = url_for('downloads');

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
    $inspectionImportActive = $pathIsActive($inspectionImportUrl);
    $billingActive = $pathIsActive($billingUrl);
    $companyActive = $pathIsActive($companyUrl);
    $adminMenuActive = $auditActive || $userAdminActive || $settingsActive || $companyActive;

    ?>
    <a class="navbar-brand d-flex align-items-center gap-2" href="<?= htmlspecialchars(url_for(), ENT_QUOTES) ?>">
      <?php
        $hex = ltrim($navBackgroundColor, '#');
        if (strlen($hex) === 3) $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
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
          <li class="nav-item"><a href="<?= htmlspecialchars($devicesUrl, ENT_QUOTES) ?>" class="nav-link<?= $devicesActive ? ' active fw-semibold' : '' ?>"><i class="fa-solid fa-plug me-1" aria-hidden="true"></i>Geräte</a></li>
          <?php if (current_user_has_role('admin')): ?>
            <li class="nav-item"><a href="<?= htmlspecialchars($inspectionImportUrl, ENT_QUOTES) ?>" class="nav-link<?= $inspectionImportActive ? ' active fw-semibold' : '' ?>"><i class="fa-solid fa-file-import me-1" aria-hidden="true"></i>Import</a></li>
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
                  <li><a class="dropdown-item" href="<?= htmlspecialchars($userAdminUrl, ENT_QUOTES) ?>"><i class="fa-solid fa-users-gear me-2" aria-hidden="true"></i>Nutzer</a></li>
                <?php endif; ?>
                <?php if (current_user_is_superadmin()): ?>
                  <li><hr class="dropdown-divider"></li>
                  <li><a class="dropdown-item" href="<?= htmlspecialchars($companyUrl, ENT_QUOTES) ?>"><i class="fa-solid fa-building me-2" aria-hidden="true"></i>Mandanten</a></li>
                  <li><a class="dropdown-item" href="<?= htmlspecialchars($settingsUrl, ENT_QUOTES) ?>"><i class="fa-solid fa-gear me-2" aria-hidden="true"></i>Konfiguration</a></li>
                <?php endif; ?>
              </ul>
            </li>
          <?php else: ?>
            <li class="nav-item"><a href="<?= htmlspecialchars($auditLogUrl, ENT_QUOTES) ?>" class="nav-link<?= $auditActive ? ' active fw-semibold' : '' ?>"><i class="fa-solid fa-clock-rotate-left me-1" aria-hidden="true"></i>Audit</a></li>
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
            $roleLabel = !empty($authUser->role) ? role_label((string) $authUser->role) : null;
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
            <div class="dropdown">
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
                <li>
                  <a class="dropdown-item" href="<?= htmlspecialchars(url_for('profil'), ENT_QUOTES) ?>"><i class="fa-solid fa-user-pen me-2" aria-hidden="true"></i>Mein Profil</a>
                </li>
                <li>
                  <a class="dropdown-item" href="<?= htmlspecialchars($downloadsUrl, ENT_QUOTES) ?>"><i class="fa-solid fa-download me-2" aria-hidden="true"></i>Meine Downloads</a>
                </li>
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
          <?php $notifications = current_user_background_jobs(6); $unreadNotifications = array_filter($notifications, static fn(array $entry): bool => !empty($entry['notification_unread'])); ?>
          <div class="dropdown">
            <button class="btn btn-outline-navbar position-relative" type="button" id="notificationsDropdown" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Benachrichtigungen" title="Benachrichtigungen">
              <i class="fa-solid fa-bell" aria-hidden="true"></i>
              <?php if ($unreadNotifications !== []): ?><span class="position-absolute top-0 start-100 translate-middle badge rounded-pill text-bg-danger"><?= count($unreadNotifications) ?><span class="visually-hidden"> ungelesene Aufgaben</span></span><?php endif; ?>
            </button>
            <ul class="dropdown-menu dropdown-menu-end p-2 notification-menu" aria-labelledby="notificationsDropdown">
              <li class="dropdown-header d-flex justify-content-between align-items-center"><span><i class="fa-solid fa-bell me-1" aria-hidden="true"></i>Benachrichtigungen</span><a class="small" href="<?= htmlspecialchars($downloadsUrl, ENT_QUOTES) ?>">Alle anzeigen</a></li>
              <?php if ($notifications === []): ?>
                <li><span class="dropdown-item-text small text-body-secondary">Keine aktuellen Hintergrundaufgaben.</span></li>
              <?php else: ?>
                <?php foreach ($notifications as $notification): ?>
                  <?php $notificationState = (string) ($notification['state'] ?? ''); $notificationType = (string) ($notification['type'] ?? ''); $notificationIcon = $notificationState === 'done' ? 'fa-circle-check text-success' : (in_array($notificationState, ['error', 'cancelled'], true) ? 'fa-circle-exclamation text-danger' : (in_array($notificationType, ['directory_import', 'phoenix_sync'], true) ? 'fa-file-import text-primary' : ($notificationType === 'pdf_regenerate' ? 'fa-file-pdf text-warning' : 'fa-clock text-warning'))); $notificationHref = !empty($notification['downloadable']) ? url_for('geraete/zip/' . rawurlencode((string) $notification['id']) . '/download') : $downloadsUrl; ?>
                  <li><a class="dropdown-item small d-flex gap-2 align-items-start rounded-2" href="<?= htmlspecialchars($notificationHref, ENT_QUOTES) ?>"><i class="fa-solid <?= $notificationIcon ?> mt-1" aria-hidden="true"></i><span><strong><?= htmlspecialchars((string) ($notification['type_label'] ?? 'Hintergrundaufgabe')) ?></strong><br><span class="text-body-secondary"><?= htmlspecialchars((string) ($notification['message'] ?? ($notificationState === 'done' ? 'Download ist fertig.' : 'Wird im Hintergrund verarbeitet.'))) ?></span></span></a></li>
                <?php endforeach; ?>
              <?php endif; ?>
            </ul>
          </div>
        <?php endif; ?>

        <div class="btn-group" role="group">
          <button type="button" class="btn btn-outline-navbar" id="themeCycleButton" aria-label="Theme umschalten">
            <i class="fas fa-circle-half-stroke" data-theme-icon></i>
          </button>
          <button type="button" class="btn btn-outline-navbar dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Theme auswählen">
            <span class="visually-hidden">Theme auswählen</span>
          </button>
          <ul class="dropdown-menu dropdown-menu-end">
            <li><button type="button" class="dropdown-item" data-bs-theme-value="light"><i class="fas fa-sun me-2"></i>Hell</button></li>
            <li><button type="button" class="dropdown-item" data-bs-theme-value="dark"><i class="fas fa-moon me-2"></i>Dunkel</button></li>
            <li><button type="button" class="dropdown-item" data-bs-theme-value="auto"><i class="fas fa-circle-half-stroke me-2"></i>Automatisch</button></li>
          </ul>
        </div>
      </div>
    </div>
  </div>
</nav>
