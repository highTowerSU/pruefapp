<?php if (current_user_has_role('admin')): ?>
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
<?php else: ?>
  <div class="alert alert-info mb-4">
    Du kannst vorhandene Kurse einsehen, aber keine neuen Kurse anlegen.
  </div>
<?php endif; ?>

<?= render_template('kurs_table.php', [
    'kurse' => $kurse,
    'message' => $message ?? null,
    'error' => $error ?? null,
]) ?>
