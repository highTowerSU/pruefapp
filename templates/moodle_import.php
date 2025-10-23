<?php
/** @var \RedBeanPHP\OODBBean $kurs */
/** @var array $teilnehmer */
/** @var array $status */
/** @var bool $canImport */
/** @var string|null $commandPreview */
/** @var array<string, mixed> $webserviceStatus */
/** @var bool $canFetchFromMoodle */
/** @var int|null $moodleCourseId */
/** @var string|null $moodleLookupError */
?>

<?php
$webserviceStatus = $webserviceStatus ?? [];
$canFetchFromMoodle = $canFetchFromMoodle ?? false;
$moodleCourseId = $moodleCourseId ?? null;
$moodleLookupError = $moodleLookupError ?? null;
?>

<div class="card mb-4">
    <div class="card-body">
        <h4 class="card-title">Teilnehmer aus Moodle abrufen</h4>
        <p class="card-text">
            Über den Moodle-Webservice können bestehende Teilnehmerlisten abgerufen und mit den lokalen Daten abgeglichen werden.
        </p>

        <?php if (empty($webserviceStatus['configured'])): ?>
            <div class="alert alert-warning" role="alert">
                Der Moodle-Webservice ist nicht vollständig konfiguriert. Bitte hinterlege eine Basis-URL und ein gültiges Token in den Einstellungen.
            </div>
        <?php else: ?>
            <dl class="row small text-muted mb-3">
                <dt class="col-sm-4">Basis-URL</dt>
                <dd class="col-sm-8">
                    <?php $baseUrl = (string) ($webserviceStatus['base_url'] ?? ''); ?>
                    <?= $baseUrl !== '' ? '<code>' . htmlspecialchars($baseUrl, ENT_QUOTES) . '</code>' : '–' ?>
                </dd>
                <dt class="col-sm-4">Token vorhanden</dt>
                <dd class="col-sm-8">
                    <?= !empty($webserviceStatus['token_configured']) ? '<span class="text-success">Ja</span>' : '<span class="text-danger">Nein</span>' ?>
                </dd>
                <dt class="col-sm-4">Verknüpfter Moodle-Kurs</dt>
                <dd class="col-sm-8">
                    <?php if ($moodleCourseId !== null): ?>
                        <code><?= (int) $moodleCourseId ?></code>
                    <?php elseif (!empty($kurs->moodle_course_shortname ?? '')): ?>
                        Shortname <code><?= htmlspecialchars($kurs->moodle_course_shortname, ENT_QUOTES) ?></code>
                    <?php else: ?>
                        –
                    <?php endif; ?>
                </dd>
            </dl>

            <?php if (!empty($moodleLookupError)): ?>
                <div class="alert alert-warning" role="alert">
                    <?= htmlspecialchars($moodleLookupError, ENT_QUOTES) ?>
                </div>
            <?php elseif (!$canFetchFromMoodle): ?>
                <div class="alert alert-info" role="alert">
                    Bitte trage in den Kurseinstellungen den Moodle-Shortname ein, um den Kurs zu verknüpfen.
                </div>
            <?php endif; ?>

            <div class="d-flex flex-wrap gap-2">
                <form method="post" action="<?= htmlspecialchars(url_for('kurse/' . (int) $kurs->id . '/teilnehmer/moodle/abrufen'), ENT_QUOTES) ?>">
                    <button type="submit" class="btn btn-outline-primary"<?= !$canFetchFromMoodle ? ' disabled' : '' ?>>
                        <i class="fa-solid fa-cloud-arrow-down"></i> Aus Moodle importieren
                    </button>
                </form>
                <form method="post" action="<?= htmlspecialchars(url_for('kurse/' . (int) $kurs->id . '/teilnehmer/moodle/synchronisieren'), ENT_QUOTES) ?>">
                    <button type="submit" class="btn btn-outline-success"<?= !$canFetchFromMoodle ? ' disabled' : '' ?>>
                        <i class="fa-solid fa-arrows-rotate"></i> Mit Moodle synchronisieren
                    </button>
                </form>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="card mb-4">
    <div class="card-body">
        <h4 class="card-title">Teilnehmer nach Moodle importieren</h4>
        <p class="card-text">
            Für diesen Kurs sind aktuell <strong><?= count($teilnehmer) ?></strong> Teilnehmer<?= count($teilnehmer) === 1 ? '' : 'innen' ?> erfasst.
            Die Daten werden als CSV vorbereitet und über das Moodle-CLI-Skript <code>admin/tool/uploaduser/cli/uploaduser.php</code> importiert.
        </p>

        <?php if (!empty($kurs->moodle_course_shortname ?? '')): ?>
            <p class="card-text">
                Beim Import werden die Teilnehmer dem Moodle-Kurs <code><?= htmlspecialchars($kurs->moodle_course_shortname, ENT_QUOTES) ?></code>
                <?php if (!empty($kurs->moodle_course_fullname ?? '') && $kurs->moodle_course_fullname !== $kurs->name): ?>
                    (<?= htmlspecialchars($kurs->moodle_course_fullname, ENT_QUOTES) ?>)
                <?php endif; ?>
                zugeordnet.
                <?php if (!empty($kurs->moodle_course_id ?? '')): ?>
                    (Kurs-ID: <?= (int) $kurs->moodle_course_id ?>)
                <?php endif; ?>
            </p>
        <?php else: ?>
            <div class="alert alert-info" role="alert">
                Es ist kein Moodle-Kurs hinterlegt. Die erzeugte CSV enthält daher keine Kurszuordnung.
            </div>
        <?php endif; ?>

        <?php if (!$status['configured']): ?>
            <div class="alert alert-warning" role="alert">
                Der Pfad zur Moodle-Installation ist nicht konfiguriert. Bitte setze die Umgebungsvariable
                <code>MOODLE_PATH</code>, sodass sie auf das Moodle-Stammverzeichnis verweist.
            </div>
        <?php elseif (!$status['script_exists']): ?>
            <div class="alert alert-warning" role="alert">
                Das Upload-Skript wurde unter
                <code><?= htmlspecialchars($status['script_path'] ?? '', ENT_QUOTES) ?></code>
                nicht gefunden. Prüfe bitte die Moodle-Installation.
            </div>
        <?php endif; ?>

        <?php if (!$status['php_exists']): ?>
            <div class="alert alert-warning" role="alert">
                Die konfigurierte PHP-Binary <code><?= htmlspecialchars($status['php_binary'] ?? '', ENT_QUOTES) ?></code>
                konnte nicht gefunden werden. Der Standardwert ist <code><?= htmlspecialchars(PHP_BINARY, ENT_QUOTES) ?></code>.
                Bei Bedarf kann über die Variable <code>MOODLE_PHP_BIN</code> ein anderer Pfad gesetzt werden.
            </div>
        <?php endif; ?>

        <dl class="row small text-muted mb-4">
            <dt class="col-sm-4">Moodle-Verzeichnis</dt>
            <dd class="col-sm-8"><?= $status['moodle_root'] !== '' ? htmlspecialchars($status['moodle_root'], ENT_QUOTES) : '–' ?></dd>
            <dt class="col-sm-4">Upload-Skript</dt>
            <dd class="col-sm-8"><?= $status['script_path'] !== '' ? htmlspecialchars($status['script_path'], ENT_QUOTES) : '–' ?></dd>
            <dt class="col-sm-4">PHP-Binary</dt>
            <dd class="col-sm-8"><?= $status['php_binary'] !== '' ? htmlspecialchars($status['php_binary'], ENT_QUOTES) : '–' ?></dd>
        </dl>

        <form method="post" class="d-flex flex-wrap gap-2">
            <input type="hidden" name="action" value="cli_import">
            <button type="submit" class="btn btn-primary"<?= (!$canImport || count($teilnehmer) === 0) ? ' disabled' : '' ?>>
                <i class="fa-solid fa-cloud-arrow-up"></i> Import starten
            </button>
            <a href="<?= htmlspecialchars(url_for('kurse/' . (int) $kurs->id . '/teilnehmer'), ENT_QUOTES) ?>" class="btn btn-outline-secondary">
                Zurück zur Teilnehmerliste
            </a>
        </form>
    </div>
</div>

<?php if ($canImport && count($teilnehmer) > 0): ?>
    <div class="card">
        <div class="card-body">
            <h5 class="card-title">Was passiert beim Import?</h5>
            <ul class="mb-3">
                <li>Alle Teilnehmer werden mit ihren Zugangsdaten in Moodle angelegt oder aktualisiert.</li>
                <li>Die CSV-Datei enthält Nutzername, Passwort, Vor- und Nachname, E-Mail-Adresse sowie die Profilfelder „Geburtsdatum“ und „Geburtsort“.</li>
                <li>Moodle versendet keine Benachrichtigungen, da die Option <code>--noemail</code> gesetzt wird.</li>
            </ul>
            <p class="mb-0 text-muted small">
                Hinweis: Der Import nutzt das Moodle-CLI-Skript <code>admin/tool/uploaduser/cli/uploaduser.php</code>. Stelle sicher, dass die ausführende PHP-Version
                Zugriff auf die Moodle-Konfiguration besitzt.
            </p>
            <?php if (!empty($commandPreview)): ?>
                <hr>
                <p class="mb-2 small text-muted">
                    Für eine manuelle Ausführung kann folgender Befehl genutzt werden. Der Platzhalter für die CSV-Datei wird während des Imports automatisch erstellt:
                </p>
                <pre class="small mb-0"><code><?= htmlspecialchars($commandPreview, ENT_QUOTES) ?></code></pre>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>
