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
</form>

<?= render_template('kurs_table.php', [
    'kurse' => $kurse,
    'message' => $message ?? null,
    'error' => $error ?? null,
]) ?>
