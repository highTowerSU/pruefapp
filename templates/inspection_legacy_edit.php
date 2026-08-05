<div class="card shadow-sm">
  <div class="card-body">
    <div class="d-flex align-items-start justify-content-between gap-3 flex-wrap mb-3">
      <div><h1 class="h4 mb-1"><i class="fa-solid fa-box-archive me-2" aria-hidden="true"></i>Legacy-Prüfung bearbeiten</h1><p class="text-body-secondary mb-0"><?= htmlspecialchars((string) $device->external_number) ?> · <?= htmlspecialchars((string) $device->name) ?></p></div>
      <span class="badge text-bg-secondary">Bestand bis einschließlich 2024</span>
    </div>
    <div class="alert alert-info"><i class="fa-solid fa-circle-info me-1" aria-hidden="true"></i>Es werden nur Metadaten korrigiert. Ergebnis, Original-PDF und gesicherte Quelldaten bleiben unverändert.</div>
    <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars((string) $error) ?></div><?php endif; ?>
    <form method="post" class="row g-3">
      <div class="col-md-6"><label class="form-label" for="legacy-number"><i class="fa-solid fa-hashtag me-1" aria-hidden="true"></i>Prüfnummer</label><input class="form-control" id="legacy-number" name="external_number" required value="<?= htmlspecialchars((string) $inspection->external_number, ENT_QUOTES) ?>"></div>
      <div class="col-md-3"><label class="form-label" for="legacy-date"><i class="fa-solid fa-calendar-day me-1" aria-hidden="true"></i>Prüfdatum</label><input class="form-control" id="legacy-date" type="date" name="test_date" value="<?= htmlspecialchars((string) $inspection->test_date, ENT_QUOTES) ?>"></div>
      <div class="col-md-3"><label class="form-label" for="legacy-due"><i class="fa-solid fa-calendar-check me-1" aria-hidden="true"></i>Nächste Prüfung</label><input class="form-control" id="legacy-due" type="date" name="next_due_date" value="<?= htmlspecialchars((string) $inspection->next_due_date, ENT_QUOTES) ?>"></div>
      <div class="col-md-6"><label class="form-label" for="legacy-examiner"><i class="fa-solid fa-user-check me-1" aria-hidden="true"></i>Prüfer</label><select class="form-select" id="legacy-examiner" name="examiner"><option value="">Nicht hinterlegt</option><?php foreach ($users as $user): $value=trim((string) ($user->email ?? $user->name ?? '')); $label=trim((string) ($user->name ?? '')) ?: $value; ?><option value="<?= htmlspecialchars($value, ENT_QUOTES) ?>"<?= (string) $inspection->examiner === $value ? ' selected' : '' ?>><?= htmlspecialchars($label) ?></option><?php endforeach; ?></select></div>
      <div class="col-md-6"><label class="form-label" for="legacy-room"><i class="fa-solid fa-location-dot me-1" aria-hidden="true"></i>Historische Raumangabe</label><input class="form-control" id="legacy-room" name="room_snapshot" value="<?= htmlspecialchars((string) $inspection->room_snapshot, ENT_QUOTES) ?>"></div>
      <div class="col-12"><label class="form-label" for="legacy-notes"><i class="fa-solid fa-note-sticky me-1" aria-hidden="true"></i>Metadatenhinweis</label><textarea class="form-control" id="legacy-notes" name="metadata_notes" rows="3"><?= htmlspecialchars((string) ($inspection->metadata_notes ?? '')) ?></textarea></div>
      <div class="col-12 d-flex gap-2 justify-content-end"><a class="btn btn-secondary" href="<?= htmlspecialchars(url_for('admin/pruefungen/' . (int) $inspection->id), ENT_QUOTES) ?>"><i class="fa-solid fa-xmark me-1" aria-hidden="true"></i>Abbrechen</a><button class="btn btn-primary" type="submit"><i class="fa-solid fa-floppy-disk me-1" aria-hidden="true"></i>Metadaten speichern</button></div>
    </form>
  </div>
</div>
