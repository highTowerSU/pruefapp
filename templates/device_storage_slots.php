<?php
$storageSlotRows ??= [['number' => '', 'comment' => '']];
$formKey ??= 0;
$slotError ??= '';
$slotFormUrl = htmlspecialchars(url_for('geraete/speicherplaetze/form'), ENT_QUOTES);
?>
<div class="col-12" data-storage-slots-panel id="device-storage-slots-<?= (int) $formKey ?>">
  <input type="hidden" name="storage_form_key" value="<?= (int) $formKey ?>">
  <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
    <label class="form-label mb-0"><i class="fa-solid fa-hard-drive icon-slot me-1" aria-hidden="true"></i>Prüf-Speicherplätze <span class="text-body-secondary fw-normal">optional</span></label>
    <span class="small text-body-secondary">Bis zu <?= DeviceStorageSlotService::MAX_SLOTS ?> Plätze, etwa bei Geräten mit zwei Netzteilen.</span>
  </div>
  <?php if ($slotError !== ''): ?><div class="alert alert-warning py-2 mb-2" role="alert"><?= htmlspecialchars($slotError) ?></div><?php endif; ?>
  <?php foreach ($storageSlotRows as $index => $row): ?>
    <div class="row g-2 align-items-end mb-2">
      <div class="col-12 col-md-3"><label class="form-label" for="storage-slot-number-<?= (int) $formKey ?>-<?= $index ?>">Speicherplatz <?= $index + 1 ?></label><input class="form-control" id="storage-slot-number-<?= (int) $formKey ?>-<?= $index ?>" name="storage_slot_numbers[]" value="<?= htmlspecialchars((string) ($row['number'] ?? ''), ENT_QUOTES) ?>" maxlength="40" placeholder="z. B. 120"></div>
      <div class="col-12 col-md-8"><label class="form-label" for="storage-slot-comment-<?= (int) $formKey ?>-<?= $index ?>">Kommentar zu Platz <?= $index + 1 ?></label><input class="form-control" id="storage-slot-comment-<?= (int) $formKey ?>-<?= $index ?>" name="storage_slot_comments[]" value="<?= htmlspecialchars((string) ($row['comment'] ?? ''), ENT_QUOTES) ?>" maxlength="240" placeholder="z. B. Netzteil links / PSU 1"></div>
      <div class="col-12 col-md-1 d-grid"><button class="btn btn-outline-danger" type="button" title="Speicherplatz <?= $index + 1 ?> entfernen" aria-label="Speicherplatz <?= $index + 1 ?> entfernen" hx-post="<?= $slotFormUrl ?>" hx-target="closest [data-storage-slots-panel]" hx-include="closest [data-storage-slots-panel]" hx-params="storage_form_key,storage_slot_numbers[],storage_slot_comments[],slot_action,slot_index" hx-vals='{"slot_action":"remove","slot_index":<?= $index ?>}' hx-swap="outerHTML" hx-disabled-elt="this"<?= count($storageSlotRows) === 1 ? ' disabled' : '' ?>><i class="fa-solid fa-trash" aria-hidden="true"></i></button></div>
    </div>
  <?php endforeach; ?>
  <button class="btn btn-outline-primary btn-sm" type="button" hx-post="<?= $slotFormUrl ?>" hx-target="closest [data-storage-slots-panel]" hx-include="closest [data-storage-slots-panel]" hx-params="storage_form_key,storage_slot_numbers[],storage_slot_comments[],slot_action,slot_index" hx-vals='{"slot_action":"add"}' hx-swap="outerHTML" hx-disabled-elt="this"<?= count($storageSlotRows) >= DeviceStorageSlotService::MAX_SLOTS ? ' disabled' : '' ?>><i class="fa-solid fa-plus me-1" aria-hidden="true"></i>Weiterer Speicherplatz</button>
  <div class="form-text">Pro Netzteil die Nummer und optional eine eindeutige Bezeichnung eintragen. Gespeichert wird zusammen mit dem Gerät.</div>
</div>
