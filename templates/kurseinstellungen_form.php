<form method="post">
    <h4>Einstellungen für Teilnehmerdateneingabe</h4>

    <div class="form-check mb-2">
        <input type="checkbox" class="form-check-input" id="feld_email" name="feld_email_aktiv"
               <?= $kurs->feld_email_aktiv ? 'checked' : '' ?>>
        <label class="form-check-label" for="feld_email">E-Mail-Adresse abfragen</label>
    </div>

    <div class="form-check mb-2">
        <input type="checkbox" class="form-check-input" id="feld_firma" name="feld_firma_aktiv"
               <?= !empty($kurs->feld_firma_aktiv) ? 'checked' : '' ?>>
        <label class="form-check-label" for="feld_firma">Firma abfragen</label>
    </div>

    <div class="form-check mb-3">
        <input type="checkbox" class="form-check-input" id="feld_geburtsort" name="feld_geburtsort_aktiv"
               <?= $kurs->feld_geburtsort_aktiv ? 'checked' : '' ?>>
        <label class="form-check-label" for="feld_geburtsort">Geburtsort abfragen</label>
    </div>

    <h4 class="mt-4">Moodle-Einstellungen</h4>

    <div class="mb-3">
        <label for="moodle_course_shortname" class="form-label">Moodle-Kurs-Shortname</label>
        <input
            type="text"
            class="form-control"
            id="moodle_course_shortname"
            name="moodle_course_shortname"
            value="<?= htmlspecialchars(trim((string) ($kurs->moodle_course_shortname ?? '')), ENT_QUOTES) ?>"
            placeholder="z. B. ENG101"
        >
        <div class="form-text">Wird für den Moodle-Import benötigt.</div>
    </div>

    <div class="mb-4">
        <label for="moodle_course_fullname" class="form-label">Moodle-Kursname (optional)</label>
        <input
            type="text"
            class="form-control"
            id="moodle_course_fullname"
            name="moodle_course_fullname"
            value="<?= htmlspecialchars(trim((string) ($kurs->moodle_course_fullname ?? '')), ENT_QUOTES) ?>"
        >
    </div>

    <button class="btn btn-primary">Speichern</button>
    <a href="<?= htmlspecialchars(url_for('kurse'), ENT_QUOTES) ?>" class="btn btn-link">Zurück</a>
</form>
