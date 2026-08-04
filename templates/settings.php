<?php
/** @var array<string, string> $values */
/** @var array<string, string> $errors */
/** @var string|null $effectiveKeycloakAccountUrl */
/** @var string|null $effectiveKeycloakAdminUrl */
/** @var string|null $keycloakAccountFileOverride */
/** @var string|null $keycloakAdminFileOverride */
/** @var array<string, mixed>|null $databaseWizard */
?>

<form method="post" action="<?= htmlspecialchars(url_for('admin/konfiguration'), ENT_QUOTES) ?>" class="card shadow-sm mb-4">
  <div class="card-header">
    <h2 class="h5 mb-0">Allgemeine Einstellungen</h2>
  </div>
  <div class="card-body">
    <div class="mb-3">
      <label for="keycloak_account_console_base_url" class="form-label">Keycloak-Konto-URL</label>
      <input type="url" id="keycloak_account_console_base_url" name="keycloak_account_console_base_url"
             class="form-control<?= isset($errors['keycloak_account_console_base_url']) ? ' is-invalid' : '' ?>"
             value="<?= htmlspecialchars($values['keycloak_account_console_base_url'] ?? '', ENT_QUOTES) ?>">
      <?php if (isset($errors['keycloak_account_console_base_url'])): ?>
        <div class="invalid-feedback"><?= htmlspecialchars($errors['keycloak_account_console_base_url'], ENT_QUOTES) ?></div>
      <?php endif; ?>
    </div>

    <div class="mb-3">
      <label for="keycloak_admin_console_base_url" class="form-label">Keycloak-Admin-URL</label>
      <input type="url" id="keycloak_admin_console_base_url" name="keycloak_admin_console_base_url"
             class="form-control<?= isset($errors['keycloak_admin_console_base_url']) ? ' is-invalid' : '' ?>"
             value="<?= htmlspecialchars($values['keycloak_admin_console_base_url'] ?? '', ENT_QUOTES) ?>">
      <?php if (isset($errors['keycloak_admin_console_base_url'])): ?>
        <div class="invalid-feedback"><?= htmlspecialchars($errors['keycloak_admin_console_base_url'], ENT_QUOTES) ?></div>
      <?php endif; ?>
    </div>

    <?php if (!empty($keycloakAccountFileOverride) || !empty($keycloakAdminFileOverride)): ?>
      <div class="alert alert-warning mb-0">Hinweis: Werte aus der externen Konfigurationsdatei überschreiben gespeicherte Werte.</div>
    <?php endif; ?>
  </div>
  <div class="card-footer text-end">
    <button type="submit" class="btn btn-primary">Speichern</button>
  </div>
</form>

<div class="card shadow-sm">
  <div class="card-header"><h2 class="h5 mb-0">Aktive Konfiguration</h2></div>
  <div class="card-body">
    <p class="mb-2"><strong>Keycloak-Konto:</strong> <?= $effectiveKeycloakAccountUrl ? htmlspecialchars($effectiveKeycloakAccountUrl, ENT_QUOTES) : '–' ?></p>
    <p class="mb-0"><strong>Keycloak-Admin:</strong> <?= $effectiveKeycloakAdminUrl ? htmlspecialchars($effectiveKeycloakAdminUrl, ENT_QUOTES) : '–' ?></p>
    <p class="mb-0 mt-2"><strong>Aktive Datenbank:</strong> <code><?= htmlspecialchars((string) ($databasePath ?? '–'), ENT_QUOTES) ?></code></p>
  </div>
</div>

<details class="card shadow-sm mt-4">
  <summary class="card-header fw-semibold">MySQL/MariaDB-Datenbank einrichten</summary>
  <div class="card-body">
    <p class="text-body-secondary small">Der Admin-Zugang wird nur für die Einrichtung verwendet. Danach arbeitet die App mit einem separat erzeugten Benutzer; das Admin-Passwort wird nicht gespeichert.</p>
    <?php if (!empty($databaseWizard)): ?>
      <div class="alert alert-<?= !empty($databaseWizard['success']) ? 'success' : 'danger' ?>">
        <?= htmlspecialchars((string) ($databaseWizard['message'] ?? ''), ENT_QUOTES) ?>
        <?php if (!empty($databaseWizard['snippet'])): ?><pre class="mt-2 mb-0 small"><code><?= htmlspecialchars((string) $databaseWizard['snippet'], ENT_QUOTES) ?></code></pre><?php endif; ?>
      </div>
    <?php endif; ?>
    <form method="post" action="<?= htmlspecialchars(url_for('admin/konfiguration'), ENT_QUOTES) ?>" class="row g-3">
      <input type="hidden" name="action" value="db_wizard">
      <div class="col-md-6"><label class="form-label" for="admin_dsn">Admin-DSN</label><input class="form-control" id="admin_dsn" name="admin_dsn" placeholder="mysql:host=127.0.0.1" required></div>
      <div class="col-md-3"><label class="form-label" for="admin_user">Admin-Benutzer</label><input class="form-control" id="admin_user" name="admin_user" placeholder="root" required></div>
      <div class="col-md-3"><label class="form-label" for="admin_password">Admin-Passwort</label><input class="form-control" id="admin_password" name="admin_password" type="password" autocomplete="off"></div>
      <div class="col-md-4"><label class="form-label" for="database_name">Datenbankname</label><input class="form-control" id="database_name" name="database_name" value="pruefapp" required></div>
      <div class="col-md-4"><label class="form-label" for="app_user">App-Benutzer</label><input class="form-control" id="app_user" name="app_user" value="pruefapp" required></div>
      <div class="col-12"><button class="btn btn-primary" type="submit"><i class="fa-solid fa-database me-1" aria-hidden="true"></i>Datenbank einrichten</button></div>
    </form>
  </div>
</details>

<div class="card shadow-sm border-danger mt-4">
  <div class="card-header text-danger"><h2 class="h5 mb-0">Elektro-Daten zurücksetzen</h2></div>
  <div class="card-body">
    <p class="mb-2">Der Umfang ist auswählbar. Mandanten, Benutzer und Prüfaufträge bleiben erhalten. Berichte werden gelöscht, sobald Prüfungen/Geräte oder alles ausgewählt ist.</p>
    <form method="post" action="<?= htmlspecialchars(url_for('admin/konfiguration'), ENT_QUOTES) ?>" class="row g-2" onsubmit="return confirm('Wirklich alle Elektro-Daten löschen? Dieser Vorgang kann nicht rückgängig gemacht werden.');">
      <input type="hidden" name="action" value="nuke_electrical">
      <div class="col-md-5"><label class="form-label" for="nuke-scope">Umfang</label><select class="form-select" id="nuke-scope" name="scope"><option value="devices">Nur Prüfungen und Geräte</option><option value="structure">Nur Räume und Struktur</option><option value="all">Alles: Prüfungen, Geräte und Struktur</option></select></div>
      <div class="col-md-5"><label class="form-label" for="nuke-confirmation">Bestätigung</label><input class="form-control" id="nuke-confirmation" name="confirmation" placeholder="NUKE ELEKTRO" required></div>
      <div class="col-12"><button type="submit" class="btn btn-danger">Elektro-Daten löschen</button></div>
    </form>
  </div>
</div>
