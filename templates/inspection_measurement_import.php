<div class="container py-3">
  <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap mb-3">
    <h1 class="h4 mb-0"><i class="fa-solid fa-vial me-2" aria-hidden="true"></i>Messdaten importieren</h1>
    <a class="btn btn-sm btn-outline-secondary" href="<?= htmlspecialchars(url_for('admin/pruefungen/import'), ENT_QUOTES) ?>"><i class="fa-solid fa-arrow-left me-1" aria-hidden="true"></i>Import &amp; Sync</a>
  </div>
  <?php if (is_array($activeJob ?? null)): ?>
    <?php $jobState = (string) ($activeJob['state'] ?? 'queued'); $jobRunning = in_array($jobState, ['queued', 'running', 'cancel_requested'], true); ?>
    <div class="alert <?= $jobRunning ? 'alert-primary' : ($jobState === 'done' ? 'alert-success' : 'alert-danger') ?> job-status-notice sticky-top" id="measurement-import-status" role="status" aria-live="polite" data-status-url="<?= htmlspecialchars(url_for('admin/pruefungen/import/' . rawurlencode((string) ($activeJob['id'] ?? '')) . '/status'), ENT_QUOTES) ?>" data-running="<?= $jobRunning ? '1' : '0' ?>">
      <span class="spinner-border spinner-border-sm me-2<?= $jobRunning ? '' : ' d-none' ?>" aria-hidden="true"></span><span class="job-message"><?= htmlspecialchars((string) ($activeJob['message'] ?? $activeJob['error'] ?? 'Die Hintergrundaufgabe wird vorbereitet.'), ENT_QUOTES) ?></span>
    </div>
  <?php endif; ?>
  <div class="card"><div class="card-body">
    <p class="text-body-secondary">Die CSV-Messwerte werden bestehenden Prüfweb-Prüfungen über Prüfdatum und Speicherplatz zugeordnet. Das Datum kann aus der CSV übernommen oder hier vorgegeben werden.</p>
    <?= render_template('inspection_measurement_import_form.php') ?>
  </div></div>
</div>
<script>
(() => {
  const status = document.getElementById('measurement-import-status');
  if (!status || status.dataset.running !== '1') return;
  const poll = () => fetch(status.dataset.statusUrl, {credentials: 'same-origin'})
    .then(response => response.json())
    .then(job => {
      const state = job.state || 'error';
      const running = ['queued', 'running', 'cancel_requested'].includes(state);
      status.classList.remove('alert-primary', 'alert-success', 'alert-danger');
      status.classList.add(running ? 'alert-primary' : (state === 'done' ? 'alert-success' : 'alert-danger'));
      status.querySelector('.spinner-border')?.classList.toggle('d-none', !running);
      const message = status.querySelector('.job-message');
      if (message) message.textContent = job.message || job.error || (running ? 'Die Aufgabe läuft im Hintergrund.' : 'Die Aufgabe ist beendet.');
      if (running) window.setTimeout(poll, 10000);
    })
    .catch(() => window.setTimeout(poll, 10000));
  window.setTimeout(poll, 10000);
})();
</script>
