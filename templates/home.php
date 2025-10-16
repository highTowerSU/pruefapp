<div class="row align-items-stretch g-4">
  <div class="col-lg-7">
    <div class="p-4 bg-body-tertiary border rounded-3 shadow-sm h-100">
      <h2 class="h4 mb-3">Willkommen zur Zugangsdaten-Verwaltung</h2>
      <p class="mb-4 text-body-secondary">
        Hier verwaltest du Moodle-Kurse, importierst Teilnehmerlisten und erzeugst Einladungslinks.
        Starte direkt in die Kursverwaltung, um neue Kurse anzulegen oder bestehende zu pflegen.
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
