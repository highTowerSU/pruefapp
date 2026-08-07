<?php
/** @var array<string, string> $values */
/** @var array<string, string> $errors */
/** @var string|null $effectiveKeycloakAccountUrl */
/** @var string|null $effectiveKeycloakAdminUrl */
/** @var string|null $keycloakAccountFileOverride */
/** @var string|null $keycloakAdminFileOverride */
/** @var array<string, mixed>|null $databaseWizard */
/** @var array<string, list<string>>|null $updateResult */
/** @var array<string, mixed>|null $migrationStatus */
/** @var bool $apiDebugSecretEnabled */
/** @var string $apiDebugSecretOnce */
?>

<form id="settings-general-panel" data-action-nav="Einstellungen speichern" data-action-icon="fa-gear" method="post" action="<?= htmlspecialchars(url_for('admin/konfiguration'), ENT_QUOTES) ?>" class="card shadow-sm mb-4">
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
    <hr>
    <h3 class="h6">Einmalige Benning-Nachmigration</h3>
    <p class="small text-body-secondary">Der Cron importiert das Verzeichnis genau einmal und korrigiert dabei ältere Messwertspalten automatisch.</p>
    <div class="row g-3">
      <div class="col-md-6"><label class="form-label" for="benning_reimport_directory">CSV/ODS-Importverzeichnis</label><input class="form-control" id="benning_reimport_directory" name="benning_reimport_directory" value="<?= htmlspecialchars($values['benning_reimport_directory'] ?? '', ENT_QUOTES) ?>" placeholder="/var/www/import/2026"></div>
      <div class="col-md-6"><label class="form-label" for="benning_reports_directory">Berichtsverzeichnis</label><input class="form-control" id="benning_reports_directory" name="benning_reports_directory" value="<?= htmlspecialchars($values['benning_reports_directory'] ?? '', ENT_QUOTES) ?>" placeholder="/var/www/berichte"></div>
    </div>
    <div class="form-check form-switch mt-3"><input class="form-check-input" type="checkbox" role="switch" id="auto_update_enabled" name="auto_update_enabled" value="1"<?= ($values['auto_update_enabled'] ?? '1') === '1' ? ' checked' : '' ?>><label class="form-check-label" for="auto_update_enabled">Automatische Abhängigkeitsupdates bei neuer Git-Version</label></div>
    <hr>
    <h3 class="h6"><i class="fa-solid fa-list-ol me-1" aria-hidden="true"></i>Aufbewahrung der Protokolle</h3>
    <p class="small text-body-secondary">Technische Cron-Logs und das fachliche Ereignisprotokoll werden automatisch nach dem FIFO-Prinzip begrenzt. ReBean-Datenrevisionen bleiben vollständig erhalten.</p>
    <div class="row g-3">
      <div class="col-md-6"><label class="form-label" for="cron_log_max_rows"><i class="fa-solid fa-database me-1" aria-hidden="true"></i>Maximale Log-Einträge</label><input class="form-control<?= isset($errors['cron_log_max_rows']) ? ' is-invalid' : '' ?>" id="cron_log_max_rows" name="cron_log_max_rows" type="number" min="500" max="1000000" step="100" value="<?= htmlspecialchars($values['cron_log_max_rows'] ?? '5000', ENT_QUOTES) ?>"><?php if (isset($errors['cron_log_max_rows'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['cron_log_max_rows'], ENT_QUOTES) ?></div><?php endif; ?></div>
      <div class="col-md-6"><label class="form-label" for="cron_log_max_bytes"><i class="fa-solid fa-hard-drive me-1" aria-hidden="true"></i>Maximale Dateigröße (Bytes)</label><input class="form-control<?= isset($errors['cron_log_max_bytes']) ? ' is-invalid' : '' ?>" id="cron_log_max_bytes" name="cron_log_max_bytes" type="number" min="262144" max="104857600" step="262144" value="<?= htmlspecialchars($values['cron_log_max_bytes'] ?? (string) (5 * 1024 * 1024), ENT_QUOTES) ?>"><?php if (isset($errors['cron_log_max_bytes'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['cron_log_max_bytes'], ENT_QUOTES) ?></div><?php endif; ?></div>
      <div class="col-md-6"><label class="form-label" for="audit_log_max_rows"><i class="fa-solid fa-shield-halved me-1" aria-hidden="true"></i>Maximale Audit-Ereignisse</label><input class="form-control<?= isset($errors['audit_log_max_rows']) ? ' is-invalid' : '' ?>" id="audit_log_max_rows" name="audit_log_max_rows" type="number" min="1000" max="5000000" step="1000" value="<?= htmlspecialchars($values['audit_log_max_rows'] ?? '250000', ENT_QUOTES) ?>"><?php if (isset($errors['audit_log_max_rows'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['audit_log_max_rows'], ENT_QUOTES) ?></div><?php endif; ?><div class="form-text">Importdetails und sonstige fachliche Ereignisse; Revisionstabellen sind davon nicht betroffen.</div></div>
    </div>
    <hr>
    <h3 class="h6"><i class="fa-solid fa-gears me-1" aria-hidden="true"></i>Hintergrundverarbeitung</h3>
    <p class="small text-body-secondary">Lange Aufgaben speichern ihren Stand nach jedem Abschnitt. Kleinere Abschnitte verteilen die verfügbare Zeit fairer auf mehrere Aufgaben.</p>
    <div class="row g-3">
      <div class="col-md-3"><label class="form-label" for="cron_time_budget_seconds"><i class="fa-solid fa-stopwatch me-1" aria-hidden="true"></i>Zeitbudget je Lauf</label><div class="input-group"><input class="form-control<?= isset($errors['cron_time_budget_seconds']) ? ' is-invalid' : '' ?>" id="cron_time_budget_seconds" name="cron_time_budget_seconds" type="number" min="30" max="900" value="<?= htmlspecialchars($values['cron_time_budget_seconds'] ?? '120', ENT_QUOTES) ?>"><span class="input-group-text">s</span><?php if (isset($errors['cron_time_budget_seconds'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['cron_time_budget_seconds'], ENT_QUOTES) ?></div><?php endif; ?></div></div>
      <div class="col-md-3"><label class="form-label" for="cron_job_slice_seconds"><i class="fa-solid fa-chart-pie me-1" aria-hidden="true"></i>Abschnitt je Aufgabe</label><div class="input-group"><input class="form-control<?= isset($errors['cron_job_slice_seconds']) ? ' is-invalid' : '' ?>" id="cron_job_slice_seconds" name="cron_job_slice_seconds" type="number" min="5" max="120" value="<?= htmlspecialchars($values['cron_job_slice_seconds'] ?? '30', ENT_QUOTES) ?>"><span class="input-group-text">s</span><?php if (isset($errors['cron_job_slice_seconds'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['cron_job_slice_seconds'], ENT_QUOTES) ?></div><?php endif; ?></div></div>
      <div class="col-md-3"><label class="form-label" for="cron_job_lease_seconds"><i class="fa-solid fa-lock me-1" aria-hidden="true"></i>Worker-Lease</label><div class="input-group"><input class="form-control<?= isset($errors['cron_job_lease_seconds']) ? ' is-invalid' : '' ?>" id="cron_job_lease_seconds" name="cron_job_lease_seconds" type="number" min="30" max="900" value="<?= htmlspecialchars($values['cron_job_lease_seconds'] ?? '180', ENT_QUOTES) ?>"><span class="input-group-text">s</span><?php if (isset($errors['cron_job_lease_seconds'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['cron_job_lease_seconds'], ENT_QUOTES) ?></div><?php endif; ?></div></div>
      <div class="col-md-3"><label class="form-label" for="background_history_days"><i class="fa-solid fa-clock-rotate-left me-1" aria-hidden="true"></i>Historie aufbewahren</label><div class="input-group"><input class="form-control<?= isset($errors['background_history_days']) ? ' is-invalid' : '' ?>" id="background_history_days" name="background_history_days" type="number" min="7" max="3650" value="<?= htmlspecialchars($values['background_history_days'] ?? '180', ENT_QUOTES) ?>"><span class="input-group-text">Tage</span><?php if (isset($errors['background_history_days'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['background_history_days'], ENT_QUOTES) ?></div><?php endif; ?></div></div>
    </div>
    <?php if (!empty($migrationStatus)): ?><div class="alert alert-success mt-3 mb-0"><strong>Nachmigration erledigt</strong><?php if (!empty($migrationStatus['completed_at'])): ?> · <?= htmlspecialchars((new DateTimeImmutable((string) $migrationStatus['completed_at']))->format('d.m.Y H:i')) ?><?php endif; ?><?php $migrationStats = $migrationStatus['stats'] ?? []; ?><div class="small mt-1">Repariert: <?= (int) ($migrationStats['repaired'] ?? 0) ?> · Importiert: <?= (int) ($migrationStats['imported'] ?? 0) ?> · Aktualisiert: <?= (int) ($migrationStats['updated'] ?? 0) ?></div></div><?php else: ?><div class="alert alert-secondary mt-3 mb-0">Die Nachmigration wird beim nächsten Cron-Lauf automatisch ausgeführt.</div><?php endif; ?>
  </div>
  <div class="card-footer text-end">
    <button type="submit" class="btn btn-primary">Speichern</button>
  </div>
</form>

<details class="card shadow-sm mt-4" id="settings-debug-panel" data-action-nav="Debug-Zugang" data-action-icon="fa-stethoscope">
  <summary class="card-header fw-semibold"><i class="fa-solid fa-stethoscope me-1" aria-hidden="true"></i>Technischer Debug-Zugang</summary>
  <div class="card-body">
    <p class="text-body-secondary small">Nur für die gezielte Serverdiagnose durch autorisierte technische Unterstützung. Der Zugang funktioniert ausschließlich mit einem HTTP-Header, wird nicht in URLs übertragen und kann hier jederzeit neu erzeugt oder deaktiviert werden.</p>
    <?php if (!empty($apiDebugSecretOnce)): ?><div class="alert alert-warning"><strong>Secret jetzt sicher kopieren.</strong><br><code class="user-select-all text-break"><?= htmlspecialchars($apiDebugSecretOnce, ENT_QUOTES) ?></code><div class="small mt-2">Nach dem Verlassen oder Neuladen dieser Seite wird es nicht erneut angezeigt.</div></div><?php endif; ?>
    <p class="mb-3">Status: <?php if (!empty($apiDebugSecretEnabled)): ?><span class="badge text-bg-success"><i class="fa-solid fa-lock me-1" aria-hidden="true"></i>aktiv</span><?php else: ?><span class="badge text-bg-secondary">deaktiviert</span><?php endif; ?></p>
    <div class="btn-group" role="group" aria-label="Debug-Zugang verwalten">
      <form method="post" action="<?= htmlspecialchars(url_for('admin/konfiguration'), ENT_QUOTES) ?>"><input type="hidden" name="action" value="generate_api_debug_secret"><button class="btn btn-warning" type="submit" onclick="return confirm('Neues Debug-Secret erzeugen? Ein bisheriges Secret verliert sofort seine Gültigkeit.');"><i class="fa-solid fa-key me-1" aria-hidden="true"></i><?= !empty($apiDebugSecretEnabled) ? 'Secret erneuern' : 'Secret erzeugen' ?></button></form>
      <?php if (!empty($apiDebugSecretEnabled)): ?><form method="post" action="<?= htmlspecialchars(url_for('admin/konfiguration'), ENT_QUOTES) ?>"><input type="hidden" name="action" value="disable_api_debug_secret"><button class="btn btn-outline-danger" type="submit" onclick="return confirm('API-Debug-Zugang wirklich deaktivieren?');"><i class="fa-solid fa-ban me-1" aria-hidden="true"></i>Deaktivieren</button></form><?php endif; ?>
    </div>
    <div class="form-text mt-3">Diagnose-Endpunkt: <code>/pruefapp/api/debug/inspection?q=100016494</code> mit Header <code>X-Api-Debug-Secret</code>.</div>
  </div>
</details>

<details class="card shadow-sm mt-4" id="settings-update-panel" data-action-nav="Anwendung aktualisieren" data-action-icon="fa-arrows-rotate">
  <summary class="card-header fw-semibold"><i class="fa-solid fa-arrows-rotate me-1" aria-hidden="true"></i>Anwendung aktualisieren</summary>
  <div class="card-body">
    <p class="text-body-secondary small">Aktualisiert Composer- und JavaScript-Abhängigkeiten sowie vorhandene Frontend-Builds. Nur für Superadministratoren.</p>
    <?php if (!empty($updateResult)): ?><div class="alert alert-<?= !empty($updateResult['errors']) ? 'danger' : 'success' ?>"><strong><?= !empty($updateResult['errors']) ? 'Aktualisierung mit Fehlern' : 'Aktualisierung abgeschlossen' ?></strong><ul class="mb-0 mt-2"><?php foreach (['ok', 'skipped', 'errors'] as $group): foreach (($updateResult[$group] ?? []) as $message): ?><li><?= htmlspecialchars((string) $message, ENT_QUOTES) ?></li><?php endforeach; endforeach; ?></ul></div><?php endif; ?>
    <form method="post" action="<?= htmlspecialchars(url_for('admin/konfiguration'), ENT_QUOTES) ?>" onsubmit="return confirm('Abhängigkeiten und Frontend wirklich aktualisieren?');">
      <input type="hidden" name="action" value="update_app">
      <button class="btn btn-outline-primary" type="submit"><i class="fa-solid fa-cloud-arrow-down me-1" aria-hidden="true"></i>Jetzt aktualisieren</button>
    </form>
  </div>
</details>

<div class="card shadow-sm">
  <div class="card-header"><h2 class="h5 mb-0">Aktive Konfiguration</h2></div>
  <div class="card-body">
    <p class="mb-2"><strong>Keycloak-Konto:</strong> <?= $effectiveKeycloakAccountUrl ? htmlspecialchars($effectiveKeycloakAccountUrl, ENT_QUOTES) : '–' ?></p>
    <p class="mb-0"><strong>Keycloak-Admin:</strong> <?= $effectiveKeycloakAdminUrl ? htmlspecialchars($effectiveKeycloakAdminUrl, ENT_QUOTES) : '–' ?></p>
    <p class="mb-0 mt-2"><strong>Aktive Datenbank:</strong> <code><?= htmlspecialchars((string) ($databasePath ?? '–'), ENT_QUOTES) ?></code></p>
  </div>
</div>

<details class="card shadow-sm mt-4" id="settings-database-panel" data-action-nav="Datenbank einrichten" data-action-icon="fa-database">
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

<div class="card shadow-sm border-danger mt-4" id="settings-reset-panel" data-action-nav="Daten zurücksetzen" data-action-icon="fa-triangle-exclamation">
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
