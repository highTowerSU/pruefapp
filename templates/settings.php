<?php
/** @var array<string, string> $values */
/** @var array<string, string> $errors */
/** @var string $storedMoodlePath */
/** @var string $effectiveMoodlePath */
/** @var string|null $envOverride */
/** @var string $storedKeycloakAccountUrl */
/** @var string|null $effectiveKeycloakAccountUrl */
/** @var string|null $keycloakAccountEnvOverride */
/** @var string $storedKeycloakAdminUrl */
/** @var string|null $effectiveKeycloakAdminUrl */
/** @var string|null $keycloakAdminEnvOverride */
/** @var array<string, mixed> $moodleStatus */

$values = $values ?? [
    'moodle_path' => '',
    'keycloak_account_console_base_url' => '',
    'keycloak_admin_console_base_url' => '',
];
$errors = $errors ?? [];
$storedMoodlePath = $storedMoodlePath ?? '';
$effectiveMoodlePath = $effectiveMoodlePath ?? '';
$envOverride = $envOverride ?? null;
$storedKeycloakAccountUrl = $storedKeycloakAccountUrl ?? '';
$effectiveKeycloakAccountUrl = $effectiveKeycloakAccountUrl ?? null;
$keycloakAccountEnvOverride = $keycloakAccountEnvOverride ?? null;
$storedKeycloakAdminUrl = $storedKeycloakAdminUrl ?? '';
$effectiveKeycloakAdminUrl = $effectiveKeycloakAdminUrl ?? null;
$keycloakAdminEnvOverride = $keycloakAdminEnvOverride ?? null;
$moodleStatus = $moodleStatus ?? [];
?>

<?php if (!empty($errors['general'])): ?>
  <div class="alert alert-danger" role="alert">
    <?= htmlspecialchars($errors['general'], ENT_QUOTES) ?>
  </div>
<?php endif; ?>

<form method="post" action="<?= htmlspecialchars(url_for('admin/konfiguration'), ENT_QUOTES) ?>" class="card shadow-sm mb-4">
  <div class="card-header">
    <h2 class="h5 mb-0">Allgemeine Einstellungen</h2>
  </div>
  <div class="card-body">
    <div class="mb-3">
      <label for="moodle_path" class="form-label">Pfad zur Moodle-Installation</label>
      <input
        type="text"
        id="moodle_path"
        name="moodle_path"
        class="form-control<?= isset($errors['moodle_path']) ? ' is-invalid' : '' ?>"
        value="<?= htmlspecialchars($values['moodle_path'] ?? '', ENT_QUOTES) ?>"
        placeholder="/var/www/moodle"
        autocomplete="off"
      >
      <div class="form-text">
        Bitte gib das Wurzelverzeichnis der Moodle-Installation an. Lasse das Feld leer, um die Einstellung zu löschen.
      </div>
      <?php if (isset($errors['moodle_path'])): ?>
        <div class="invalid-feedback">
          <?= htmlspecialchars($errors['moodle_path'], ENT_QUOTES) ?>
        </div>
      <?php endif; ?>
    </div>

    <?php if (!empty($envOverride)): ?>
      <div class="alert alert-warning" role="alert">
        Die Umgebungsvariable <code>MOODLE_PATH</code> ist gesetzt und überschreibt den hier gespeicherten Wert.
      </div>
    <?php endif; ?>

    <div class="mb-3">
      <label for="keycloak_account_console_base_url" class="form-label">Keycloak-Konto-URL</label>
      <input
        type="url"
        id="keycloak_account_console_base_url"
        name="keycloak_account_console_base_url"
        class="form-control<?= isset($errors['keycloak_account_console_base_url']) ? ' is-invalid' : '' ?>"
        value="<?= htmlspecialchars($values['keycloak_account_console_base_url'] ?? '', ENT_QUOTES) ?>"
        placeholder="https://keycloak.example.org/realms/meinrealm/account"
        autocomplete="off"
      >
      <div class="form-text">
        Optionaler Direktlink zur Keycloak-Account-Oberfläche. Lasse das Feld leer, um die URL automatisch aus Server und Realm abzuleiten.
      </div>
      <?php if (isset($errors['keycloak_account_console_base_url'])): ?>
        <div class="invalid-feedback">
          <?= htmlspecialchars($errors['keycloak_account_console_base_url'], ENT_QUOTES) ?>
        </div>
      <?php endif; ?>
    </div>

    <?php if (!empty($keycloakAccountEnvOverride)): ?>
      <div class="alert alert-warning" role="alert">
        Die Umgebungsvariable <code>APP_KEYCLOAK_ACCOUNT_CONSOLE_BASE_URL</code> ist gesetzt und überschreibt den hier gespeicherten Wert.
      </div>
    <?php endif; ?>

    <div class="mb-3">
      <label for="keycloak_admin_console_base_url" class="form-label">Keycloak-Admin-URL</label>
      <input
        type="url"
        id="keycloak_admin_console_base_url"
        name="keycloak_admin_console_base_url"
        class="form-control<?= isset($errors['keycloak_admin_console_base_url']) ? ' is-invalid' : '' ?>"
        value="<?= htmlspecialchars($values['keycloak_admin_console_base_url'] ?? '', ENT_QUOTES) ?>"
        placeholder="https://keycloak.example.org/admin/master/console/#/realms/meinrealm"
        autocomplete="off"
      >
      <div class="form-text">
        Optionaler Direktlink zur Keycloak-Admin-Oberfläche. Lasse das Feld leer, um die URL automatisch aus Server und Realm abzuleiten.
      </div>
      <?php if (isset($errors['keycloak_admin_console_base_url'])): ?>
        <div class="invalid-feedback">
          <?= htmlspecialchars($errors['keycloak_admin_console_base_url'], ENT_QUOTES) ?>
        </div>
      <?php endif; ?>
    </div>

    <?php if (!empty($keycloakAdminEnvOverride)): ?>
      <div class="alert alert-warning" role="alert">
        Die Umgebungsvariable <code>APP_KEYCLOAK_ADMIN_CONSOLE_BASE_URL</code> ist gesetzt und überschreibt den hier gespeicherten Wert.
      </div>
    <?php endif; ?>
  </div>
  <div class="card-footer text-end">
    <button type="submit" class="btn btn-primary">
      <i class="fa-solid fa-floppy-disk me-1" aria-hidden="true"></i>
      Speichern
    </button>
  </div>
</form>

<section class="card shadow-sm">
  <div class="card-header">
    <h2 class="h5 mb-0">Status der Moodle-Integration</h2>
  </div>
  <div class="card-body">
    <dl class="row mb-0">
      <dt class="col-sm-5 col-lg-4">Gespeicherter Pfad</dt>
      <dd class="col-sm-7 col-lg-8">
        <?= $storedMoodlePath !== '' ? '<code>' . htmlspecialchars($storedMoodlePath, ENT_QUOTES) . '</code>' : '–' ?>
      </dd>

      <dt class="col-sm-5 col-lg-4">Aktiv verwendeter Pfad</dt>
      <dd class="col-sm-7 col-lg-8">
        <?= $effectiveMoodlePath !== '' ? '<code>' . htmlspecialchars($effectiveMoodlePath, ENT_QUOTES) . '</code>' : '–' ?>
      </dd>

      <dt class="col-sm-5 col-lg-4">Upload-Skript</dt>
      <dd class="col-sm-7 col-lg-8">
        <?php $scriptPath = (string) ($moodleStatus['script_path'] ?? ''); ?>
        <?= $scriptPath !== '' ? '<code>' . htmlspecialchars($scriptPath, ENT_QUOTES) . '</code>' : '–' ?>
      </dd>

      <dt class="col-sm-5 col-lg-4">Skript gefunden</dt>
      <dd class="col-sm-7 col-lg-8">
        <?= !empty($moodleStatus['script_exists']) ? '<span class="text-success">Ja</span>' : '<span class="text-danger">Nein</span>' ?>
      </dd>

      <dt class="col-sm-5 col-lg-4">PHP-Binary</dt>
      <dd class="col-sm-7 col-lg-8">
        <?php $phpBinary = (string) ($moodleStatus['php_binary'] ?? ''); ?>
        <?= $phpBinary !== '' ? '<code>' . htmlspecialchars($phpBinary, ENT_QUOTES) . '</code>' : '–' ?>
      </dd>

      <dt class="col-sm-5 col-lg-4">PHP-Binary gefunden</dt>
      <dd class="col-sm-7 col-lg-8">
        <?= !empty($moodleStatus['php_exists']) ? '<span class="text-success">Ja</span>' : '<span class="text-danger">Nein</span>' ?>
      </dd>

      <dt class="col-sm-5 col-lg-4">Keycloak-Konto-URL (gespeichert)</dt>
      <dd class="col-sm-7 col-lg-8">
        <?= $storedKeycloakAccountUrl !== '' ? '<code>' . htmlspecialchars($storedKeycloakAccountUrl, ENT_QUOTES) . '</code>' : '–' ?>
      </dd>

      <dt class="col-sm-5 col-lg-4">Keycloak-Konto-URL (aktiv)</dt>
      <dd class="col-sm-7 col-lg-8">
        <?php if (!empty($effectiveKeycloakAccountUrl)): ?>
          <code><?= htmlspecialchars((string) $effectiveKeycloakAccountUrl, ENT_QUOTES) ?></code>
        <?php else: ?>
          –
        <?php endif; ?>
      </dd>

      <dt class="col-sm-5 col-lg-4">Keycloak-Admin-URL (gespeichert)</dt>
      <dd class="col-sm-7 col-lg-8">
        <?= $storedKeycloakAdminUrl !== '' ? '<code>' . htmlspecialchars($storedKeycloakAdminUrl, ENT_QUOTES) . '</code>' : '–' ?>
      </dd>

      <dt class="col-sm-5 col-lg-4">Keycloak-Admin-URL (aktiv)</dt>
      <dd class="col-sm-7 col-lg-8">
        <?php if (!empty($effectiveKeycloakAdminUrl)): ?>
          <code><?= htmlspecialchars((string) $effectiveKeycloakAdminUrl, ENT_QUOTES) ?></code>
        <?php else: ?>
          –
        <?php endif; ?>
      </dd>
    </dl>
  </div>
</section>
