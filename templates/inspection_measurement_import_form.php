<form method="post" action="<?= htmlspecialchars(url_for('admin/pruefungen/import'), ENT_QUOTES) ?>" enctype="multipart/form-data" class="row g-2 mt-2" id="pending-measurement-upload">
  <input type="hidden" name="action" value="pending_measurement_import">
  <div class="col-md-4"><label class="form-label" for="measurement-date">Ersatz-Prüfdatum (optional)</label><input class="form-control" id="measurement-date" type="date" name="measurement_date"></div>
  <div class="col-md-5"><label class="form-label" for="measurement-csv">Messdaten (CSV)</label><input class="form-control" id="measurement-csv" type="file" name="measurement_csv" accept=".csv" required></div>
  <div class="col-md-3 d-flex align-items-end"><button class="btn btn-primary w-100" type="submit"><i class="fa-solid fa-clock me-1" aria-hidden="true"></i>Im Hintergrund importieren</button></div>
  <div class="col-12 form-text">Jede CSV-Zeile wird über ihr eigenes Prüfdatum und ihren Speicherplatz zugeordnet. Das Ersatz-Prüfdatum gilt nur für Zeilen ohne Datumsangabe. Es werden ausschließlich in Prüfweb angelegte Prüfungen ergänzt; nicht passende Zeilen werden übersprungen und protokolliert.</div>
</form>
<script>
document.getElementById('pending-measurement-upload')?.addEventListener('submit', function () {
  const button = this.querySelector('button[type="submit"]');
  if (!button) return;
  button.disabled = true;
  button.innerHTML = '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Upload läuft …';
});
</script>
