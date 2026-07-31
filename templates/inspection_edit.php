<div class="card"><div class="card-body">
<h1 class="h4">Neue Prüfung</h1>
<p class="text-body-secondary">Gerät: <strong><?= htmlspecialchars((string) $device->external_number) ?> · <?= htmlspecialchars((string) $device->name) ?></strong></p>
<?php if (!empty($error)): ?><div class="alert alert-danger"><?= htmlspecialchars((string) $error) ?></div><?php endif; ?>
<form method="post" class="row g-3">
<div class="col-md-3"><label class="form-label">Prüfdatum</label><input class="form-control" type="date" name="test_date" value="<?= htmlspecialchars((string) $inspection->test_date) ?>" required><div class="form-text">Vorausgefüllt, bei Bedarf änderbar.</div></div><div class="w-100"></div>
<div class="col-md-9"><label class="form-label">Schutzklasse bestimmen</label><div class="row g-2" role="radiogroup"><?php foreach ([['I','Klasse I','Metallische Schutzkontakte auf 6 und 12 Uhr; Schutzleiter vorhanden. Dazu gehören Mickey-Mouse- (IEC C5) und Kaltgeräteanschlüsse (IEC C13/C19).','fa-plug-circle-check','SK I','⏚','CEE 7/4 Schuko · CEE 7/7 Kombi','public/img/stecker/schuko-deutsch.jpg'],['II','Klasse II','Keine metallischen Schutzkontakte auf 6 und 12 Uhr; doppelte Isolierung. Typisch ist die liegende Acht (IEC C7).','fa-shield-halved','SK II','▣','CEE 7/16 Euro · CEE 7/17 Kontur','public/img/stecker/euro-flach.jpg'],['III','Klasse III','Kleinspannung ohne Netzanschluss','fa-battery-half','SK III','◇ III','DC-Hohlstecker · Batterie','public/img/stecker/dc-hohlstecker.jpg'],['Kabel','Kabel','Kaltgeräte-/Verlängerungskabel','fa-link','SK Kabel','⌁','Kabel','']] as [$class, $title, $hint, $icon, $skLabel, $symbol, $plugLabel, $image]): ?><div class="col-6 col-xl-3"><label class="border rounded p-2 d-block h-100 protection-choice" data-sk="<?= htmlspecialchars($skLabel, ENT_QUOTES) ?>"><input class="visually-hidden" type="radio" name="protection_class" value="<?= $class ?>"<?= (string) $inspection->protection_class === $class ? ' checked' : '' ?>><span class="d-flex align-items-center justify-content-between mb-2"><span class="sk-symbol" aria-hidden="true"><?= htmlspecialchars($symbol) ?></span><span class="badge text-bg-dark"><?= htmlspecialchars($skLabel) ?></span></span><?php if ($image !== ''): ?><img class="img-fluid rounded mb-2 protection-plug-image" src="<?= htmlspecialchars(url_for($image), ENT_QUOTES) ?>" alt="Beispiel: <?= htmlspecialchars($plugLabel, ENT_QUOTES) ?>" loading="lazy"><?php endif; ?><?php if ($class === 'I'): ?><img class="img-fluid rounded mb-2 protection-plug-image" src="<?= htmlspecialchars(url_for('public/img/stecker/schuko.jpg'), ENT_QUOTES) ?>" alt="CEE 7/7 Kombi-Stecker" loading="lazy"><?php endif; ?><?php if ($class === 'II'): ?><img class="img-fluid rounded mb-2 protection-plug-image" src="<?= htmlspecialchars(url_for('public/img/stecker/konturenstecker.jpg'), ENT_QUOTES) ?>" alt="CEE 7/17 Konturenstecker" loading="lazy"><?php endif; ?><i class="fa-solid <?= $icon ?> fs-3 d-block mb-1" aria-hidden="true"></i><strong><?= $title ?></strong><span class="d-block small text-body-secondary"><?= $hint ?></span><?php if ($class === 'I'): ?><span class="d-block small text-body-secondary mt-1">CEE 7/4 und CEE 7/7 gehören wegen ihrer Schutzkontakte zu SK I; IEC C5, C13 und C19 werden hier ebenfalls eingeordnet.</span><?php elseif ($class === 'II'): ?><span class="d-block small text-body-secondary mt-1">CEE 7/16 und CEE 7/17 gehören zu SK II.</span><?php endif; ?><span class="badge rounded-pill text-bg-light border mt-2"><?= htmlspecialchars($plugLabel) ?></span></label></div><?php endforeach; ?></div></div>
<div class="col-md-6"><label class="form-label">Prüfer</label><?php if ($canChooseOtherExaminer): ?><select class="form-select" name="examiner" required><option value="">Bitte wählen</option><?php foreach ($users as $user): $label=trim((string) ($user->name ?? '')) . (trim((string) ($user->email ?? '')) !== '' ? ' · ' . $user->email : ''); $value=trim((string) ($user->email ?? $user->name ?? '')); ?><option value="<?= htmlspecialchars($value, ENT_QUOTES) ?>"<?= (string) $inspection->examiner === $value ? ' selected' : '' ?>><?= htmlspecialchars($label) ?></option><?php endforeach; ?></select><?php else: ?><input class="form-control" value="<?= htmlspecialchars((string) $inspection->examiner) ?>" readonly><input type="hidden" name="examiner" value="<?= htmlspecialchars((string) $inspection->examiner, ENT_QUOTES) ?>"><?php endif; ?></div>
<div class="col-md-3"><label class="form-label">Speicherplatz</label><input class="form-control" name="storage_slot" value="<?= htmlspecialchars((string) $inspection->storage_slot) ?>" placeholder="wird nach Messung eingetragen"></div>
<div class="col-md-6"><label class="form-label">Nächste Prüfung</label><div class="input-group"><input class="form-control next-date-suggestion" type="date" id="next_due_date" name="next_due_date" value="<?= htmlspecialchars((string) $inspection->next_due_date) ?>"><button class="btn btn-outline-success" type="button" id="confirm_next_due">Übernehmen</button></div><div class="btn-group btn-group-sm mt-2" role="group" aria-label="Prüfintervall"><button type="button" class="btn btn-outline-secondary interval-btn" data-days="182">½ Jahr</button><button type="button" class="btn btn-outline-secondary interval-btn" data-days="365">1 Jahr</button><button type="button" class="btn btn-outline-secondary interval-btn" data-days="730">2 Jahre</button></div><div class="form-text">Vorschlag: ein Jahr nach dem Prüfdatum. Gelb markiert, bis bestätigt.</div></div>
<div class="d-flex flex-wrap gap-2 mt-2 plug-variant-gallery"><figure><img src="<?= htmlspecialchars(url_for('public/img/stecker/iec-c5-schematic.svg'), ENT_QUOTES) ?>" alt="IEC C5 Mickey-Mouse-Kabel" loading="lazy"><figcaption>IEC C5 · SK I</figcaption></figure><figure><img src="<?= htmlspecialchars(url_for('public/img/stecker/iec-c13-schematic.svg'), ENT_QUOTES) ?>" alt="IEC C13 Kaltgeräteanschluss" loading="lazy"><figcaption>IEC C13 · SK I</figcaption></figure><figure><img src="<?= htmlspecialchars(url_for('public/img/stecker/iec-c19-schematic.svg'), ENT_QUOTES) ?>" alt="IEC C19 Kaltgeräteanschluss" loading="lazy"><figcaption>IEC C19 · SK I</figcaption></figure><figure><img src="<?= htmlspecialchars(url_for('public/img/stecker/iec-c7.svg'), ENT_QUOTES) ?>" alt="IEC C7 liegende Acht" loading="lazy"><figcaption>IEC C7 · SK II</figcaption></figure></div>
<?php $plugChecklistLabel = (string) $inspection->protection_class === 'I' ? 'Metallische Schutzkontakte auf 6 und 12 Uhr vorhanden und unbeschädigt' : ((string) $inspection->protection_class === 'II' ? 'Keine metallischen Schutzkontakte auf 6 und 12 Uhr; Schutzisolierung erkennbar' : 'Stecker, Kontakte und Anschluss unbeschädigt'); $checklistValues = json_decode((string) ($inspection->checklist_json ?? ''), true) ?: []; ?>
<div class="col-12"><h2 class="h5 mb-3">Sichtprüfung und Funktionsprüfung</h2><div class="row g-3"><?php foreach (['label' => 'Beschriftung vollständig und lesbar', 'leitung' => 'Anschlussleitung und Zugentlastung ohne erkennbare Schäden', 'gehaeuse' => 'Gehäuse und Lüftungsöffnungen ohne erkennbare Schäden', 'stecker' => $plugChecklistLabel, 'funktion' => 'Sicherheitsrelevante Funktionen arbeiten ordnungsgemäß'] as $key => $label): $status = ($checklistValues[$key] ?? '') === 'ok' ? 'ja' : (string) ($checklistValues[$key] ?? ''); ?><div class="col-md-6"><div class="border rounded-3 p-3 h-100 checklist-card"><div class="fs-5 mb-3"><?= htmlspecialchars($label) ?></div><div class="btn-group w-100 checklist-status" role="group" aria-label="Status: <?= htmlspecialchars($label, ENT_QUOTES) ?>"><?php foreach (['' => 'Offen', 'ja' => 'Ja', 'nein' => 'Nein'] as $value => $caption): ?><input class="btn-check" type="radio" name="checklist[<?= $key ?>]" id="checklist-<?= $key ?>-<?= $value === '' ? 'offen' : $value ?>" value="<?= $value ?>"<?= $status === $value ? ' checked' : '' ?>><label class="btn btn-outline-secondary py-3 fs-5" for="checklist-<?= $key ?>-<?= $value === '' ? 'offen' : $value ?>"><?= $caption ?></label><?php endforeach; ?></div></div></div><?php endforeach; ?></div></div>
<div class="col-md-3"><label class="form-label">Regiezeit (Minuten)</label><input class="form-control" type="number" min="0" name="regie_minutes" value="<?= (int) ($inspection->regie_minutes ?? 0) ?>"></div>
<div class="col-md-9"><label class="form-label">Begründung Regiezeit</label><input class="form-control" name="regie_reason" value="<?= htmlspecialchars((string) ($inspection->regie_reason ?? '')) ?>"></div>
<div class="col-12 d-flex gap-2"><button class="btn btn-outline-primary" name="complete" value="0">Zwischenspeichern</button><button class="btn btn-primary" name="complete" value="1">Prüfung abschließen</button></div>
</form></div></div>
<style>.protection-choice,.checklist-card{cursor:pointer;transition:border-color .15s,box-shadow .15s,background-color .15s}.protection-choice:has(input:checked),.checklist-card:has(input:checked){border-color:var(--bs-primary)!important;box-shadow:0 0 0 .2rem rgba(var(--bs-primary-rgb),.2);background:rgba(var(--bs-primary-rgb),.04)}.checklist-card:hover{border-color:var(--bs-primary)!important}.checklist-status .btn{flex:1;min-height:3.25rem}.sk-symbol{font:700 1.8rem/1 Arial,sans-serif;min-width:3rem;color:var(--bs-emphasis-color)}.protection-plug-image{display:block;width:100%;height:86px;object-fit:contain;background:#fff}.protection-choice[data-sk="SK Kabel"]:before{content:"";display:block;height:172px;margin-bottom:.5rem}.plug-variant-gallery figure{margin:0;width:110px}.plug-variant-gallery img{width:110px;height:60px;object-fit:contain;background:#fff;border-radius:7px;padding:4px}.plug-variant-gallery figcaption{font-size:.72rem;text-align:center;color:var(--bs-secondary-color)}.plug-variant-mini{display:flex;gap:.35rem;flex-wrap:wrap;margin-top:.5rem}.plug-variant-mini-item{margin:0;width:78px;text-align:center}.plug-variant-mini-item img{width:78px;height:42px;object-fit:contain;background:#fff;border-radius:4px;padding:2px}.plug-variant-mini-item figcaption{font-size:.62rem;line-height:1.1;color:var(--bs-secondary-color)}form.row.g-3>.w-100+.col-md-9{width:100%}@media(max-width:767.98px){form.row.g-3>.col-md-3,form.row.g-3>.col-md-6,form.row.g-3>.col-md-9{width:100%}.protection-choice{padding:1rem!important}.protection-plug-image{height:72px}.protection-choice[data-sk="SK Kabel"]:before{height:144px}form.row.g-3{--bs-gutter-y:1.25rem}.form-control,.form-select,.btn{min-height:48px}.form-label{font-weight:600;margin-bottom:.45rem}.input-group>.btn{padding-inline:1rem}.btn-group.checklist-status{display:flex}.checklist-status .btn{min-height:52px;padding-inline:.65rem}.col-12>.d-flex.gap-2{flex-wrap:wrap}.col-12>.d-flex.gap-2 .btn{flex:1 1 100%}.plug-variant-gallery{justify-content:space-between}.plug-variant-gallery figure{flex:1 1 30%;width:auto}.plug-variant-gallery img{width:100%;height:64px;touch-action:manipulation}}@media(max-width:575.98px){form.row.g-3>.w-100+.col-md-9 .col-6{width:100%}.card-body{padding:1rem}.protection-choice{padding:.8rem!important}.checklist-card{padding:1rem!important}.checklist-status .btn{font-size:1rem}}</style>
<style>
.checklist-status .btn[for$="-ja"]{color:var(--bs-success);border-color:var(--bs-success)}
.checklist-status .btn[for$="-nein"]{color:var(--bs-danger);border-color:var(--bs-danger)}
.checklist-status .btn[for$="-offen"]{color:var(--bs-secondary-color);border-color:var(--bs-secondary)}
.checklist-status .btn-check[value="ja"]:checked + .btn{color:#fff;background-color:var(--bs-success);border-color:var(--bs-success)}
.checklist-status .btn-check[value="nein"]:checked + .btn{color:#fff;background-color:var(--bs-danger);border-color:var(--bs-danger)}
.checklist-status .btn-check[value=""]:checked + .btn{color:var(--bs-body-color);background-color:var(--bs-secondary-bg);border-color:var(--bs-secondary)}
.protection-plug-image{height:112px}
.plug-variant-mini-item img{width:92px;height:58px}
@media(max-width:767.98px){.protection-plug-image{height:96px}.plug-variant-mini-item{width:92px}.plug-variant-mini-item img{width:92px;height:56px}}
.protection-choice{display:grid!important;grid-template-rows:auto 400px 40px minmax(180px,auto) auto;align-items:start;min-height:760px;height:100%;gap:.75rem}
.protection-choice:focus-within,.checklist-card:focus-within{outline:3px solid var(--bs-primary);outline-offset:3px}
.checklist-status .btn-check:focus-visible + .btn,.interval-btn:focus-visible,.btn:focus-visible,.form-control:focus-visible,.form-select:focus-visible{box-shadow:0 0 0 .25rem rgba(var(--bs-primary-rgb),.35)}
.protection-choice[data-sk="SK Kabel"]:before{display:none!important}
.connector-grid{grid-row:2;display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.75rem;align-content:start;height:400px;min-height:400px}
.connector-example{display:grid;grid-template-rows:88px auto;align-items:center;text-align:center;margin:0;padding:.6rem;border:1px solid var(--bs-border-color);border-radius:.5rem;background:var(--bs-tertiary-bg)}
.connector-example img{width:100%;height:84px;object-fit:contain;background:transparent}
.connector-example figcaption{font-size:.72rem;line-height:1.2;color:var(--bs-secondary-color);padding-top:.35rem}
.connector-empty{grid-column:1/-1;min-height:220px;display:grid;place-items:center;text-align:center;color:var(--bs-secondary-color);border:1px dashed var(--bs-border-color);border-radius:.5rem}
.connector-title{grid-row:3;display:flex;align-items:center;gap:.5rem;margin-top:.5rem}.connector-title i{margin:0!important}.connector-title strong{font-size:1.05rem}
.connector-description{grid-row:4;min-height:180px;margin-top:.35rem}.connector-badge{grid-row:5;align-self:end;justify-self:start;margin-top:.25rem}
.card-body>form.row.g-3{row-gap:2.25rem}.inspection-data-heading,.regie-heading{padding-top:.25rem}.col-12>h2{margin-bottom:.25rem}
.criteria-catalog{border:1px solid var(--bs-border-color);border-left:4px solid var(--bs-primary);border-radius:.5rem;padding:1rem 1.25rem;background:var(--bs-tertiary-bg)}.criteria-catalog ul{padding-left:1.25rem}.criteria-catalog li+li{margin-top:.45rem}
@media(max-width:575.98px){.protection-choice{grid-template-rows:auto 360px 40px minmax(170px,auto) auto;min-height:700px}.connector-grid{height:360px;min-height:360px;gap:.6rem}.connector-example{grid-template-rows:72px auto;padding:.45rem}.connector-example img{height:68px}.connector-empty{min-height:200px}}
</style>
<script>
(() => {
  const date = document.querySelector('[name="test_date"]');
  const next = document.getElementById('next_due_date');
  const type = document.querySelector('[name="inspection_type"]');
  const protection = [...document.querySelectorAll('[name="protection_class"]')];
  const form = document.querySelector('form.row.g-3');
  if (form) {
    const protectionBlock = form.querySelector('.col-md-9');
    const protectionLabel = protectionBlock?.querySelector(':scope > .form-label');
    if (protectionBlock && protectionLabel) { protectionLabel.outerHTML = '<h2 class="h5 mb-0">Schutzklasse bestimmen</h2>'; protectionBlock.classList.remove('col-md-9'); protectionBlock.classList.add('col-12'); }
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
    Kabel: {title:'Kriterienkatalog · Anschluss- und Verlängerungsleitung', items:['Schutzleiterwiderstand RSL: ≤ 0,3 Ω bis 5 m Leitungslänge; danach +0,1 Ω je weitere 7,5 m, maximal 1 Ω.','Isolationswiderstand RISO: mindestens 1 MΩ.','Schutzleiterstrom IPE (Differenzstrom): maximal 3,5 mA; Berührungsstrom IB: kleiner als 0,5 mA.','Leitungslänge und Leitungsquerschnitt dokumentieren; Stecker, Kupplung, Zugentlastung, Isolation und Aderanschlüsse prüfen.']}
  };
  const protectionBlock = form?.querySelector('[data-sk="SK I"]')?.closest('.col-6')?.parentElement?.parentElement;
  const criteriaPanel = protectionBlock ? document.createElement('section') : null;
  if (criteriaPanel && !document.getElementById('criteria-catalog')) {
    criteriaPanel.id = 'criteria-catalog';
    criteriaPanel.className = 'col-12 criteria-catalog';
    protectionBlock.after(criteriaPanel);
    const renderCriteria = () => {
      const selected = protection.find(input => input.checked)?.value;
      const criteria = criteriaByClass[selected];
      criteriaPanel.innerHTML = criteria ? `<h2 class="h5 mb-2">${criteria.title}</h2><ul class="mb-0">${criteria.items.map(item => `<li>${item}</li>`).join('')}</ul>` : '<h2 class="h5 mb-2">Kriterienkatalog</h2><p class="mb-0 text-body-secondary">Bitte zuerst eine Schutzklasse auswählen.</p>';
    };
    protection.forEach(input => input.addEventListener('change', renderCriteria));
    renderCriteria();
  }
  if (!date || !next) return;
  const update = (days, confirmed = false) => { if (!date.value) return; const d = new Date(date.value + 'T12:00:00'); d.setDate(d.getDate() + Number(days)); next.value = d.toISOString().slice(0, 10); next.dataset.confirmed = confirmed ? '1' : '0'; next.classList.toggle('bg-warning-subtle', !confirmed); };
  document.querySelectorAll('.interval-btn').forEach(button => button.addEventListener('click', () => update(button.dataset.days, true)));
  document.getElementById('confirm_next_due')?.addEventListener('click', () => { next.classList.remove('bg-warning-subtle'); next.dataset.confirmed = '1'; });
  date.addEventListener('change', () => update(365));
  const syncType = () => { const selected = protection.find(input => input.checked); if (selected && type) type.value = ({I:'Schutzklasse I', II:'Schutzklasse II', III:'Schutzklasse III', Kabel:'Kabelprüfung'})[selected.value] || ''; };
  const syncChecklist = () => { const selected = protection.find(input => input.checked); const label = document.querySelectorAll('.checklist-card > div:first-child')[3]; if (label) label.textContent = selected?.value === 'I' ? 'Metallische Schutzkontakte auf 6 und 12 Uhr vorhanden und unbeschädigt' : (selected?.value === 'II' ? 'Keine metallischen Schutzkontakte auf 6 und 12 Uhr; Schutzisolierung erkennbar' : 'Stecker, Kontakte und Anschluss unbeschädigt'); };
  protection.forEach(input => input.addEventListener('change', () => { syncType(); syncChecklist(); })); syncType(); syncChecklist();
  if (next.value) next.classList.add('bg-warning-subtle');
  const schematicByClass = {
    'SK I': ['cee-7-4.svg', 'cee-7-7.svg'],
    'SK II': ['cee-7-16.svg', 'cee-7-17.svg'],
    'SK III': ['dc-buchse.svg']
  };
  document.querySelectorAll('.protection-choice').forEach(card => {
    const images = schematicByClass[card.dataset.sk];
    if (!images) return;
    card.querySelectorAll('.protection-plug-image').forEach((image, index) => {
      const file = images[index] || images[0];
      image.src = `<?= htmlspecialchars(url_for('public/img/stecker/'), ENT_QUOTES) ?>${file}`;
    });
  });
  const gallery = document.querySelector('.plug-variant-gallery');
  if (gallery) {
    gallery.querySelectorAll('figure').forEach(figure => {
      const caption = figure.textContent || '';
      const target = document.querySelector(caption.includes('SK II') ? '[data-sk="SK II"]' : '[data-sk="SK I"]');
      const image = figure.querySelector('img');
      if (!target || !image) return;
      const actual = caption.includes('C5') ? 'iec-c5.svg' : (caption.includes('C13') ? 'iec-c13.svg' : (caption.includes('C19') ? 'iec-c19.svg' : (caption.includes('C7') ? 'iec-c7-real.svg' : '')));
      if (actual) image.src = `<?= htmlspecialchars(url_for('public/img/stecker/'), ENT_QUOTES) ?>${actual}`;
      let mini = target.querySelector('.plug-variant-mini');
      if (!mini) { mini = document.createElement('div'); mini.className = 'plug-variant-mini'; target.appendChild(mini); }
      const item = figure.cloneNode(true);
      item.className = 'plug-variant-mini-item';
      mini.appendChild(item);
    });
    gallery.remove();
  }
  const connectorLabels = {
    'SK I': ['CEE 7/4 · Schuko-Stecker', 'CEE 7/7 · Kombistecker E/F'],
    'SK II': ['CEE 7/16 · Eurostecker', 'CEE 7/17 · Konturenstecker'],
    'SK III': ['DC-Hohlbuchse · USB · Batterie/Akku']
  };
  document.querySelectorAll('.protection-choice').forEach(card => {
    const header = card.querySelector(':scope > span');
    const images = [...card.querySelectorAll(':scope > .protection-plug-image')];
    const mini = card.querySelector(':scope > .plug-variant-mini');
    const grid = document.createElement('div');
    grid.className = 'connector-grid';
    images.forEach((image, index) => {
      const figure = document.createElement('figure');
      figure.className = 'connector-example';
      figure.appendChild(image);
      const caption = document.createElement('figcaption');
      caption.textContent = (connectorLabels[card.dataset.sk] || [])[index] || image.alt.replace(/^Beispiel:\s*/i, '');
      figure.appendChild(caption);
      grid.appendChild(figure);
    });
    if (card.dataset.sk === 'SK III') {
      [['usb.svg', 'USB-Anschluss'], ['batterie.svg', 'Batterie / Akku']].forEach(([file, label]) => {
        const figure = document.createElement('figure');
        figure.className = 'connector-example';
        const image = document.createElement('img');
        image.src = `<?= htmlspecialchars(url_for('public/img/stecker/'), ENT_QUOTES) ?>${file}`;
        image.alt = label;
        figure.appendChild(image);
        const caption = document.createElement('figcaption');
        caption.textContent = label;
        figure.appendChild(caption);
        grid.appendChild(figure);
      });
    }
    if (card.dataset.sk === 'SK Kabel') {
      [['kabel-schuko.jpg', 'CEE 7/7 → Schuko-Kupplung', 'Schuko-Verlängerung'], ['kabel-c13.svg', 'CEE 7/7 → IEC C13', 'Kaltgerätekabel'], ['c5_power_cable.svg', 'CEE 7/7 oder CEE 7/16 → IEC C5', 'Mickey-Mouse- / Kleeblattkabel · mit geeignetem Adapter separat prüfbar']].forEach(([file, connector, label]) => {
        const figure = document.createElement('figure');
        figure.className = 'connector-example';
        const image = document.createElement('img');
        image.src = `<?= htmlspecialchars(url_for('public/img/stecker/'), ENT_QUOTES) ?>${file}`;
        image.alt = connector;
        figure.appendChild(image);
        const caption = document.createElement('figcaption');
        caption.innerHTML = `${connector}<br><span>${label}</span>`;
        figure.appendChild(caption);
        grid.appendChild(figure);
      });
    }
    if (mini) {
      mini.querySelectorAll('figure').forEach(figure => { figure.className = 'connector-example'; grid.appendChild(figure); });
      mini.remove();
    }
    if (!grid.children.length) {
      const empty = document.createElement('div');
      empty.className = 'connector-empty';
      empty.textContent = 'Anschluss- und Verlängerungskabel';
      grid.appendChild(empty);
    }
    const icon = card.querySelector(':scope > i');
    const title = card.querySelector(':scope > strong');
    if (!header || !icon || !title) return;
    header.after(grid);
    const titleWrap = document.createElement('div');
    titleWrap.className = 'connector-title';
    titleWrap.append(icon, title);
    grid.after(titleWrap);
    const descriptions = [...card.querySelectorAll(':scope > span.d-block.small')];
    if (descriptions.length) {
      const description = document.createElement('div');
      description.className = 'connector-description';
      descriptions.forEach(item => description.appendChild(item));
      if (card.dataset.sk === 'SK Kabel') {
        const note = document.createElement('div');
        note.className = 'small text-body-secondary border rounded p-2 mt-2';
        note.textContent = 'Hinweis: Zweipolige Anschlussleitungen ohne Schutzleiter, z. B. IEC C7, werden nicht separat als SK-Kabel geprüft, sondern zusammen mit dem zugehörigen Schutzklasse-II-Gerät.';
        description.appendChild(note);
      }
      titleWrap.after(description);
    }
    const badge = card.querySelector(':scope > span.badge.rounded-pill');
    if (badge) badge.classList.add('connector-badge');
  });
})();
</script>
