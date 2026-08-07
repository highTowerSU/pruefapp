<?php
/** Compact visual editor for compatible JSON metadata objects. */
$metadataName = (string) ($metadataName ?? 'metadata_json');
$metadataId = (string) ($metadataId ?? 'metadata-json');
$metadataValue = trim((string) ($metadataValue ?? ''));
if ($metadataValue === '{}') $metadataValue = '';
?>
<div class="metadata-visual-editor" data-metadata-editor>
  <div class="metadata-visual-rows d-grid gap-2"></div>
  <button class="btn btn-sm btn-secondary mt-2" type="button" data-metadata-add><i class="fa-solid fa-plus me-1" aria-hidden="true"></i>Attribut hinzufügen</button>
  <details class="mt-2">
    <summary class="small text-body-secondary">JSON-Ansicht für Fortgeschrittene</summary>
    <textarea class="form-control font-monospace mt-2" id="<?= htmlspecialchars($metadataId, ENT_QUOTES) ?>" name="<?= htmlspecialchars($metadataName, ENT_QUOTES) ?>" data-metadata-json placeholder='z. B. {"kostenstelle":"1000"}'><?= htmlspecialchars($metadataValue) ?></textarea>
  </details>
</div>
<div class="form-text">Text, Zahl, Datum, Ja/Nein oder MAC-Adresse; die Daten werden weiterhin als kompatibles JSON gespeichert.</div>
<script>
(() => {
  const init = (editor) => {
    if (editor.dataset.metadataBound) return;
    editor.dataset.metadataBound = '1';
    const rows = editor.querySelector('.metadata-visual-rows');
    const json = editor.querySelector('[data-metadata-json]');
    const sync = () => {
      const data = {};
      rows.querySelectorAll('[data-metadata-row]').forEach(row => {
        const key = row.querySelector('[data-metadata-key]').value.trim();
        const type = row.querySelector('[data-metadata-type]').value;
        let value = row.querySelector('[data-metadata-value]').value.trim();
        if (!key) return;
        if (type === 'number') value = value === '' ? null : Number(value);
        if (type === 'boolean') value = value === 'true';
        data[key] = value;
      });
      json.value = Object.keys(data).length ? JSON.stringify(data) : '';
    };
    const add = (key = '', value = '', type = 'text') => {
      const row = document.createElement('div'); row.className = 'row g-2 align-items-center'; row.dataset.metadataRow = '1';
      row.innerHTML = '<div class="col-12 col-lg-4"><input class="form-control form-control-sm" data-metadata-key placeholder="Name" aria-label="Attributname"></div><div class="col-5 col-lg-3"><select class="form-select form-select-sm" data-metadata-type aria-label="Datentyp"><option value="text">Text</option><option value="number">Zahl</option><option value="date">Datum</option><option value="boolean">Ja/Nein</option><option value="mac">MAC-Adresse</option></select></div><div class="col-5 col-lg-4"><input class="form-control form-control-sm" data-metadata-value placeholder="Wert" aria-label="Attributwert"></div><div class="col-2 col-lg-1"><button class="btn btn-sm btn-danger w-100" type="button" data-metadata-remove aria-label="Attribut entfernen"><i class="fa-solid fa-trash" aria-hidden="true"></i></button></div>';
      row.querySelector('[data-metadata-key]').value = key;
      row.querySelector('[data-metadata-type]').value = type;
      const input = row.querySelector('[data-metadata-value]'); input.value = type === 'boolean' ? String(Boolean(value)) : String(value ?? '');
      input.type = type === 'date' ? 'date' : (type === 'number' ? 'number' : 'text');
      rows.append(row);
    };
    let source = {};
    try { source = JSON.parse(json.value || '{}'); } catch (_) { source = {}; }
    const typeOf = value => typeof value === 'boolean' ? 'boolean' : typeof value === 'number' ? 'number' : /^\d{4}-\d{2}-\d{2}$/.test(String(value)) ? 'date' : 'text';
    Object.entries(source).forEach(([key, value]) => add(key, value, typeOf(value)));
    if (!rows.children.length) add();
    editor.querySelector('[data-metadata-add]').addEventListener('click', () => { add(); sync(); });
    rows.addEventListener('click', event => { const remove = event.target.closest('[data-metadata-remove]'); if (!remove) return; remove.closest('[data-metadata-row]').remove(); if (!rows.children.length) add(); sync(); });
    rows.addEventListener('input', sync);
    rows.addEventListener('change', event => { const row = event.target.closest('[data-metadata-row]'); if (event.target.matches('[data-metadata-type]') && row) { const input = row.querySelector('[data-metadata-value]'); input.type = event.target.value === 'date' ? 'date' : (event.target.value === 'number' ? 'number' : 'text'); } sync(); });
  };
  document.querySelectorAll('[data-metadata-editor]').forEach(init);
})();
</script>
