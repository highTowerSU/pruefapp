<?php
/** @var array<string, string> $values */
/** @var array<string, string> $errors */
/** @var string|null $effectiveKeycloakAccountUrl */
/** @var string|null $effectiveKeycloakAdminUrl */
/** @var string|null $keycloakAccountFileOverride */
/** @var string|null $keycloakAdminFileOverride */
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
  </div>
</div>

<div class="card shadow-sm border-danger mt-4">
  <div class="card-header text-danger"><h2 class="h5 mb-0">Elektro-Daten zurücksetzen</h2></div>
  <div class="card-body">
    <p class="mb-2">Löscht alle Elektro-Prüfungen, Geräte, Strukturdatensätze und importierten PDF-Berichte. Mandanten, Benutzer und Prüfaufträge bleiben erhalten.</p>
    <form method="post" action="<?= htmlspecialchars(url_for('admin/konfiguration'), ENT_QUOTES) ?>" class="row g-2" onsubmit="return confirm('Wirklich alle Elektro-Daten löschen? Dieser Vorgang kann nicht rückgängig gemacht werden.');">
      <input type="hidden" name="action" value="nuke_electrical">
      <div class="col-md-5"><label class="form-label" for="nuke-confirmation">Bestätigung</label><input class="form-control" id="nuke-confirmation" name="confirmation" placeholder="NUKE ELEKTRO" required></div>
      <div class="col-12"><button type="submit" class="btn btn-danger">Elektro-Daten löschen</button></div>
    </form>
  </div>
</div>
