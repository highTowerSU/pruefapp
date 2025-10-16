<nav class="navbar navbar-expand-lg navbar-navy mb-4 noprint">
  <div class="container">
    <?php
    $branding = $branding ?? get_branding();
    $currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $coursesUrl = url_for('kurse');
    $auditLogUrl = url_for('admin/audit-log');

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
    ?>
    <a class="navbar-brand" href="<?= htmlspecialchars(url_for(), ENT_QUOTES) ?>">
      <?= htmlspecialchars($branding['nav_brand'] ?? 'Kursverwaltung') ?>
    </a>

    <div class="d-flex align-items-center ms-auto gap-4 flex-wrap justify-content-end">
      <?php $authUser = current_user(); ?>
      <?php if ($authUser !== null): ?>
        <div class="d-flex align-items-center gap-3 flex-wrap justify-content-end">
          <a href="<?= htmlspecialchars($coursesUrl, ENT_QUOTES) ?>" class="nav-link px-0 link-light<?= $coursesActive ? ' fw-semibold text-decoration-underline' : '' ?>">Kurse</a>
          <a href="<?= htmlspecialchars($auditLogUrl, ENT_QUOTES) ?>" class="nav-link px-0 link-light<?= $auditActive ? ' fw-semibold text-decoration-underline' : '' ?>">Audit-Log</a>
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
          ?>
          <div class="d-flex align-items-center gap-2">
            <div class="dropdown">
              <button class="btn btn-outline-light dropdown-toggle d-flex align-items-center gap-2"
                      type="button"
                      id="<?= htmlspecialchars($userMenuId, ENT_QUOTES) ?>"
                      data-bs-toggle="dropdown"
                      aria-expanded="false"
                      aria-haspopup="true">
                <i class="fa-solid fa-user" aria-hidden="true"></i>
                <span><?= htmlspecialchars($displayName) ?></span>
              </button>
              <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="<?= htmlspecialchars($userMenuId, ENT_QUOTES) ?>">
                <?php if ($userManagementUrl !== null && current_user_has_role('admin')): ?>
                  <li>
                    <a class="dropdown-item" href="<?= htmlspecialchars($userManagementUrl, ENT_QUOTES) ?>">Nutzerverwaltung</a>
                  </li>
                  <li><hr class="dropdown-divider"></li>
                <?php endif; ?>
                <li>
                  <a class="dropdown-item" href="<?= htmlspecialchars(url_for('logout.php'), ENT_QUOTES) ?>">Logout</a>
                </li>
              </ul>
            </div>
            <?php if ($roleLabel !== null): ?>
              <span class="badge text-bg-secondary" title="Rolle: <?= htmlspecialchars($roleLabel) ?>">
                <?= htmlspecialchars($roleLabel) ?>
              </span>
            <?php endif; ?>
          </div>
        <?php endif; ?>

        <div class="btn-group" role="group">
          <button type="button" class="btn btn-outline-secondary" id="themeCycleButton" aria-label="Theme umschalten">
            <i class="fas fa-circle-half-stroke" data-theme-icon></i>
          </button>
          <button type="button" class="btn btn-outline-secondary dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Theme auswählen">
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
