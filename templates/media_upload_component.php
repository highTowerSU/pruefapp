<?php
/** Reusable local/Companion photo upload controls for devices, rooms and inspections. */
$componentId = (string) ($componentId ?? 'media-upload');
$action = (string) ($action ?? '');
$hxTarget = (string) ($hxTarget ?? '');
$hiddenFields = (array) ($hiddenFields ?? []);
$allowTypePlate = !empty($allowTypePlate);
$photoTypes = $allowTypePlate
    ? ['condition' => 'Gerät', 'type_plate' => 'Typenschild', 'defect' => 'Mangel', 'disposal' => 'Aussonderung', 'other' => 'Sonstiges']
    : ['condition' => 'Gerät', 'defect' => 'Mangel', 'disposal' => 'Aussonderung', 'other' => 'Sonstiges'];
?>
<form method="post" action="<?= htmlspecialchars($action, ENT_QUOTES) ?>"<?= $hxTarget !== '' ? ' hx-post="' . htmlspecialchars($action, ENT_QUOTES) . '" hx-target="' . htmlspecialchars($hxTarget, ENT_QUOTES) . '" hx-swap="outerHTML" hx-encoding="multipart/form-data"' : ' enctype="multipart/form-data"' ?> class="media-upload-component border rounded-3 p-3" data-media-upload-component>
  <?php foreach ($hiddenFields as $name => $value): ?><input type="hidden" name="<?= htmlspecialchars((string) $name, ENT_QUOTES) ?>" value="<?= htmlspecialchars((string) $value, ENT_QUOTES) ?>"><?php endforeach; ?>
  <div class="row g-3 align-items-end">
    <div class="col-12 col-xl-5">
      <label class="form-label fw-semibold" for="<?= htmlspecialchars($componentId, ENT_QUOTES) ?>-file"><i class="fa-solid fa-camera me-1" aria-hidden="true"></i>Fotoquelle</label>
      <div class="input-group"><input class="form-control" id="<?= htmlspecialchars($componentId, ENT_QUOTES) ?>-file" name="photo" type="file" accept="image/jpeg,image/png,image/webp" capture="environment" required data-media-upload-input><button class="btn btn-secondary d-none" type="button" data-companion-upload-photo title="Foto aus der Companion-App auswählen"><i class="fa-solid fa-mobile-screen-button" aria-hidden="true"></i><span class="visually-hidden">Companion-Foto auswählen</span></button></div>
      <div class="media-upload-paste mt-2" contenteditable="true" role="textbox" aria-label="Foto hier einfügen: Strg+V oder Rechtsklick, Einfügen" data-media-paste><i class="fa-solid fa-paste me-1" aria-hidden="true"></i>Foto einfügen <span>– Strg+V oder Rechtsklick → Einfügen</span></div>
      <div class="mt-2 d-none" data-media-preview aria-live="polite"></div>
    </div>
    <div class="col-12 col-xl-7">
      <div class="row g-3 align-items-end">
        <div class="col-12 col-md-4"><label class="form-label fw-semibold" for="<?= htmlspecialchars($componentId, ENT_QUOTES) ?>-type">Fotoart</label><select class="form-select" id="<?= htmlspecialchars($componentId, ENT_QUOTES) ?>-type" name="media_type"><?php foreach ($photoTypes as $value => $label): ?><option value="<?= htmlspecialchars($value, ENT_QUOTES) ?>"><?= htmlspecialchars($label) ?></option><?php endforeach; ?></select></div>
        <div class="col-12 col-md-8"><label class="form-label fw-semibold" for="<?= htmlspecialchars($componentId, ENT_QUOTES) ?>-caption">Bemerkung <span class="fw-normal text-body-secondary">optional</span></label><input class="form-control" id="<?= htmlspecialchars($componentId, ENT_QUOTES) ?>-caption" name="caption" maxlength="1000" placeholder="z. B. Schaden an der Rückseite"></div>
        <div class="col-12 d-flex flex-wrap align-items-center justify-content-between gap-2">
          <?php if ($allowTypePlate): ?><div class="form-check"><input class="form-check-input" type="checkbox" name="analyse_typeplate" value="1" id="<?= htmlspecialchars($componentId, ENT_QUOTES) ?>-analyse"><label class="form-check-label" for="<?= htmlspecialchars($componentId, ENT_QUOTES) ?>-analyse"><i class="fa-solid fa-wand-magic-sparkles me-1" aria-hidden="true"></i>Typenschild nach Upload erkennen</label></div><?php else: ?><span class="small text-body-secondary">Bild und Bemerkung werden der aktuellen Prüfung zugeordnet.</span><?php endif; ?>
          <button class="btn btn-primary media-upload-submit" type="submit"><i class="fa-solid fa-upload me-1" aria-hidden="true"></i>Foto hochladen</button>
        </div>
      </div>
    </div>
  </div>
</form>
<style>
  .media-upload-component{background:var(--bs-tertiary-bg)}
  .media-upload-component .form-control,.media-upload-component .form-select,.media-upload-component .btn{min-height:2.75rem}
  .media-upload-paste{min-height:2.75rem;padding:.55rem .75rem;border:1px dashed var(--bs-border-color);border-radius:var(--bs-border-radius);color:var(--bs-secondary-color);cursor:text}
  .media-upload-paste:focus{outline:0;border-color:var(--bs-primary);box-shadow:0 0 0 .2rem rgba(var(--bs-primary-rgb),.2)}
  .media-upload-submit{min-width:11.5rem}
  @media(max-width:575.98px){.media-upload-submit{width:100%}.media-upload-paste span{display:block;margin-top:.15rem}}
</style>
