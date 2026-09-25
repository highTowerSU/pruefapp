<div class="card"><div class="card-body">
<h1 class="h4">Neue Prüfung</h1>
<p class="text-body-secondary">Gerät: <strong><?= htmlspecialchars((string) $device->external_number) ?> · <?= htmlspecialchars((string) $device->name) ?></strong></p>
<?php if (!empty($error)): ?><div class="alert alert-danger"><?= htmlspecialchars((string) $error) ?></div><?php endif; ?>
<?= render_template('inspection_companion_panel.php', ['inspection' => $inspection, 'session' => $companionSession ?? []]) ?>
<?= render_template('inspection_companion_inbox.php', ['items' => InspectionCompanionInboxService::itemsForOwner((int) current_user()->id)]) ?>
<?= render_template('inspection_media_panel.php', ['inspection' => $inspection, 'inspectionMedia' => $inspectionMedia ?? []]) ?>
<?php $resultPresentation = InspectionEvaluationService::presentation((string) ($inspection->result_status ?? ''), (string) ($inspection->status ?? '')); $resultStatus = $resultPresentation['status']; $resultClass = $resultPresentation['class']; $resultLabel = $resultPresentation['label']; $measurementCount = count(InspectionDataService::measurements((int) $inspection->id)); if ($measurementCount === 0) { $measurementValues = json_decode((string) ($inspection->measurements_json ?? ''), true); $measurementCount = is_array($measurementValues) ? count($measurementValues) : 0; } ?>
<div class="alert alert-<?= $resultStatus === InspectionEvaluationService::FAILED ? 'danger' : ($resultStatus === InspectionEvaluationService::PASSED ? 'success' : 'warning') ?> d-flex flex-wrap align-items-center justify-content-between gap-2"><span><i class="fa-solid <?= htmlspecialchars($resultPresentation['icon'], ENT_QUOTES) ?> me-1" aria-hidden="true"></i><strong>Ergebnis: <?= htmlspecialchars($resultLabel) ?></strong><?php if ($resultStatus === InspectionEvaluationService::IN_PROGRESS): ?> · Prüfung noch nicht abgeschlossen.<?php elseif ($resultStatus === InspectionEvaluationService::DATA_MISSING): ?> · Erforderliche Angaben oder Messwerte fehlen.<?php endif; ?></span><span class="badge text-bg-secondary"><?= $measurementCount ?> Messwerte</span></div>
<form method="post" class="row g-3">
<div class="col-md-6"><label class="form-label">Neue Gerätenummer / Etikett</label><input class="form-control" name="external_number" value="<?= htmlspecialchars((string) (preg_replace('/-(?:\d{2}|20\d{2})$/', '', (string) $inspection->external_number) ?: $inspection->external_number)) ?>" required><div class="form-text">Jahreszusatz wird automatisch aus dem Prüfdatum ergänzt. Derzeitige Gerätenummer: <?= htmlspecialchars((string) (($device->external_number ?? '') ?: 'nicht hinterlegt')) ?>.</div></div><div class="col-md-3"><label class="form-label">Prüfdatum *</label><input class="form-control" type="date" name="test_date" value="<?= htmlspecialchars((string) $inspection->test_date) ?>" required><div class="form-text">Pflichtfeld; vorausgefüllt, bei Bedarf änderbar.</div></div><div class="w-100"></div>
<?php
$protectionChoices = [
    'I' => [
        'title' => 'Klasse I', 'symbol' => '⏚', 'icon' => 'fa-plug-circle-check',
        'description' => 'Schutzleiter vorhanden; metallische Schutzkontakte am Netzstecker.',
        'detail' => 'Auch IEC C5, C13 und C19 gehören bei Schutzleiteranschluss zu SK I.',
        'examples' => [
            ['schuko-deutsch.jpg', 'CEE 7/4 · Schuko-Stecker'],
            ['schuko.jpg', 'CEE 7/7 · Kombistecker'],
            ['iec-c5.svg', 'IEC C5 · Mickey Mouse'],
            ['iec-c13.svg', 'IEC C13 · Kaltgerät'],
            ['iec-c19.svg', 'IEC C19 · Kaltgerät'],
        ],
    ],
    'II' => [
        'title' => 'Klasse II', 'symbol' => '▣', 'icon' => 'fa-shield-halved',
        'description' => 'Doppelte Isolierung; kein Schutzleiter am Gerät.',
        'detail' => 'Typisch sind Euro-, Kontur- und IEC-C7-Anschlüsse.',
        'examples' => [
            ['euro-flach.jpg', 'CEE 7/16 · Eurostecker'],
            ['konturenstecker.jpg', 'CEE 7/17 · Konturenstecker'],
            ['iec-c7-real.svg', 'IEC C7 · liegende Acht'],
        ],
    ],
    'III' => [
        'title' => 'Klasse III', 'symbol' => '◇ III', 'icon' => 'fa-battery-half',
        'description' => 'Schutzkleinspannung; kein direkter Netzanschluss am Prüfobjekt.',
        'detail' => 'Die Versorgung erfolgt etwa über Akku, USB oder ein externes Netzteil.',
        'examples' => [
            ['dc-hohlstecker.jpg', 'DC-Hohlstecker'],
            ['usb.svg', 'USB-Anschluss'],
            ['batterie.svg', 'Batterie / Akku'],
        ],
    ],
    'Kabel' => [
        'title' => 'Kabel', 'symbol' => '⌁', 'icon' => 'fa-link',
        'description' => 'Anschluss- und Verlängerungsleitungen mit Schutzleiter.',
        'detail' => 'Zweipolige IEC-C7-Leitungen werden zusammen mit dem SK-II-Gerät geprüft.',
        'examples' => [
            ['kabel-schuko.jpg', 'Schuko-Verlängerung'],
            ['kabel-c13.svg', 'Schuko → IEC C13'],
            ['c5_power_cable.svg', 'IEC C5 · Kleeblattkabel'],
        ],
    ],
];
?>
<div class="col-12 protection-options">
  <h2 class="h5 mb-2">Schutzklasse bestimmen</h2>
  <div class="row g-2" role="radiogroup" aria-label="Schutzklasse bestimmen">
    <?php foreach ($protectionChoices as $class => $choice): ?>
      <div class="col-12 col-sm-6 col-xl-3">
        <label class="border rounded-3 p-3 d-grid h-100 protection-choice" data-sk="SK <?= htmlspecialchars($class, ENT_QUOTES) ?>">
          <input class="visually-hidden" type="radio" name="protection_class" value="<?= htmlspecialchars($class, ENT_QUOTES) ?>"<?= (string) $inspection->protection_class === $class ? ' checked' : '' ?>>
          <span class="d-flex align-items-center justify-content-between"><span class="sk-symbol" aria-hidden="true"><?= htmlspecialchars($choice['symbol']) ?></span><span class="badge text-bg-dark">SK <?= htmlspecialchars($class) ?></span></span>
          <span class="connector-grid">
            <?php foreach ($choice['examples'] as [$file, $caption]): ?>
              <span class="connector-example"><img src="<?= htmlspecialchars(url_for('public/img/stecker/' . $file), ENT_QUOTES) ?>" alt="<?= htmlspecialchars($caption, ENT_QUOTES) ?>" loading="lazy"><span class="connector-caption"><?= htmlspecialchars($caption) ?></span></span>
            <?php endforeach; ?>
          </span>
          <span class="connector-title"><i class="fa-solid <?= htmlspecialchars($choice['icon'], ENT_QUOTES) ?> fs-3" aria-hidden="true"></i><strong><?= htmlspecialchars($choice['title']) ?></strong></span>
          <span class="connector-description small text-body-secondary"><?= htmlspecialchars($choice['description']) ?><span class="d-block mt-1"><?= htmlspecialchars($choice['detail']) ?></span></span>
          <span class="badge rounded-pill text-bg-secondary connector-badge"><?= htmlspecialchars($choice['title']) ?></span>
        </label>
      </div>
    <?php endforeach; ?>
    <div class="col-12 mt-3"><label class="border rounded-3 p-3 d-block protection-special-choice"><input class="visually-hidden" type="radio" name="protection_class" value="Drehstrom"<?= (string) $inspection->protection_class === 'Drehstrom' ? ' checked' : '' ?>><span class="d-flex align-items-center gap-3"><span class="sk-symbol" aria-hidden="true">⚡ 3~</span><span><strong>CEE-Drehstromkabel 400 V</strong><span class="d-block small text-body-secondary">BENNING ST 725 · 16 A / 32 A · fünfpolige CEE-Leitung</span></span><span class="badge text-bg-secondary ms-auto">Sonderfall</span></span><img class="img-fluid rounded mt-3 d-block cee-drehstrom-image" src="<?= htmlspecialchars(url_for('public/img/stecker/cee-drehstrom-16a-verlaengerung.jpg'), ENT_QUOTES) ?>" alt="Rote fünfpolige CEE-Verlängerungsleitung mit Stecker und Kupplung" loading="lazy"><span class="d-block small text-body-secondary mt-2">Prüfung mit passendem BENNING-CEE-Messadapter am ST 725; passiv oder aktiv je nach Verfahren und Adapter.</span></label></div>
  </div>
</div>
<div class="col-md-6"><label class="form-label">Prüfer</label><?php if ($canChooseOtherExaminer): ?><select class="form-select" name="examiner" required><option value="">Bitte wählen</option><?php foreach ($users as $user): $label=trim((string) ($user->name ?? '')) !== '' ? trim((string) $user->name) : trim((string) ($user->email ?? '')); $value=trim((string) ($user->email ?? $user->name ?? '')); ?><option value="<?= htmlspecialchars($value, ENT_QUOTES) ?>"<?= (string) $inspection->examiner === $value ? ' selected' : '' ?>><?= htmlspecialchars($label) ?></option><?php endforeach; ?></select><?php else: ?><input class="form-control" value="<?= htmlspecialchars(display_examiner_name((string) $inspection->examiner)) ?>" readonly><input type="hidden" name="examiner" value="<?= htmlspecialchars((string) $inspection->examiner, ENT_QUOTES) ?>"><?php endif; ?></div>
<?php $deviceStorageSlotOptions = array_values(array_filter(DeviceStorageSlotService::fromDevice($device), static fn(array $row): bool => $row['number'] !== '')); ?>
<div class="col-md-3"><label class="form-label" for="inspection-storage-slot"><i class="fa-solid fa-hard-drive me-1" aria-hidden="true"></i>Speicherplatz</label><input class="form-control" id="inspection-storage-slot" name="storage_slot" list="inspection-storage-slot-options" value="<?= htmlspecialchars((string) $inspection->storage_slot, ENT_QUOTES) ?>" placeholder="wird nach Messung eingetragen"><datalist id="inspection-storage-slot-options"><?php foreach ($deviceStorageSlotOptions as $slotOption): ?><option value="<?= htmlspecialchars($slotOption['number'], ENT_QUOTES) ?>" label="<?= htmlspecialchars($slotOption['comment'], ENT_QUOTES) ?>"><?= htmlspecialchars($slotOption['comment']) ?></option><?php endforeach; ?></datalist><?php if ($deviceStorageSlotOptions !== []): ?><div class="form-text">Am Gerät hinterlegt: <?php foreach ($deviceStorageSlotOptions as $index => $slotOption): ?><?= $index > 0 ? ' · ' : '' ?><?= htmlspecialchars($slotOption['number']) ?><?= $slotOption['comment'] !== '' ? ' (' . htmlspecialchars($slotOption['comment']) . ')' : '' ?><?php endforeach; ?></div><?php endif; ?></div>
<div class="col-md-3"><label class="form-label" for="cable-length"><i class="fa-solid fa-ruler-horizontal me-1" aria-hidden="true"></i>Kabellänge</label><div class="input-group"><input class="form-control" id="cable-length" name="cable_length_m" type="number" min="0" step="0.1" inputmode="decimal" value="<?= htmlspecialchars((string) ($inspection->cable_length_m ?? ''), ENT_QUOTES) ?>"><span class="input-group-text">m</span></div><div class="form-text">Wird am Gerät übernommen und serverseitig für den RSL-Grenzwert ausgewertet.</div></div><div class="col-md-3"><label class="form-label d-block"><i class="fa-solid fa-temperature-high me-1" aria-hidden="true"></i>Wärmegerät</label><div class="form-check form-switch mt-2"><input class="form-check-input" type="checkbox" role="switch" id="warming-device" name="warming_device" value="1"<?= !empty($inspection->warming_device_snapshot ?? $device->warming_device) ? ' checked' : '' ?>><label class="form-check-label" for="warming-device">Heizelement vorhanden</label></div><div class="form-text">Wird am Gerät übernommen.</div></div>
<div class="col-md-6"><label class="form-label" for="next_due_date">Nächstes Prüfdatum *</label><input class="form-control" type="date" id="next_due_date" name="next_due_date" value="<?= htmlspecialchars((string) $inspection->next_due_date) ?>" required><div class="btn-group btn-group-sm mt-2" role="group" aria-label="Prüfintervall"><button type="button" class="btn btn-outline-secondary interval-btn" data-days="182">½ Jahr</button><button type="button" class="btn btn-outline-secondary interval-btn" data-days="365">1 Jahr</button><button type="button" class="btn btn-outline-secondary interval-btn" data-days="730">2 Jahre</button></div><div class="form-text">Pflichtfeld; vorgeschlagen ist ein Jahr nach dem Prüfdatum. Die Intervalle oder das Datum können geändert werden.</div></div>
<?php $plugChecklistLabel = (string) $inspection->protection_class === 'I' ? 'Metallische Schutzkontakte auf 6 und 12 Uhr vorhanden und unbeschädigt' : ((string) $inspection->protection_class === 'II' ? 'Keine metallischen Schutzkontakte auf 6 und 12 Uhr; Schutzisolierung erkennbar' : 'Stecker, Kontakte und Anschluss unbeschädigt'); $checklistValues = json_decode((string) ($inspection->checklist_json ?? ''), true) ?: []; ?>
<div class="col-12"><h2 class="h5 mb-3">Sichtprüfung und Funktionsprüfung</h2><div class="row g-3"><?php foreach (['label' => 'Beschriftung vollständig und lesbar', 'leitung' => 'Anschlussleitung und Zugentlastung ohne erkennbare Schäden', 'gehaeuse' => 'Gehäuse und Lüftungsöffnungen ohne erkennbare Schäden', 'stecker' => $plugChecklistLabel, 'funktion' => 'Sicherheitsrelevante Funktionen arbeiten ordnungsgemäß', 'safe_operation' => 'Ein sicherer Betrieb ist bis zur nächsten Prüfung zu erwarten', 'customer_notice' => 'Es sind keine zusätzlichen Hinweise oder Abweichungen für den Auftraggeber vorhanden'] as $key => $label): $status = ($checklistValues[$key] ?? '') === 'ok' ? 'ja' : (string) ($checklistValues[$key] ?? ''); ?><div class="col-md-6"><div class="border rounded-3 p-3 h-100 checklist-card"><div class="fs-5 mb-3"><?= htmlspecialchars($label) ?></div><div class="btn-group w-100 checklist-status" role="group" aria-label="Status: <?= htmlspecialchars($label, ENT_QUOTES) ?>"><?php foreach (['' => 'Offen', 'ja' => 'Ja', 'nein' => 'Nein'] as $value => $caption): ?><input class="btn-check" type="radio" name="checklist[<?= $key ?>]" id="checklist-<?= $key ?>-<?= $value === '' ? 'offen' : $value ?>" value="<?= $value ?>"<?= $status === $value ? ' checked' : '' ?>><label class="btn btn-outline-secondary py-3 fs-5" for="checklist-<?= $key ?>-<?= $value === '' ? 'offen' : $value ?>"><?= $caption ?></label><?php endforeach; ?></div></div></div><?php endforeach; ?></div></div>
<div class="col-md-3"><label class="form-label">Regiezeit (Minuten)</label><input class="form-control" type="number" min="0" name="regie_minutes" value="<?= (int) ($inspection->regie_minutes ?? 0) ?>"></div>
<div class="col-md-9"><label class="form-label">Begründung Regiezeit</label><input class="form-control" name="regie_reason" value="<?= htmlspecialchars((string) ($inspection->regie_reason ?? '')) ?>"></div>
<div class="col-12"><label class="form-label" for="inspection-notes"><i class="fa-solid fa-note-sticky me-1" aria-hidden="true"></i>Bemerkung zur Prüfung</label><textarea class="form-control" id="inspection-notes" name="metadata_notes" rows="3" placeholder="Interne Hinweise, Auffälligkeiten oder ergänzende Angaben"><?= htmlspecialchars((string) ($inspection->metadata_notes ?? '')) ?></textarea></div>
<div class="col-12"><label class="form-label" for="customer-hint"><i class="fa-solid fa-comment-dots me-1" aria-hidden="true"></i>Kundenhinweis</label><textarea class="form-control" id="customer-hint" name="customer_hint" rows="2" placeholder="Hinweis für den Auftraggeber; wird am Gerät nachverfolgt."><?= htmlspecialchars((string) ($inspection->customer_hint ?? '')) ?></textarea></div>
<div class="col-md-6"><label class="form-label" for="failed-action"><i class="fa-solid fa-screwdriver-wrench me-1" aria-hidden="true"></i>Maßnahme bei nicht bestanden</label><select class="form-select" id="failed-action" name="failed_action"><option value="">Nur bei nicht bestanden auswählen</option><?php foreach (['blocked' => 'Gesperrt', 'repair' => 'Zur Reparatur', 'disposed' => 'Entsorgt'] as $value => $label): ?><option value="<?= $value ?>"<?= (string) ($inspection->failed_action ?? '') === $value ? ' selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select><div class="form-text">Nicht bestandene Prüfungen bleiben berichtsfähig und abrechenbar.</div></div>
<div class="col-12 d-flex gap-2 flex-wrap"><button class="btn btn-outline-primary" name="complete" value="0"><i class="fa-solid fa-floppy-disk me-1" aria-hidden="true"></i>Zwischenspeichern</button><button class="btn btn-primary" name="complete" value="1"><i class="fa-solid fa-flag-checkered me-1" aria-hidden="true"></i>Prüfung serverseitig prüfen und abschließen</button><?php if ($resultStatus === InspectionEvaluationService::IN_PROGRESS && current_user_has_role('admin', 'editor')): ?><button class="btn btn-outline-danger" type="submit" data-double-confirm formaction="<?= htmlspecialchars(url_for('admin/pruefungen/' . (int) $inspection->id . '/loeschen'), ENT_QUOTES) ?>" formmethod="post"><i class="fa-solid fa-trash me-1" aria-hidden="true"></i>Prüfung löschen</button><?php endif; ?></div>
</form></div></div>
<style>
.protection-choice,.checklist-card{cursor:pointer;transition:border-color .15s,box-shadow .15s,background-color .15s}
.protection-choice:has(input:checked),.checklist-card:has(input:checked){border-color:var(--bs-primary)!important;box-shadow:0 0 0 .2rem rgba(var(--bs-primary-rgb),.2);background:rgba(var(--bs-primary-rgb),.04)}
.checklist-card:hover{border-color:var(--bs-primary)!important}
.checklist-status .btn{flex:1;min-height:3.25rem}
.sk-symbol{font:700 1.8rem/1 Arial,sans-serif;min-width:3rem;color:var(--bs-emphasis-color)}
@media(max-width:767.98px){
  form.row.g-3>.col-md-3,form.row.g-3>.col-md-6,form.row.g-3>.col-md-9{width:100%}
  .protection-choice{padding:1rem!important}
  form.row.g-3{--bs-gutter-y:1.25rem}
  .form-control,.form-select,.btn{min-height:48px}
  .form-label{font-weight:600;margin-bottom:.45rem}
  .input-group>.btn{padding-inline:1rem}
  .btn-group.checklist-status{display:flex}
  .checklist-status .btn{min-height:52px;padding-inline:.65rem}
  .col-12>.d-flex.gap-2{flex-wrap:wrap}
  .col-12>.d-flex.gap-2 .btn{flex:1 1 100%}
}
@media(max-width:575.98px){
  .card-body{padding:1rem}
  .protection-choice{padding:.8rem!important}
  .checklist-card{padding:1rem!important}
  .checklist-status .btn{font-size:1rem}
}
</style>
<style>
.checklist-status .btn[for$="-ja"]{color:var(--bs-success);border-color:var(--bs-success)}
.checklist-status .btn[for$="-nein"]{color:var(--bs-danger);border-color:var(--bs-danger)}
.checklist-status .btn[for$="-offen"]{color:var(--bs-secondary-color);border-color:var(--bs-secondary)}
.checklist-status .btn-check[value="ja"]:checked + .btn{color:#fff;background-color:var(--bs-success);border-color:var(--bs-success)}
.checklist-status .btn-check[value="nein"]:checked + .btn{color:#fff;background-color:var(--bs-danger);border-color:var(--bs-danger)}
.checklist-status .btn-check[value=""]:checked + .btn{color:var(--bs-body-color);background-color:var(--bs-secondary-bg);border-color:var(--bs-secondary)}
.protection-choice{display:grid!important;grid-template-rows:auto auto auto 1fr auto;align-items:start;height:100%;gap:.75rem}
.protection-choice:focus-within,.checklist-card:focus-within{outline:3px solid var(--bs-primary);outline-offset:3px}
.protection-special-choice{cursor:pointer;transition:border-color .15s,box-shadow .15s,background-color .15s}.protection-special-choice:has(input:checked){border-color:var(--bs-primary)!important;box-shadow:0 0 0 .2rem rgba(var(--bs-primary-rgb),.2);background:rgba(var(--bs-primary-rgb),.04)}.protection-special-choice:focus-within{outline:3px solid var(--bs-primary);outline-offset:3px}
.cee-drehstrom-image{width:100%;max-height:180px;object-fit:contain;background:#fff;padding:.35rem}
.checklist-status .btn-check:focus-visible + .btn,.interval-btn:focus-visible,.btn:focus-visible,.form-control:focus-visible,.form-select:focus-visible{box-shadow:0 0 0 .25rem rgba(var(--bs-primary-rgb),.35)}
.connector-grid{grid-row:2;display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.75rem;align-content:start}
.connector-example{display:grid;grid-template-rows:88px auto;align-items:center;text-align:center;margin:0;padding:.6rem;border:1px solid var(--bs-border-color);border-radius:.5rem;background:var(--bs-tertiary-bg)}
.connector-example img{width:100%;height:84px;object-fit:contain;background:transparent}
.connector-caption{font-size:.72rem;line-height:1.2;color:var(--bs-secondary-color);padding-top:.35rem}
.connector-title{grid-row:3;display:flex;align-items:center;gap:.5rem;margin-top:.5rem}.connector-title i{margin:0!important}.connector-title strong{font-size:1.05rem}
.connector-description{grid-row:4;margin-top:.35rem}.connector-badge{grid-row:5;align-self:end;justify-self:start;margin-top:.25rem}
.card-body>form.row.g-3{row-gap:2.25rem}.inspection-data-heading,.regie-heading{padding-top:.25rem}.col-12>h2{margin-bottom:.25rem}
.criteria-catalog{border:1px solid var(--bs-border-color);border-left:4px solid var(--bs-primary);border-radius:.5rem;padding:1rem 1.25rem;background:var(--bs-tertiary-bg)}.criteria-catalog ul{padding-left:1.25rem}.criteria-catalog li+li{margin-top:.45rem}
@media(max-width:575.98px){.connector-grid{gap:.6rem}.connector-example{grid-template-rows:72px auto;padding:.45rem}.connector-example img{height:68px}}
</style>
<script>
(() => {
  const date = document.querySelector('[name="test_date"]');
  const next = document.getElementById('next_due_date');
  const type = document.querySelector('[name="inspection_type"]');
  const protection = [...document.querySelectorAll('[name="protection_class"]')];
  const form = document.querySelector('form.row.g-3');
  if (form) {
    const examinerBlock = form.querySelector('[name="examiner"]')?.closest('.col-md-6');
    const storageBlock = form.querySelector('[name="storage_slot"]')?.closest('[class*="col-md-"]');
    const nextBlock = form.querySelector('[name="next_due_date"]')?.closest('.col-md-6');
    if (examinerBlock && !form.querySelector('.inspection-data-heading')) { const heading = document.createElement('div'); heading.className = 'col-12 inspection-data-heading'; heading.innerHTML = '<h2 class="h5 mb-0">Prüfungsdaten</h2>'; examinerBlock.before(heading); }
    [examinerBlock, storageBlock, nextBlock].forEach(block => { if (block) { block.classList.remove('col-md-6', 'col-md-3'); block.classList.add('col-md-4'); } });
    const checklistSection = [...form.querySelectorAll('.col-12')].find(block => block.querySelector(':scope > h2')?.textContent.includes('Sichtprüfung'));
    checklistSection?.querySelectorAll(':scope > .row > .col-md-6').forEach(block => { block.classList.remove('col-md-6'); block.classList.add('col-12'); });
    const regieBlock = form.querySelector('[name="regie_minutes"]')?.closest('.col-md-3');
    const reasonBlock = form.querySelector('[name="regie_reason"]')?.closest('.col-md-9');
    if (regieBlock && !form.querySelector('.regie-heading')) { const heading = document.createElement('div'); heading.className = 'col-12 regie-heading'; heading.innerHTML = '<h2 class="h5 mb-0">Regiezeit</h2>'; regieBlock.before(heading); }
    [regieBlock, reasonBlock].forEach(block => { if (block) { block.classList.remove('col-md-3', 'col-md-9'); block.classList.add('col-12'); } });
  }
  const criteriaByClass = {
    I: {title:'Kriterienkatalog · Schutzklasse I', items:['Schutzleiterwiderstand RSL: ≤ 0,3 Ω bis 5 m Leitungslänge; danach +0,1 Ω je weitere 7,5 m, maximal 1 Ω.','Isolationswiderstand RISO: mindestens 1 MΩ; bei Geräten mit Heizelementen mindestens 0,3 MΩ.','Schutzleiterstrom IPE (Differenzstrom): maximal 3,5 mA an leitfähigen Teilen mit PE-Verbindung.','Berührungsstrom IB: kleiner als 0,5 mA an allen berührbaren leitfähigen Teilen.','Hinweis: Heizgeräte, Netzfilter und lange Leitungen können abweichende Messwerte verursachen und müssen besonders bewertet werden.']},
    II: {title:'Kriterienkatalog · Schutzklasse II', items:['Kein Schutzleiter: keine metallischen Schutzkontakte auf 6 und 12 Uhr.','Isolationswiderstand RISO: mindestens 1 MΩ; bei Geräten mit Heizelementen mindestens 0,3 MΩ.','Berührungsstrom IB: kleiner als 0,5 mA an allen berührbaren leitfähigen Teilen.','Berührbare leitfähige Teile und doppelte/verstärkte Isolierung besonders auf Beschädigungen prüfen.','Hinweis: IEC C7 und andere zweipolige Leitungen werden gemeinsam mit dem zugehörigen Gerät bewertet.']},
    III: {title:'Kriterienkatalog · Schutzklasse III', items:['Nur Schutzkleinspannung: kein direkter Netzanschluss am Prüfobjekt.','Versorgung, Polarität, Akku/Batterie und Kleinspannungsanschluss auf Beschädigung und sicheren Sitz prüfen.','Messgrenzen richten sich nach der Gerätespezifikation und dem verwendeten Netzteil.']},
    Kabel: {title:'Kriterienkatalog · Anschluss- und Verlängerungsleitung', items:['Schutzleiterwiderstand RSL: ≤ 0,3 Ω bis 5 m Leitungslänge; danach +0,1 Ω je weitere 7,5 m, maximal 1 Ω.','Isolationswiderstand RISO: mindestens 1 MΩ.','Schutzleiterstrom IPE (Differenzstrom): maximal 3,5 mA; Berührungsstrom IB: kleiner als 0,5 mA.','Leitungslänge und Leitungsquerschnitt dokumentieren; Stecker, Kupplung, Zugentlastung, Isolation und Aderanschlüsse prüfen.']},
    Drehstrom: {title:'Sonderfall · CEE-Drehstrom-Verlängerungsleitung mit BENNING ST 725', items:['Geeignet für CEE-Verlängerungsleitungen und dreiphasige Betriebsmittel mit 16 A oder 32 A, 400 V und fünfpoligem CEE-Anschluss.','Einen zum BENNING ST 725 passenden CEE-Messadapter verwenden. Je nach Prüfablauf ist ein passiver oder aktiver Adapter erforderlich.','Sichtprüfung von Leitung, Stecker und Kupplung; Schutzleiterwiderstand, Durchgang von L1, L2, L3 und N, Leiterzuordnung, Unterbrechungen, Verwechslungen und Kurzschlüsse prüfen.','Isolationswiderstand messen; gegebenenfalls zusätzlich Phasenfolge beziehungsweise Drehfeld prüfen.','Passive Messungen erfolgen spannungsfrei. Aktive Drehstromprüfungen ausschließlich mit einem dafür vorgesehenen BENNING-CEE-Messadapter und entsprechend der Bedienungsanleitung durchführen. Den Prüfling niemals ohne geeigneten Messadapter über das Prüfgerät an das Drehstromnetz anschließen.','Eine Strom- beziehungsweise Leckstrommesszange ist nur erforderlich, wenn das gewählte Prüfverfahren eine Ableit- oder Leckstrommessung über eine Stromzange vorsieht.','Dokumentieren: 16 A oder 32 A, Polzahl, Nennspannung, Leitungslänge, Leiterquerschnitt, Messadapter, passive oder aktive Prüfung, Messwerte sowie Leiterzuordnung und Durchgang.']}
  };
  const warmingDevice = <?= !empty($device->warming_device) ? 'true' : 'false' ?>;
  const protectionBlock = form?.querySelector('.protection-options');
  const criteriaPanel = protectionBlock ? document.createElement('section') : null;
  if (criteriaPanel && !document.getElementById('criteria-catalog')) {
    criteriaPanel.id = 'criteria-catalog';
    criteriaPanel.className = 'col-12 criteria-catalog';
    protectionBlock.after(criteriaPanel);
    const renderCriteria = () => {
      const selected = protection.find(input => input.checked)?.value;
      const criteria = criteriaByClass[selected];
      criteriaPanel.innerHTML = criteria ? `<h2 class="h5 mb-2">${criteria.title} <span class="badge text-bg-${warmingDevice ? 'warning' : 'secondary'} ms-2">Wärmegerät: ${warmingDevice ? 'Ja' : 'Nein'}</span></h2><ul class="mb-0">${criteria.items.map(item => `<li>${item}</li>`).join('')}</ul>${selected === 'I' || selected === 'Kabel' ? '<div class="rsl-calculator mt-3"><strong>Vorschau des RSL-Grenzwerts:</strong> <output class="badge text-bg-secondary rsl-result">≤ 0,30 Ω</output><div class="form-text">Die verbindliche Entscheidung und Verifizierung erfolgt im Backend anhand der gespeicherten Kabellänge.</div></div>' : ''}` : '<h2 class="h5 mb-2">Kriterienkatalog</h2><p class="mb-0 text-body-secondary">Bitte zuerst eine Schutzklasse auswählen.</p>';
      const lengthInput = form?.querySelector('[name="cable_length_m"]');
      const result = criteriaPanel.querySelector('.rsl-result');
      const updateLimitHint = () => { if (!lengthInput || !result) return; const length = Number(lengthInput.value); const steps = !Number.isFinite(length) || length <= 5 ? 0 : Math.ceil((length - 5) / 7.5); result.value = `≤ ${Math.min(1, 0.3 + steps * 0.1).toFixed(2).replace('.', ',')} Ω`; };
      if (lengthInput && result) { lengthInput.addEventListener('input', updateLimitHint); updateLimitHint(); }
    };
    protection.forEach(input => input.addEventListener('change', renderCriteria));
    renderCriteria();
  }
  if (!date || !next) return;
  const update = days => { if (!date.value) return; const d = new Date(date.value + 'T12:00:00'); d.setDate(d.getDate() + Number(days)); next.value = d.toISOString().slice(0, 10); };
  document.querySelectorAll('.interval-btn').forEach(button => button.addEventListener('click', () => update(button.dataset.days)));
  date.addEventListener('change', () => update(365));
  const syncType = () => { const selected = protection.find(input => input.checked); if (selected && type) type.value = ({I:'Schutzklasse I', II:'Schutzklasse II', III:'Schutzklasse III', Kabel:'Kabelprüfung', Drehstrom:'CEE-Drehstromprüfung 400 V'})[selected.value] || ''; };
  const syncChecklist = () => { const selected = protection.find(input => input.checked); const label = document.querySelectorAll('.checklist-card > div:first-child')[3]; if (label) label.textContent = selected?.value === 'I' ? 'Metallische Schutzkontakte auf 6 und 12 Uhr vorhanden und unbeschädigt' : (selected?.value === 'II' ? 'Keine metallischen Schutzkontakte auf 6 und 12 Uhr; Schutzisolierung erkennbar' : 'Stecker, Kontakte und Anschluss unbeschädigt'); };
  protection.forEach(input => input.addEventListener('change', () => { syncType(); syncChecklist(); })); syncType(); syncChecklist();
})();
</script>
