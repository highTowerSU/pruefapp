<?php
/** Reusable local/Companion photo upload controls for devices, rooms and inspections. */
$componentId = (string) ($componentId ?? 'media-upload');
$action = (string) ($action ?? '');
$hxTarget = (string) ($hxTarget ?? '');
$hiddenFields = (array) ($hiddenFields ?? []);
$allowTypePlate = !empty($allowTypePlate);
$inline = !empty($inline);
$stageDraft = !empty($stageDraft);
$fileInputName = (string) ($fileInputName ?? 'photo');
$mediaTypeName = (string) ($mediaTypeName ?? 'media_type');
$captionName = (string) ($captionName ?? 'caption');
$submitText = (string) ($submitText ?? 'Foto hochladen');
$showMetadata = isset($showMetadata) ? (bool) $showMetadata : true;
$contextHint = (string) ($contextHint ?? 'Bild und Bemerkung werden diesem Eintrag zugeordnet.');
$photoTypes = $allowTypePlate
    ? ['condition' => 'Gerät', 'type_plate' => $stageDraft ? 'Typenschild · KI-Vorschlag' : 'Typenschild', 'defect' => 'Mangel', 'disposal' => 'Aussonderung', 'other' => 'Sonstiges']
    : ['condition' => 'Gerät', 'defect' => 'Mangel', 'disposal' => 'Aussonderung', 'other' => 'Sonstiges'];
?>
<?php if ($inline): ?><div class="media-upload-component border rounded-3 p-3" data-media-upload-component><?php else: ?><form method="post" action="<?= htmlspecialchars($action, ENT_QUOTES) ?>"<?= $hxTarget !== '' ? ' hx-post="' . htmlspecialchars($action, ENT_QUOTES) . '" hx-target="' . htmlspecialchars($hxTarget, ENT_QUOTES) . '" hx-swap="outerHTML" hx-encoding="multipart/form-data"' : ' enctype="multipart/form-data"' ?> class="media-upload-component border rounded-3 p-3" data-media-upload-component><?php endif; ?>
  <?php foreach ($hiddenFields as $name => $value): ?><input type="hidden" name="<?= htmlspecialchars((string) $name, ENT_QUOTES) ?>" value="<?= htmlspecialchars((string) $value, ENT_QUOTES) ?>"><?php endforeach; ?>
  <div class="row g-3 align-items-end">
    <div class="col-12 col-xxl-5">
      <label class="form-label fw-semibold" for="<?= htmlspecialchars($componentId, ENT_QUOTES) ?>-file"><i class="fa-solid fa-camera me-1" aria-hidden="true"></i>Fotoquelle</label>
      <div class="input-group"><input class="form-control" id="<?= htmlspecialchars($componentId, ENT_QUOTES) ?>-file" name="<?= htmlspecialchars($fileInputName, ENT_QUOTES) ?>" type="file" accept="image/jpeg,image/png,image/webp" capture="environment"<?= $inline ? '' : ' required' ?> data-media-upload-input<?= $stageDraft ? ' data-stage-device-photo' : '' ?>><button class="btn btn-secondary" type="button" data-companion-upload-photo data-companion-connect-url="<?= htmlspecialchars(url_for('profil') . '#companion-sessions', ENT_QUOTES) ?>" title="Foto aus der Companion-App auswählen oder verbinden"><i class="fa-solid fa-paperclip" aria-hidden="true"></i><span class="visually-hidden">Companion-Foto auswählen oder verbinden</span></button></div>
      <div class="media-upload-paste mt-2" contenteditable="true" role="textbox" aria-label="Foto hier einfügen: Strg+V oder Rechtsklick, Einfügen" data-media-paste><i class="fa-solid fa-paste me-1" aria-hidden="true"></i>Foto einfügen <span>– Strg+V oder Rechtsklick → Einfügen</span></div>
      <div class="mt-2 d-none" data-media-preview aria-live="polite"></div>
    </div>
    <div class="col-12 <?= $showMetadata ? 'col-xxl-7' : 'col-xxl-7 d-flex align-items-end' ?>">
      <?php if (!$showMetadata): ?><div class="w-100 d-flex flex-wrap align-items-center justify-content-between gap-2"><span class="small text-body-secondary">Das Foto wird als neue Kundeninfo angelegt. Den Titel kannst du danach direkt ändern.</span><button class="btn btn-primary media-upload-submit" type="<?= $stageDraft ? 'button' : 'submit' ?>"<?= $stageDraft ? ' data-stage-device-photo-upload' : '' ?>><i class="fa-solid fa-upload me-1" aria-hidden="true"></i><?= htmlspecialchars($submitText) ?></button></div><?php else: ?>
      <div class="row g-3 align-items-end">
        <div class="col-12 col-xxl-4"><label class="form-label fw-semibold" for="<?= htmlspecialchars($componentId, ENT_QUOTES) ?>-type">Fotoart</label><select class="form-select" id="<?= htmlspecialchars($componentId, ENT_QUOTES) ?>-type" name="<?= htmlspecialchars($mediaTypeName, ENT_QUOTES) ?>"><?php foreach ($photoTypes as $value => $label): ?><option value="<?= htmlspecialchars($value, ENT_QUOTES) ?>"><?= htmlspecialchars($label) ?></option><?php endforeach; ?></select></div>
        <div class="col-12 col-xxl-8"><label class="form-label fw-semibold" for="<?= htmlspecialchars($componentId, ENT_QUOTES) ?>-caption">Bemerkung <span class="fw-normal text-body-secondary">optional</span></label><input class="form-control" id="<?= htmlspecialchars($componentId, ENT_QUOTES) ?>-caption" name="<?= htmlspecialchars($captionName, ENT_QUOTES) ?>" maxlength="1000" placeholder="z. B. Schaden an der Rückseite"></div>
        <div class="col-12 d-flex flex-wrap align-items-center justify-content-between gap-2">
          <?php if ($allowTypePlate && !$stageDraft): ?><div class="form-check"><input class="form-check-input" type="checkbox" name="analyse_typeplate" value="1" id="<?= htmlspecialchars($componentId, ENT_QUOTES) ?>-analyse"><label class="form-check-label" for="<?= htmlspecialchars($componentId, ENT_QUOTES) ?>-analyse"><i class="fa-solid fa-wand-magic-sparkles me-1" aria-hidden="true"></i>Typenschild nach Upload erkennen</label></div><?php elseif ($stageDraft): ?><span class="small text-body-secondary">Das Typenschild erzeugt nach dem Vorab-Upload einen übernehmbaren KI-Vorschlag.</span><?php else: ?><span class="small text-body-secondary"><?= htmlspecialchars($contextHint) ?></span><?php endif; ?>
          <button class="btn btn-primary media-upload-submit" type="<?= $stageDraft ? 'button' : 'submit' ?>"<?= $stageDraft ? ' data-stage-device-photo-upload' : '' ?>><i class="fa-solid fa-upload me-1" aria-hidden="true"></i><?= htmlspecialchars($submitText) ?></button>
        </div>
      </div>
      <?php endif; ?>
    </div>
  </div>
<?php if ($inline): ?></div><?php else: ?></form><?php endif; ?>
<style>
  .media-upload-component{background:var(--bs-tertiary-bg)}
  .media-upload-component .form-control,.media-upload-component .form-select,.media-upload-component .btn{min-height:2.75rem}
  .media-upload-paste{min-height:2.75rem;padding:.55rem .75rem;border:1px dashed var(--bs-border-color);border-radius:var(--bs-border-radius);color:var(--bs-secondary-color);cursor:text}
  .media-upload-paste:focus{outline:0;border-color:var(--bs-primary);box-shadow:0 0 0 .2rem rgba(var(--bs-primary-rgb),.2)}
  .media-upload-submit{min-width:11.5rem;display:inline-flex;align-items:center;justify-content:center}
  .media-upload-component .input-group{min-width:0}
  .media-upload-component .form-text,.media-upload-component .small{overflow-wrap:anywhere}
  @media(max-width:575.98px){.media-upload-submit{width:100%}.media-upload-paste span{display:block;margin-top:.15rem}}
</style>
