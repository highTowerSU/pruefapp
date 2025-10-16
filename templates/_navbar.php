<nav class="navbar navbar-expand-lg navbar-navy mb-4 noprint">
  <div class="container">
    <a class="navbar-brand" href="<?= htmlspecialchars(url_for(), ENT_QUOTES) ?>">Moodle-Zugang</a>
    <div class="d-flex align-items-center ms-auto gap-3">
      <div class="dropdown">
        <button class="btn btn-outline-secondary dropdown-toggle" type="button" id="themeDropdown" data-bs-toggle="dropdown" aria-expanded="false">
          <i class="fas fa-circle-half-stroke me-2" id="themeDropdownIcon"></i>
          <span id="themeDropdownLabel">Automatisch</span>
        </button>
        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="themeDropdown">
          <li><button type="button" class="dropdown-item" data-theme-value="light">Hell</button></li>
          <li><button type="button" class="dropdown-item" data-theme-value="dark">Dunkel</button></li>
          <li><button type="button" class="dropdown-item" data-theme-value="auto">Automatisch</button></li>
        </ul>
      </div>
      <?php if (isset($_SESSION['user'])): ?>
        <span class="navbar-text me-3">
          Eingeloggt als <strong><?= htmlspecialchars($_SESSION['user']->preferred_username ?? 'Nutzer') ?></strong>
        </span>
        <a href="<?= htmlspecialchars(url_for('logout.php'), ENT_QUOTES) ?>" class="btn btn-logout">Logout</a>
      <?php endif; ?>
    </div>
  </div>
</nav>
