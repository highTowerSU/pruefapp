<?php
$branding = $branding ?? get_branding();
$navColors = $branding['nav_colors'] ?? [];
$navBackgroundColor = $navColors['background'] ?? '#0D6EFD';
$navTextColor = $navColors['text'] ?? '#FFFFFF';
$navStyle = sprintf('--navbar-bg:%s; --navbar-color:%s;', $navBackgroundColor, $navTextColor);
?>
<nav class="navbar navbar-expand-lg navbar-themed mb-4 noprint" style="<?= htmlspecialchars($navStyle, ENT_QUOTES) ?>">
  <div class="container">
    <?php
    $currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $coursesUrl = url_for('kurse');
    $auditLogUrl = url_for('admin/audit-log');
    $userAdminUrl = url_for('admin/nutzer');
    $settingsUrl = url_for('admin/konfiguration');
    $helpUrl = url_for('hilfe');

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

    ?>
    <a class="navbar-brand d-flex align-items-center gap-2" href="<?= htmlspecialchars(url_for(), ENT_QUOTES) ?>">
      <?php $brandLogo = $branding['header_logo']['path'] ?? ''; ?>
      <?php if (!empty($brandLogo)): ?>
        <?php $brandLogoUrl = preg_match('#^https?://#i', $brandLogo) ? $brandLogo : url_for($brandLogo); ?>
        <img src="<?= htmlspecialchars($brandLogoUrl, ENT_QUOTES) ?>"
             alt="<?= htmlspecialchars($branding['header_logo']['alt'] ?? ($branding['company_name'] ?? '')) ?>"
             class="navbar-brand-logo img-fluid" style="max-height:1.5em">
      <?php endif; ?>
      <span><?= htmlspecialchars($branding['nav_brand'] ?? 'Kursverwaltung') ?></span>
    </a>

    <div class="d-flex align-items-center ms-auto gap-4 flex-wrap justify-content-end">
      <?php $authUser = current_user(); ?>
      <?php if ($authUser !== null): ?>
        <?php
          $companyUrl = url_for('firmen');
          $companyPrefix = rtrim($companyUrl, '/');
          $companyActive = false;
          if ($companyPrefix === '') {
              $companyActive = $currentPath === '/' || $currentPath === '';
          } else {
              $companyActive = $currentPath === $companyUrl || strpos($currentPath, $companyPrefix . '/') === 0;
          }
        ?>
        <div class="d-flex align-items-center gap-3 flex-wrap justify-content-end">
          <a href="<?= htmlspecialchars($coursesUrl, ENT_QUOTES) ?>" class="nav-link px-0<?= $coursesActive ? ' active fw-semibold text-decoration-underline' : '' ?>">Kurse</a>
          <a href="<?= htmlspecialchars($helpUrl, ENT_QUOTES) ?>" class="nav-link px-0<?= $helpActive ? ' active fw-semibold text-decoration-underline' : '' ?>">Hilfe</a>
          <a href="<?= htmlspecialchars($auditLogUrl, ENT_QUOTES) ?>" class="nav-link px-0<?= $auditActive ? ' active fw-semibold text-decoration-underline' : '' ?>">Audit-Log</a>
          <?php if (current_user_has_role('admin')): ?>
            <a href="<?= htmlspecialchars($userAdminUrl, ENT_QUOTES) ?>" class="nav-link px-0<?= $userAdminActive ? ' active fw-semibold text-decoration-underline' : '' ?>">Nutzer</a>
            <a href="<?= htmlspecialchars($companyUrl, ENT_QUOTES) ?>" class="nav-link px-0<?= $companyActive ? ' active fw-semibold text-decoration-underline' : '' ?>">Firmen</a>
            <a href="<?= htmlspecialchars($settingsUrl, ENT_QUOTES) ?>" class="nav-link px-0<?= $settingsActive ? ' active fw-semibold text-decoration-underline' : '' ?>">Konfiguration</a>
          <?php endif; ?>
        </div>
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
                $userManagementUrl = getenv('APP_USER_MANAGEMENT_URL') ?: ($_ENV['APP_USER_MANAGEMENT_URL'] ?? null);
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
                <?php if ($hasManagementLink): ?>
                  <li>
                    <a class="dropdown-item" href="<?= htmlspecialchars($userManagementUrl, ENT_QUOTES) ?>">Nutzerverwaltung</a>
                  </li>
                <?php endif; ?>
                <?php if ($hasKeycloakLink): ?>
                  <li>
                    <a class="dropdown-item" href="<?= htmlspecialchars($keycloakAccountUrl, ENT_QUOTES) ?>" target="_blank" rel="noopener">
                      Mein Konto …
                    </a>
                  </li>
                <?php endif; ?>
                <?php if ($hasManagementLink || $hasKeycloakLink): ?>
                  <li><hr class="dropdown-divider"></li>
                <?php endif; ?>
                <li>
                  <a class="dropdown-item" href="<?= htmlspecialchars(url_for('logout.php'), ENT_QUOTES) ?>">Logout</a>
                </li>
              </ul>
            </div>
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
