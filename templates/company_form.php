<?php
/** @var array $company */
/** @var bool $is_new */
/** @var string[] $errors */
?>

<?php if ($errors !== []): ?>
  <div class="alert alert-danger">
    <h2 class="h6 mb-2">Es sind Fehler aufgetreten:</h2>
    <ul class="mb-0">
      <?php foreach ($errors as $error): ?>
        <li><?= htmlspecialchars($error) ?></li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<form method="post" class="card shadow-sm border-0" enctype="multipart/form-data">
  <div class="card-body">
    <div class="row g-4">
      <div class="col-lg-6">
        <div class="mb-3">
          <label class="form-label" for="name">Anzeigename *</label>
          <input type="text" class="form-control" id="name" name="name" required value="<?= htmlspecialchars($company['name']) ?>">
          <div class="form-text">Wird in Überschriften und Beschreibungen verwendet.</div>
        </div>
      </div>
      <div class="col-lg-6">
        <div class="mb-3">
          <label class="form-label" for="slug">Kurznamen (Slug) *</label>
          <input type="text" class="form-control" id="slug" name="slug" required value="<?= htmlspecialchars($company['slug']) ?>">
          <div class="form-text">Kleinbuchstaben, Zahlen und Bindestriche. Wird u.a. für die Auswahl über die externe Konfigurationsdatei verwendet.</div>
        </div>
      </div>
    </div>

    <div class="row g-4">
      <div class="col-lg-6">
        <div class="mb-3">
          <label class="form-label" for="app_title">Seitentitel *</label>
          <input type="text" class="form-control" id="app_title" name="app_title" value="<?= htmlspecialchars($company['app_title']) ?>">
        </div>
      </div>
      <div class="col-lg-6">
        <div class="mb-3">
          <label class="form-label" for="nav_brand">Navigationstitel *</label>
          <input type="text" class="form-control" id="nav_brand" name="nav_brand" value="<?= htmlspecialchars($company['nav_brand']) ?>">
          <div class="form-text">Erscheint links oben in der Navigation.</div>
        </div>
      </div>
    </div>

    <div class="mb-3">
      <label class="form-label" for="home_headline">Startseiten-Headline</label>
      <input type="text" class="form-control" id="home_headline" name="home_headline" value="<?= htmlspecialchars($company['home_headline']) ?>">
    </div>
    <h2 class="h5 mt-4">Logo-Varianten</h2>
    <div class="row g-4 mb-3">
      <div class="col-lg-6">
        <label class="form-label" for="logo_light_path">Logo auf hellem Hintergrund</label>
        <input type="text" class="form-control" id="logo_light_path" name="logo_light_path" value="<?= htmlspecialchars($company['logo_light_path']) ?>">
        <input type="file" class="form-control mt-2" name="logo_light_file" accept="image/png,image/jpeg,image/svg+xml,image/gif,image/webp">
        <div class="form-text">Üblicherweise die dunkle Logo-Variante.</div>
      </div>
      <div class="col-lg-6">
        <label class="form-label" for="logo_dark_path">Logo auf dunklem Hintergrund</label>
        <input type="text" class="form-control" id="logo_dark_path" name="logo_dark_path" value="<?= htmlspecialchars($company['logo_dark_path']) ?>">
        <input type="file" class="form-control mt-2" name="logo_dark_file" accept="image/png,image/jpeg,image/svg+xml,image/gif,image/webp">
        <div class="form-text">Üblicherweise die helle Logo-Variante.</div>
      </div>
    </div>
    <div class="mb-3">
      <label class="form-label" for="home_intro">Startseiten-Intro</label>
      <textarea class="form-control" id="home_intro" name="home_intro" rows="2"><?= htmlspecialchars($company['home_intro']) ?></textarea>
    </div>
    <div class="mb-4">
      <label class="form-label" for="home_details">Startseiten-Details</label>
      <textarea class="form-control" id="home_details" name="home_details" rows="3"><?= htmlspecialchars($company['home_details']) ?></textarea>
    </div>

    <hr>

    <h2 class="h5">SevDesk-Abrechnung</h2>
    <p class="form-text">Jeder Mandant kann eine eigene SevDesk-Instanz verwenden. Der API-Token wird nur gespeichert und nie wieder angezeigt.</p>
    <div class="row g-4 mb-4">
      <div class="col-lg-6"><label class="form-label" for="sevdesk_api_url">SevDesk-API-URL</label><input type="url" class="form-control" id="sevdesk_api_url" name="sevdesk_api_url" value="<?= htmlspecialchars($company['sevdesk_api_url'] ?? 'https://my.sevdesk.de/api/v1') ?>"></div>
      <div class="col-lg-6"><label class="form-label" for="sevdesk_api_token">SevDesk-API-Token</label><input type="password" autocomplete="new-password" class="form-control" id="sevdesk_api_token" name="sevdesk_api_token" value=""><div class="form-text">Leer lassen, um den vorhandenen Token beizubehalten.</div></div>
    </div>

    <hr>

    <div class="row g-4">
      <div class="col-lg-6">
        <div class="mb-3">
          <label class="form-label" for="header_logo_path">Header-Logo</label>
          <input type="text" class="form-control" id="header_logo_path" name="header_logo_path" value="<?= htmlspecialchars($company['header_logo_path']) ?>">
          <div class="form-text">Pfad relativ zum Projekt oder vollständige URL. Bei einem Upload wird der Pfad automatisch gesetzt.</div>
        </div>
      </div>
      <div class="col-lg-6">
        <div class="mb-3">
          <label class="form-label" for="header_logo_file">Header-Logo hochladen</label>
          <input type="file" class="form-control" id="header_logo_file" name="header_logo_file" accept="image/png,image/jpeg,image/svg+xml,image/gif,image/webp">
          <div class="form-text">Optional. Unterstützt PNG, JPG, SVG, GIF oder WebP bis 2&nbsp;MB.</div>
        </div>
      </div>
    </div>

    <?php if (!empty($company['header_logo_url'])): ?>
      <div class="mb-4">
        <span class="form-text d-block mb-2">Aktuelles Header-Logo:</span>
        <img src="<?= htmlspecialchars($company['header_logo_url'], ENT_QUOTES) ?>"
             alt="<?= htmlspecialchars($company['header_logo_alt'] ?? $company['name']) ?>"
             class="img-fluid" style="max-height: 64px; width: auto;">
      </div>
    <?php endif; ?>

    <div class="mb-3">
      <label class="form-label" for="header_logo_alt">Header-Logo Alternativtext</label>
      <input type="text" class="form-control" id="header_logo_alt" name="header_logo_alt" value="<?= htmlspecialchars($company['header_logo_alt']) ?>">
      <div class="form-text">Wird standardmäßig mit dem Firmennamen vorbelegt.</div>
    </div>

    <hr>

    <h2 class="h5">Theme-Farben</h2>
    <div class="row g-4 mb-4">
      <?php foreach ([
        'primary_color' => ['Primary', '#0D6EFD'],
        'primary_text_color' => ['Text auf Primary', '#FFFFFF'],
        'light_color' => ['Fläche im hellen Theme', '#F8F9FA'],
        'dark_color' => ['Fläche im dunklen Theme', '#212529'],
      ] as $field => [$label, $fallback]): ?>
        <div class="col-sm-6 col-xl-3">
          <label class="form-label" for="<?= $field ?>"><?= $label ?></label>
          <input type="color" class="form-control form-control-color" id="<?= $field ?>" name="<?= $field ?>" value="<?= htmlspecialchars($company[$field] ?: $fallback) ?>">
        </div>
      <?php endforeach; ?>
    </div>
    <h2 class="h5">Navbar-Farben</h2>

    <div class="row g-4">
      <div class="col-lg-6">
        <div class="mb-3">
          <label class="form-label" for="nav_background_color">Navigations-Hintergrundfarbe</label>
          <input type="color" class="form-control form-control-color" id="nav_background_color" name="nav_background_color" value="<?= htmlspecialchars($company['nav_background_color'] ?: '#0D6EFD') ?>" title="Farbwert im Hex-Format">
          <div class="form-text">Hex-Wert, z.&nbsp;B. <code>#000080</code>.</div>
        </div>
      </div>
      <div class="col-lg-6">
        <div class="mb-3">
          <label class="form-label" for="nav_text_color">Navigations-Textfarbe</label>
          <input type="color" class="form-control form-control-color" id="nav_text_color" name="nav_text_color" value="<?= htmlspecialchars($company['nav_text_color'] ?: '#FFFFFF') ?>" title="Farbwert im Hex-Format">
          <div class="form-text">Hex-Wert, z.&nbsp;B. <code>#FFFFFF</code>.</div>
        </div>
      </div>
    </div>

    <hr>

    <div class="row g-4">
      <div class="col-lg-6">
        <div class="mb-3">
          <label class="form-label" for="legal_impressum_label">Impressum Label</label>
          <input type="text" class="form-control" id="legal_impressum_label" name="legal_impressum_label" value="<?= htmlspecialchars($company['legal_impressum_label']) ?>">
        </div>
        <div class="mb-4">
          <label class="form-label" for="legal_impressum_url">Impressum URL</label>
          <input type="url" class="form-control" id="legal_impressum_url" name="legal_impressum_url" value="<?= htmlspecialchars($company['legal_impressum_url']) ?>">
        </div>
      </div>
      <div class="col-lg-6">
        <div class="mb-3">
          <label class="form-label" for="legal_privacy_label">Datenschutz Label</label>
          <input type="text" class="form-control" id="legal_privacy_label" name="legal_privacy_label" value="<?= htmlspecialchars($company['legal_privacy_label']) ?>">
        </div>
        <div class="mb-4">
          <label class="form-label" for="legal_privacy_url">Datenschutz URL</label>
          <input type="url" class="form-control" id="legal_privacy_url" name="legal_privacy_url" value="<?= htmlspecialchars($company['legal_privacy_url']) ?>">
        </div>
      </div>
    </div>

    <div class="row g-3 mb-4">
      <div class="col-md-6">
        <div class="form-check form-switch">
          <input class="form-check-input" type="checkbox" role="switch" id="is_default" name="is_default" <?= !empty($company['is_default']) ? 'checked' : '' ?>>
          <label class="form-check-label" for="is_default">Als Standardmandant verwenden</label>
          <div class="form-text">Branding für interne Seiten ohne eigene Mandantenzuordnung.</div>
        </div>
      </div>
      <div class="col-md-6">
        <div class="form-check form-switch">
          <input class="form-check-input" type="checkbox" role="switch" id="is_login_brand" name="is_login_brand" <?= !empty($company['is_login_brand']) ? 'checked' : '' ?>>
          <label class="form-check-label" for="is_login_brand">Für die Login-Maske verwenden</label>
          <div class="form-text">Logo, Farben und Rechtstexte dieses Mandanten erscheinen beim Login.</div>
        </div>
      </div>
    </div>

    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
      <a class="btn btn-outline-secondary" href="<?= htmlspecialchars(url_for('mandanten'), ENT_QUOTES) ?>">Abbrechen</a>
      <button type="submit" class="btn btn-primary">
        <?= $is_new ? 'Anlegen' : 'Speichern' ?>
      </button>
    </div>
  </div>
</form>
