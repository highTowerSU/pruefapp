<form method="post"
      class="mb-4"
      hx-post="kurse"
      hx-target="#kurs-tabelle"
      hx-swap="outerHTML"
      hx-on::after-request="if (event.detail.successful) { this.reset(); const input = this.querySelector('[name=kursname]'); if (input) { input.focus(); } }">
    <div class="row g-2">
        <div class="col-md-6">
            <input type="text" name="kursname" class="form-control" placeholder="Neuer Kursname" required>
        </div>
        <div class="col-md-2">
            <button class="btn btn-primary">Kurs anlegen</button>
        </div>
    </div>

    <div class="card mt-3">
        <div class="card-body">
            <h5 class="card-title">Moodle-Optionen</h5>
            <p class="card-text small text-muted">
                Optional kannst du hier einen bestehenden Moodle-Kurs kopieren. Der Shortname wird außerdem beim Teilnehmerimport
                verwendet, damit die Nutzer automatisch im richtigen Kurs landen.
            </p>

            <div class="form-check form-switch mb-3">
                <input class="form-check-input" type="checkbox" role="switch" id="moodle-copy" name="moodle_copy" value="1">
                <label class="form-check-label" for="moodle-copy">Bestehenden Moodle-Kurs kopieren</label>
            </div>

            <div class="row g-2 mb-3">
                <div class="col-md-4">
                    <label for="moodle-template" class="form-label">Quellkurs (Shortname)</label>
                    <input type="text" class="form-control" id="moodle-template" name="moodle_template_shortname" placeholder="z. B. KURS-VORLAGE">
                </div>
                <div class="col-md-4">
                    <label for="moodle-shortname" class="form-label">Neuer Moodle-Shortname</label>
                    <input type="text" class="form-control" id="moodle-shortname" name="moodle_new_shortname" placeholder="z. B. KURS-2024">
                </div>
                <div class="col-md-4">
                    <label for="moodle-fullname" class="form-label">Neuer Moodle-Name</label>
                    <input type="text" class="form-control" id="moodle-fullname" name="moodle_new_fullname" placeholder="z. B. Kurs Wintersemester 2024">
                </div>
            </div>

            <div class="form-check mb-2">
                <input class="form-check-input" type="checkbox" id="moodle-visible" name="moodle_visible" value="1" checked>
                <label class="form-check-label" for="moodle-visible">Neuen Moodle-Kurs direkt sichtbar schalten</label>
            </div>

            <p class="small text-muted mb-0">
                Wenn du keinen Shortname angibst, wird beim Moodle-Import keine Kurszuordnung vorgenommen.
            </p>
        </div>
    </div>
</form>

<?= render_template('kurs_table.php', [
    'kurse' => $kurse,
    'message' => $message ?? null,
    'error' => $error ?? null,
]) ?>
