<?php
/** @var \RedBeanPHP\OODBBean $user */
/** @var string $signature */
/** @var array<int, array<string, string>> $followups */
/** @var array<int, array<string, string>> $certificates */
 $canEdit = $canEdit ?? true; $profileUrl = $profileUrl ?? url_for('profil'); $certificates = $certificates ?? [];
?>
<header class="page-header mb-4">
  <h1 class="mb-1"><i class="fa-solid fa-user-pen me-2" aria-hidden="true"></i><?= !empty($adminView) ? 'Benutzerprofil' : 'Mein Profil' ?></h1>
  <p class="mb-0 text-body-secondary">Persönliche Angaben für die Prüf-Dokumentation<?= !empty($adminView) ? ' · ' . htmlspecialchars((string) ($user->name ?? $user->email ?? '')) : '' ?></p>
</header>

<div class="row g-4">
  <div class="col-12 col-xl-7">
    <section class="card shadow-sm">
      <div class="card-header fw-semibold"><i class="fa-solid fa-signature me-2" aria-hidden="true"></i>Unterschrift für Prüfberichte</div>
      <div class="card-body">
        <p class="text-body-secondary">Die Unterschrift wird in neu erzeugte Prüfberichte übernommen, wenn du als Prüfer/in eingetragen bist. Am besten eignet sich eine freigestellte PNG-Datei.</p>
        <?php if ($signature !== ''): ?>
          <div class="border rounded-2 bg-white p-3 mb-3 d-inline-block">
            <img src="<?= htmlspecialchars($signature, ENT_QUOTES) ?>" alt="Gespeicherte Unterschrift" style="max-width:20rem;max-height:8rem">
          </div>
        <?php else: ?>
          <div class="alert alert-secondary"><i class="fa-solid fa-pen-nib me-2" aria-hidden="true"></i>Noch keine Unterschrift hinterlegt.</div>
        <?php endif; ?>
        <?php if ($canEdit): ?><form method="post" action="<?= htmlspecialchars($profileUrl, ENT_QUOTES) ?>" enctype="multipart/form-data" class="vstack gap-3">
          <div>
            <label for="report-signature" class="form-label"><i class="fa-solid fa-image me-1" aria-hidden="true"></i>&nbsp;Bilddatei</label>
            <input class="form-control" id="report-signature" name="report_signature" type="file" accept="image/png,image/jpeg" required>
            <div class="form-text">PNG oder JPEG, maximal 2 MB und 4000 × 2000 Pixel.</div>
          </div>
          <div class="d-flex gap-2 flex-wrap">
            <button class="btn btn-primary" type="submit" name="action" value="upload_signature"><i class="fa-solid fa-floppy-disk me-1" aria-hidden="true"></i>Unterschrift speichern</button>
            <?php if ($signature !== ''): ?>
              <button class="btn btn-danger" type="submit" name="action" value="delete_signature" formnovalidate onclick="return confirm('Gespeicherte Unterschrift wirklich entfernen?')"><i class="fa-solid fa-trash me-1" aria-hidden="true"></i>Entfernen</button>
            <?php endif; ?>
          </div>
        </form><?php endif; ?>
      </div>
    </section>
  </div>
  <div class="col-12 col-xl-5">
    <section class="card shadow-sm">
      <div class="card-header fw-semibold"><i class="fa-solid fa-id-card me-2" aria-hidden="true"></i>Kontodaten</div>
      <div class="card-body">
        <dl class="row mb-0">
          <dt class="col-sm-4">Name</dt><dd class="col-sm-8"><?= htmlspecialchars((string) ($user->name ?? '')) ?></dd>
          <dt class="col-sm-4">E-Mail</dt><dd class="col-sm-8 text-break"><?= htmlspecialchars((string) ($user->email ?? '')) ?></dd>
          <dt class="col-sm-4">Rolle</dt><dd class="col-sm-8"><span class="badge text-bg-secondary"><?= htmlspecialchars(role_label((string) ($user->role ?? 'user'))) ?></span></dd>
        </dl>
      </div>
    </section>
  </div>
  <div class="col-12 col-xl-7">
    <section class="card shadow-sm">
      <div class="card-header fw-semibold"><i class="fa-solid fa-graduation-cap me-2" aria-hidden="true"></i>Unterweisungen</div>
      <div class="card-body">
        <p class="text-body-secondary">Dokumentiere hier die Erstunterweisung und alle späteren Folgeunterweisungen. Diese Angaben bleiben im Benutzerprofil und können für Prüf- und Qualifikationsnachweise verwendet werden.</p>
        <?php if ($canEdit): ?><form method="post" action="<?= htmlspecialchars($profileUrl, ENT_QUOTES) ?>" class="vstack gap-3">
          <input type="hidden" name="action" value="save_instruction">
          <div>
            <label class="form-label" for="instruction-initial-date"><i class="fa-solid fa-calendar-check me-1" aria-hidden="true"></i>&nbsp;Erstunterweisung am</label>
            <input class="form-control" id="instruction-initial-date" name="instruction_initial_date" type="date" value="<?= htmlspecialchars((string) ($user->instruction_initial_date ?? ''), ENT_QUOTES) ?>">
          </div>
          <div>
            <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
              <label class="form-label mb-0"><i class="fa-solid fa-calendar-days me-1" aria-hidden="true"></i>&nbsp;Folgeunterweisungen</label>
              <button class="btn btn-sm btn-outline-primary" type="button" id="add-followup"><i class="fa-solid fa-plus me-1" aria-hidden="true"></i>Weitere hinzufügen</button>
            </div>
            <div id="followup-list" class="vstack gap-2">
              <?php $followupRows = $followups !== [] ? $followups : [['date' => '', 'topic' => '']]; ?>
              <?php foreach ($followupRows as $followup): ?>
                <div class="row g-2 align-items-end followup-row">
                  <div class="col-12 col-md-4"><label class="form-label small">Datum</label><input class="form-control" name="followup_date[]" type="date" value="<?= htmlspecialchars((string) ($followup['date'] ?? ''), ENT_QUOTES) ?>"></div>
                  <div class="col"><label class="form-label small">Thema / Hinweis</label><input class="form-control" name="followup_topic[]" type="text" maxlength="240" value="<?= htmlspecialchars((string) ($followup['topic'] ?? ''), ENT_QUOTES) ?>" placeholder="z. B. jährliche Wiederholungsunterweisung"></div>
                  <div class="col-auto"><button class="btn btn-outline-danger remove-followup" type="button" title="Eintrag entfernen"><i class="fa-solid fa-trash" aria-hidden="true"></i><span class="visually-hidden">Eintrag entfernen</span></button></div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
          <div>
            <label class="form-label" for="instruction-notes"><i class="fa-solid fa-note-sticky me-1" aria-hidden="true"></i>&nbsp;Notizen zu den Unterweisungen</label>
            <textarea class="form-control" id="instruction-notes" name="instruction_notes" rows="3" maxlength="1000" placeholder="Interne Hinweise, Unterweiser/in oder Nachweisablage"><?= htmlspecialchars((string) ($user->instruction_notes ?? '')) ?></textarea>
          </div>
          <div><button class="btn btn-primary" type="submit"><i class="fa-solid fa-floppy-disk me-1" aria-hidden="true"></i>Unterweisungen speichern</button></div>
        </form><?php else: ?>
          <dl class="row mb-0"><dt class="col-sm-4">Erstunterweisung</dt><dd class="col-sm-8"><?= htmlspecialchars((string) ($user->instruction_initial_date ?? '—')) ?></dd><dt class="col-sm-4">Folgeunterweisungen</dt><dd class="col-sm-8"><?= count($followups) ?></dd><dt class="col-sm-4">Notizen</dt><dd class="col-sm-8 text-break"><?= nl2br(htmlspecialchars((string) ($user->instruction_notes ?? '—'))) ?></dd></dl>
        <?php endif; ?>
      </div>
    </section>
  </div>
  <div class="col-12 col-xl-5">
    <section class="card shadow-sm">
      <div class="card-header fw-semibold"><i class="fa-solid fa-file-pdf me-2" aria-hidden="true"></i>Unterweisungsnachweise</div>
      <div class="card-body">
        <?php if ($certificates === []): ?><p class="text-body-secondary mb-3">Noch keine PDF-Zertifikate hinterlegt.</p><?php else: ?>
          <div class="list-group mb-3">
            <?php foreach ($certificates as $certificate): ?><div class="list-group-item d-flex justify-content-between align-items-start gap-2"><div><a href="<?= htmlspecialchars($adminView ? url_for('admin/nutzer/' . (int) $user->id . '/profil/nachweis/' . rawurlencode((string) $certificate['id'])) : url_for('profil/nachweis/' . rawurlencode((string) $certificate['id'])), ENT_QUOTES) ?>" target="_blank" rel="noopener"><i class="fa-solid fa-file-pdf me-1 text-danger" aria-hidden="true"></i><?= htmlspecialchars((string) ($certificate['title'] ?: $certificate['name'])) ?></a><div class="small text-body-secondary"><?= htmlspecialchars((string) ($certificate['kind'] ?? '')) ?> · <?= htmlspecialchars((string) ($certificate['date'] ?? '')) ?></div></div><?php if ($canEdit): ?><form method="post" action="<?= htmlspecialchars($profileUrl, ENT_QUOTES) ?>"><input type="hidden" name="action" value="delete_certificate"><input type="hidden" name="certificate_id" value="<?= htmlspecialchars((string) $certificate['id'], ENT_QUOTES) ?>"><button class="btn btn-sm btn-outline-danger" title="Nachweis entfernen" onclick="return confirm('Nachweis wirklich entfernen?')"><i class="fa-solid fa-trash" aria-hidden="true"></i><span class="visually-hidden">Nachweis entfernen</span></button></form><?php endif; ?></div><?php endforeach; ?>
          </div>
        <?php endif; ?>
        <?php if ($canEdit): ?><form method="post" action="<?= htmlspecialchars($profileUrl, ENT_QUOTES) ?>" enctype="multipart/form-data" class="vstack gap-2"><input type="hidden" name="action" value="upload_certificate"><label class="form-label mb-0" for="instruction-certificate"><i class="fa-solid fa-upload me-1" aria-hidden="true"></i>PDF-Zertifikat</label><input class="form-control" id="instruction-certificate" name="instruction_certificate" type="file" accept="application/pdf" required><div class="row g-2"><div class="col-6"><label class="form-label small" for="certificate-date">Datum</label><input class="form-control" id="certificate-date" name="certificate_date" type="date" required></div><div class="col-6"><label class="form-label small" for="certificate-kind">Art</label><select class="form-select" id="certificate-kind" name="certificate_kind"><option>Erstunterweisung</option><option selected>Folgeunterweisung</option></select></div></div><input class="form-control" name="certificate_title" maxlength="240" placeholder="Bezeichnung (optional)"><div class="form-text">PDF, maximal 10 MB.</div><button class="btn btn-primary" type="submit"><i class="fa-solid fa-floppy-disk me-1" aria-hidden="true"></i>Nachweis speichern</button></form><?php endif; ?>
      </div>
    </section>
  </div>
</div>
<script>
(() => {
  const list = document.getElementById('followup-list');
  const add = document.getElementById('add-followup');
  if (!list || !add) return;
  const bindRemove = (button) => button.addEventListener('click', () => {
    const rows = list.querySelectorAll('.followup-row');
    if (rows.length > 1) button.closest('.followup-row')?.remove();
    else button.closest('.followup-row')?.querySelectorAll('input').forEach((input) => { input.value = ''; });
  });
  list.querySelectorAll('.remove-followup').forEach(bindRemove);
  add.addEventListener('click', () => {
    const row = document.createElement('div');
    row.className = 'row g-2 align-items-end followup-row';
    row.innerHTML = '<div class="col-12 col-md-4"><label class="form-label small">Datum</label><input class="form-control" name="followup_date[]" type="date"></div><div class="col"><label class="form-label small">Thema / Hinweis</label><input class="form-control" name="followup_topic[]" type="text" maxlength="240" placeholder="z. B. jährliche Wiederholungsunterweisung"></div><div class="col-auto"><button class="btn btn-outline-danger remove-followup" type="button" title="Eintrag entfernen"><i class="fa-solid fa-trash" aria-hidden="true"></i><span class="visually-hidden">Eintrag entfernen</span></button></div>';
    list.appendChild(row);
    bindRemove(row.querySelector('.remove-followup'));
  });
})();
</script>
