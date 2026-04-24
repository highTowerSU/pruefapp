<?php if (current_user_can_manage_courses()): ?>
  <form method="post"
        class="mb-4"
        hx-post="kurse"
        hx-target="#kurs-tabelle"
        hx-swap="outerHTML"
        hx-on::after-request="if (event.detail.successful) { this.reset(); const input = this.querySelector('[name=kursname]'); if (input) { input.focus(); } }">
    <div class="row g-2 align-items-end">
      <div class="col-md-6">
        <label class="form-label visually-hidden" for="kursname">Neuer Kursname</label>
        <input type="text" id="kursname" name="kursname" class="form-control" placeholder="Neuer Kursname" required>
      </div>
      <div class="col-md-2">
        <button class="btn btn-primary w-100">Kurs anlegen</button>
      </div>
    </div>
  </form>
<?php else: ?>
  <div class="alert alert-info mb-4">Du kannst vorhandene Kurse einsehen, aber keine neuen Kurse anlegen.</div>
<?php endif; ?>

<?= render_template('kurs_table.php', [
    'kurse' => $kurse,
    'message' => $message ?? null,
    'error' => $error ?? null,
]) ?>
