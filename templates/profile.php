<?php
/** @var \RedBeanPHP\OODBBean $user */
/** @var string $signature */
/** @var array<int, array<string, string>> $followups */
?>
<header class="page-header mb-4">
  <h1 class="mb-1"><i class="fa-solid fa-user-pen me-2" aria-hidden="true"></i>Mein Profil</h1>
  <p class="mb-0 text-body-secondary">Persönliche Angaben für die Prüf-Dokumentation</p>
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
        <form method="post" enctype="multipart/form-data" class="vstack gap-3">
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
        </form>
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
        <form method="post" class="vstack gap-3">
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
        </form>
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
