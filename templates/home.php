<?php
$branding = $branding ?? get_branding();
$versionDisplay = app_version_display_data();
?>
<div class="row align-items-stretch g-4">
  <div class="col-lg-7">
    <div class="p-4 bg-body-tertiary border rounded-3 shadow-sm h-100">
      <h2 class="h4 mb-3"><?= htmlspecialchars($branding['home_headline'] ?? 'Willkommen in der Prüfauftragsverwaltung') ?></h2>
      <p class="mb-3 text-body-secondary">
        <?= htmlspecialchars($branding['home_intro'] ?? 'Hier dokumentierst du Prüfungen, verwaltest Prüfumfänge und hältst Nachweise revisionssicher fest.') ?>
      </p>
      <p class="mb-4 text-body-secondary">
        <?= htmlspecialchars($branding['home_details'] ?? 'Der aktuelle Schwerpunkt liegt auf Elektroprüfungen nach DGUV Vorschrift 3. Weitere Prüftypen wie Leitern können ergänzt werden.') ?>
      </p>
      <a class="btn btn-primary" href="<?= htmlspecialchars(url_for('kurse'), ENT_QUOTES) ?>">Zu den Prüfaufträgen</a>
    </div>
  </div>
  <div class="col-lg-5">
    <div class="card border-0 shadow-sm h-100 bg-body-tertiary">
      <div class="card-body">
        <h3 class="h5 mb-3">Schnellzugriffe</h3>
        <ul class="list-unstyled mb-0">
          <li class="mb-2 d-flex align-items-center">
            <i class="fa-solid fa-list-check me-2 text-primary" aria-hidden="true"></i>
            <a class="text-decoration-none" href="<?= htmlspecialchars(url_for('kurse'), ENT_QUOTES) ?>">Prüfaufträge anzeigen &amp; bearbeiten</a>
          </li>
          <li class="mb-2 d-flex align-items-center">
            <i class="fa-solid fa-user-plus me-2 text-primary" aria-hidden="true"></i>
            <a class="text-decoration-none" href="<?= htmlspecialchars(url_for('kurse'), ENT_QUOTES) ?>">Prüflinge/Objekte importieren</a>
          </li>
          <li class="mb-0 d-flex align-items-center">
            <i class="fa-solid fa-link me-2 text-primary" aria-hidden="true"></i>
            <a class="text-decoration-none" href="<?= htmlspecialchars(url_for('kurse'), ENT_QUOTES) ?>">Erfassungslink generieren</a>
          </li>
        </ul>
      </div>
    </div>
  </div>
</div>
<?php if (!empty($versionDisplay['version'])): ?>
  <div class="mt-4 text-body-secondary small">
    Version <?= htmlspecialchars($versionDisplay['version']) ?>
    <?php if (!empty($versionDisplay['commit'])): ?>
      <span class="text-body-tertiary mx-1">·</span>
      <span class="font-monospace">#<?= htmlspecialchars($versionDisplay['commit']) ?></span>
    <?php endif; ?>
    <?php if (!empty($versionDisplay['build_date_human']) && !empty($versionDisplay['build_date_iso'])): ?>
      <span class="text-body-tertiary mx-1">·</span>
      <time datetime="<?= htmlspecialchars($versionDisplay['build_date_iso'], ENT_QUOTES) ?>">
        <?= htmlspecialchars($versionDisplay['build_date_human']) ?>
      </time>
    <?php endif; ?>
  </div>
<?php endif; ?>
