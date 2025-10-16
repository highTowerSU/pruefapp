<nav class="navbar navbar-expand-lg navbar-navy mb-4 noprint">
  <div class="container">
    <?php $branding = $branding ?? get_branding(); ?>
    <a class="navbar-brand" href="<?= htmlspecialchars(url_for(), ENT_QUOTES) ?>">
      <?= htmlspecialchars($branding['nav_brand'] ?? 'Kursverwaltung') ?>
    </a>
    <div class="d-flex align-items-center ms-auto gap-3">
      <?php if (isset($_SESSION['user'])): ?>
        <span class="navbar-text me-3">
          Eingeloggt als <strong><?= htmlspecialchars($_SESSION['user']->preferred_username ?? 'Nutzer') ?></strong>
        </span>
        <a href="<?= htmlspecialchars(url_for('logout.php'), ENT_QUOTES) ?>" class="btn btn-logout">Logout</a>
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
</nav>
