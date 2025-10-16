<?php $branding = $branding ?? get_branding(); ?>
<div class="row align-items-stretch g-4">
  <div class="col-lg-7">
    <div class="p-4 bg-body-tertiary border rounded-3 shadow-sm h-100">
      <h2 class="h4 mb-3"><?= htmlspecialchars($branding['home_headline'] ?? 'Willkommen in der Kursverwaltung') ?></h2>
      <p class="mb-3 text-body-secondary">
        <?= htmlspecialchars($branding['home_intro'] ?? 'Hier verwaltest du deine Kurse, importierst Teilnehmerlisten und erzeugst Einladungslinks.') ?>
      </p>
      <p class="mb-4 text-body-secondary">
        <?= htmlspecialchars($branding['home_details'] ?? 'Das Modul bleibt flexibel erweiterbar und unterstützt die Unternehmensgruppe in ihrer täglichen Zusammenarbeit.') ?>
      </p>
      <a class="btn btn-primary" href="<?= htmlspecialchars(url_for('kurse'), ENT_QUOTES) ?>">Zur Kursverwaltung</a>
    </div>
  </div>
  <div class="col-lg-5">
    <div class="card border-0 shadow-sm h-100 bg-body-tertiary">
      <div class="card-body">
        <h3 class="h5 mb-3">Schnellzugriffe</h3>
        <ul class="list-unstyled mb-0">
          <li class="mb-2 d-flex align-items-center">
            <i class="fa-solid fa-list-check me-2 text-primary" aria-hidden="true"></i>
            <a class="text-decoration-none" href="<?= htmlspecialchars(url_for('kurse'), ENT_QUOTES) ?>">Kurse anzeigen &amp; bearbeiten</a>
          </li>
          <li class="mb-2 d-flex align-items-center">
            <i class="fa-solid fa-user-plus me-2 text-primary" aria-hidden="true"></i>
            <a class="text-decoration-none" href="<?= htmlspecialchars(url_for('kurse'), ENT_QUOTES) ?>">Teilnehmer importieren</a>
          </li>
          <li class="mb-0 d-flex align-items-center">
            <i class="fa-solid fa-link me-2 text-primary" aria-hidden="true"></i>
            <a class="text-decoration-none" href="<?= htmlspecialchars(url_for('kurse'), ENT_QUOTES) ?>">Einladungslink generieren</a>
          </li>
        </ul>
      </div>
    </div>
  </div>
</div>
