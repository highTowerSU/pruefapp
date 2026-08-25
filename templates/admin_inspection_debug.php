<div class="container py-4" id="inspection-debug-page">
  <header class="page-header mb-4">
    <h1 class="mb-1"><i class="fa-solid fa-stethoscope me-2" aria-hidden="true"></i>Prüfungsdiagnose</h1>
    <p class="mb-0 text-body-secondary">Nur für Superadmins. Zeigt den tatsächlich serverseitig berechneten Status.</p>
  </header>

  <section class="card mb-4">
    <div class="card-body">
      <form method="get" class="row g-2 align-items-end">
        <div class="col-md-8"><label class="form-label" for="inspection-debug-query">Geräte- oder Prüfnummer</label><input class="form-control" id="inspection-debug-query" name="q" value="<?= htmlspecialchars($query) ?>" placeholder="z. B. 100016494" autofocus></div>
        <div class="col-md-auto"><button class="btn btn-primary"><i class="fa-solid fa-magnifying-glass me-1" aria-hidden="true"></i>Diagnostizieren</button></div>
      </form>
    </div>
  </section>

  <section class="card mb-4">
    <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2"><strong><i class="fa-solid fa-copy me-1" aria-hidden="true"></i>Prüfungsdubletten &amp; kurze Wiederholungen</strong><span class="badge text-bg-<?= ($duplicateOpen ?? 0) > 0 ? 'warning text-dark' : 'success' ?>"><?= (int) ($duplicateOpen ?? 0) ?> offen</span></div>
    <div class="card-body">
      <p class="small text-body-secondary">Der einmalige Hintergrundlauf sucht gleiche Prüfnummern am selben Gerät, Import-Suffixe wie „-25-2“ und Prüfungen desselben Geräts innerhalb von 180 Tagen. Er verändert weder Prüfungen noch Rechnungen; besonders bei bereits bezahlten historischen Rechnungen bleibt die Entscheidung bewusst manuell.</p>
      <?php if (!empty($duplicateAuditJob)): ?><p><i class="fa-solid fa-spinner fa-spin me-1" aria-hidden="true"></i>Aufgabe <?= htmlspecialchars(substr((string) $duplicateAuditJob['id'], 0, 12)) ?>: <?= (int) $duplicateAuditJob['step'] ?> von <?= (int) $duplicateAuditJob['total'] ?> · <?= htmlspecialchars((string) $duplicateAuditJob['message']) ?></p><?php endif; ?>
      <form method="post" action="<?= htmlspecialchars(url_for('admin/debug/pruefungen/dubletten-pruefen'), ENT_QUOTES) ?>" class="mb-3"><button class="btn btn-primary" type="submit"><i class="fa-solid fa-magnifying-glass me-1" aria-hidden="true"></i>Erneut prüfen</button></form>
      <?php if (($duplicateFindings ?? []) === []): ?><p class="mb-0 text-body-secondary">Noch keine offenen Auffälligkeiten. Der Cron legt den einmaligen Lauf nach dem Deployment automatisch an.</p><?php else: ?><div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th>Schwere</th><th>Gerät</th><th>Ältere Prüfung</th><th>Spätere Prüfung</th><th>Hinweis</th></tr></thead><tbody><?php foreach ($duplicateFindings as $finding): ?><tr><td><span class="badge text-bg-<?= ($finding['severity'] ?? '') === 'danger' ? 'danger' : 'warning text-dark' ?>"><?= htmlspecialchars((string) $finding['finding_type']) ?></span></td><td><a href="<?= htmlspecialchars(url_for('geraete?device_id=' . (int) $finding['device_id']), ENT_QUOTES) ?>"><?= htmlspecialchars((string) $finding['device_number']) ?> · <?= htmlspecialchars((string) $finding['device_name']) ?></a></td><td><a href="<?= htmlspecialchars(url_for('pruefungen/' . (int) $finding['inspection_id']), ENT_QUOTES) ?>"><?= htmlspecialchars((string) $finding['earlier_number']) ?></a><div class="small text-body-secondary"><?= htmlspecialchars((string) $finding['earlier_date']) ?></div></td><td><a href="<?= htmlspecialchars(url_for('pruefungen/' . (int) $finding['peer_inspection_id']), ENT_QUOTES) ?>"><?= htmlspecialchars((string) $finding['later_number']) ?></a><div class="small text-body-secondary"><?= htmlspecialchars((string) $finding['later_date']) ?></div></td><td><?= htmlspecialchars((string) $finding['reason']) ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
    </div>
  </section>

  <section class="card mb-4">
    <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2"><strong><i class="fa-solid fa-code-compare me-1" aria-hidden="true"></i>Import-Ergebnisabgleich</strong><span class="badge text-bg-<?= ($importsToReconcile ?? 0) > 0 ? 'warning text-dark' : 'success' ?>"><?= (int) ($importsToReconcile ?? 0) ?> offen</span></div>
    <div class="card-body"><?php if (!empty($importReconciliationJob)): ?><p class="mb-0"><i class="fa-solid fa-spinner fa-spin me-1" aria-hidden="true"></i>Aufgabe <?= htmlspecialchars(substr((string) $importReconciliationJob['id'], 0, 12)) ?>: <?= (int) $importReconciliationJob['step'] ?> von <?= (int) $importReconciliationJob['total'] ?> · <?= htmlspecialchars((string) $importReconciliationJob['message']) ?></p><?php else: ?><p class="mb-0 text-body-secondary">Der Cron plant den Abgleich automatisch ein, solange importierte Prüfungen mit fehlenden Daten vorhanden sind.</p><?php endif; ?></div>
  </section>

  <section class="card mb-4">
    <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2"><strong><i class="fa-solid fa-file-archive me-1" aria-hidden="true"></i>Legacy-Klassifizierung</strong><span class="badge text-bg-<?= $unclassified > 0 ? 'warning text-dark' : 'success' ?>"><?= (int) $unclassified ?> offen</span></div>
    <div class="card-body">
      <?php if ($legacyJob): ?><p class="mb-0"><i class="fa-solid fa-spinner fa-spin me-1" aria-hidden="true"></i>Aufgabe <?= htmlspecialchars(substr((string) $legacyJob['id'], 0, 12)) ?>: <?= htmlspecialchars((string) $legacyJob['step']) ?> von <?= htmlspecialchars((string) $legacyJob['total']) ?>.</p>
      <?php elseif ($unclassified > 0): ?><form method="post" action="<?= htmlspecialchars(url_for('admin/debug/pruefungen/legacy-migration'), ENT_QUOTES) ?>"><p>Historische Prüfungen bis 2024 ohne Legacy-Klassifikation können sofort für den nächsten Cron-Lauf vorgemerkt werden.</p><button class="btn btn-warning"><i class="fa-solid fa-play me-1" aria-hidden="true"></i>Legacy-Migration einplanen</button></form>
      <?php else: ?><p class="mb-0 text-body-secondary"><i class="fa-solid fa-circle-check me-1" aria-hidden="true"></i>Alle historischen Prüfungen sind bereits klassifiziert.</p><?php endif; ?>
    </div>
  </section>

  <?php if ($query !== ''): ?>
  <section class="card"><div class="card-header"><strong>Serverergebnis für „<?= htmlspecialchars($query) ?>“</strong></div><div class="card-body p-0">
    <?php if ($rows === []): ?><p class="p-3 mb-0 text-body-secondary">Keine passende Prüfung gefunden.</p><?php else: ?><div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th>Prüfung</th><th>Gerät</th><th>Datum</th><th>Gespeichert</th><th>Quellstatus</th><th>Berechnet</th><th>Klassifikation</th><th>Erwartung</th></tr></thead><tbody><?php foreach ($rows as $row): ?><tr><td><?= htmlspecialchars((string) $row['external_number']) ?><br><span class="small text-body-secondary">#<?= (int) $row['id'] ?></span></td><td><?= htmlspecialchars((string) $row['device_number']) ?> · <?= htmlspecialchars((string) $row['device_name']) ?></td><td><?= htmlspecialchars((string) $row['test_date']) ?></td><td><?= htmlspecialchars((string) $row['result_status']) ?><br><span class="small text-body-secondary"><?= htmlspecialchars((string) $row['status']) ?></span></td><td><?= htmlspecialchars((string) ($row['source_snapshot_status'] ?: '—')) ?></td><td><span class="badge text-bg-<?= ($row['computed_status'] ?? '') === 'legacy' ? 'secondary' : (($row['computed_status'] ?? '') === 'data_missing' ? 'warning text-dark' : 'info') ?>"><?= htmlspecialchars((string) $row['computed_status']) ?></span></td><td><?= htmlspecialchars((string) $row['classification']) ?></td><td><?= !empty($row['expected_legacy']) ? 'muss Legacy sein' : 'aktuelle Prüfung' ?></td></tr><tr><td colspan="8"><details><summary class="small">Rohdaten anzeigen</summary><pre class="small text-wrap mt-2 mb-0"><?= htmlspecialchars(json_encode($row, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}') ?></pre></details></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
  </div></section>
  <?php endif; ?>
</div>
