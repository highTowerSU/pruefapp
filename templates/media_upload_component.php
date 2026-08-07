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
<form method="post" action="<?= htmlspecialchars($action, ENT_QUOTES) ?>"<?= $hxTarget !== '' ? ' hx-post="' . htmlspecialchars($action, ENT_QUOTES) . '" hx-target="' . htmlspecialchars($hxTarget, ENT_QUOTES) . '" hx-swap="outerHTML" hx-encoding="multipart/form-data"' : ' enctype="multipart/form-data"' ?> class="row g-2 align-items-end" data-media-upload-component>
  <?php foreach ($hiddenFields as $name => $value): ?><input type="hidden" name="<?= htmlspecialchars((string) $name, ENT_QUOTES) ?>" value="<?= htmlspecialchars((string) $value, ENT_QUOTES) ?>"><?php endforeach; ?>
  <div class="col-12 col-lg-4">
    <label class="form-label" for="<?= htmlspecialchars($componentId, ENT_QUOTES) ?>-file"><i class="fa-solid fa-camera me-1" aria-hidden="true"></i>Foto hinzufügen</label>
    <div class="input-group"><input class="form-control" id="<?= htmlspecialchars($componentId, ENT_QUOTES) ?>-file" name="photo" type="file" accept="image/jpeg,image/png,image/webp" capture="environment" required data-media-upload-input><button class="btn btn-secondary d-none" type="button" data-companion-upload-photo title="Foto aus der Companion-App auswählen"><i class="fa-solid fa-mobile-screen-button" aria-hidden="true"></i><span class="visually-hidden">Companion-Foto auswählen</span></button></div>
    <div class="form-control mt-2 small text-body-secondary" contenteditable="true" role="textbox" aria-label="Foto hier einfügen: Strg+V oder Rechtsklick, Einfügen" data-media-paste><i class="fa-solid fa-paste me-1" aria-hidden="true"></i>Foto hier einfügen – Strg+V oder Rechtsklick → Einfügen</div>
    <div class="mt-2 d-none" data-media-preview aria-live="polite"></div>
  </div>
  <div class="col-12 col-sm-5 col-lg-3"><label class="form-label" for="<?= htmlspecialchars($componentId, ENT_QUOTES) ?>-type">Fotoart</label><select class="form-select" id="<?= htmlspecialchars($componentId, ENT_QUOTES) ?>-type" name="media_type"><?php foreach ($photoTypes as $value => $label): ?><option value="<?= htmlspecialchars($value, ENT_QUOTES) ?>"><?= htmlspecialchars($label) ?></option><?php endforeach; ?></select><?php if ($allowTypePlate): ?><div class="form-check mt-1"><input class="form-check-input" type="checkbox" name="analyse_typeplate" value="1" id="<?= htmlspecialchars($componentId, ENT_QUOTES) ?>-analyse"><label class="form-check-label small" for="<?= htmlspecialchars($componentId, ENT_QUOTES) ?>-analyse"><i class="fa-solid fa-wand-magic-sparkles me-1" aria-hidden="true"></i>Typenschild erkennen</label></div><?php endif; ?></div>
  <div class="col-12 col-sm-7 col-lg-3"><label class="form-label" for="<?= htmlspecialchars($componentId, ENT_QUOTES) ?>-caption">Bemerkung</label><input class="form-control" id="<?= htmlspecialchars($componentId, ENT_QUOTES) ?>-caption" name="caption" maxlength="1000" placeholder="optional"></div>
  <div class="col-12 col-lg-2"><button class="btn btn-primary w-100" type="submit"><i class="fa-solid fa-upload me-1" aria-hidden="true"></i>Foto hochladen</button></div>
</form>
