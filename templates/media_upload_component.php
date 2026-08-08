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
  <div class="media-upload-layout<?= $showMetadata ? '' : ' media-upload-layout-simple' ?>">
    <div class="media-upload-source">
      <label class="form-label fw-semibold" for="<?= htmlspecialchars($componentId, ENT_QUOTES) ?>-file"><i class="fa-solid fa-camera me-1" aria-hidden="true"></i>Fotoquelle</label>
      <div class="input-group"><input class="form-control" id="<?= htmlspecialchars($componentId, ENT_QUOTES) ?>-file" name="<?= htmlspecialchars($fileInputName, ENT_QUOTES) ?>" type="file" accept="image/jpeg,image/png,image/webp" capture="environment"<?= $inline ? '' : ' required' ?> data-media-upload-input<?= $stageDraft ? ' data-stage-device-photo' : '' ?>><button class="btn btn-secondary" type="button" data-companion-upload-photo data-companion-connect-url="<?= htmlspecialchars(url_for('profil') . '#companion-sessions', ENT_QUOTES) ?>" title="Foto aus der Companion-App auswählen oder verbinden"><i class="fa-solid fa-paperclip" aria-hidden="true"></i><span class="visually-hidden">Companion-Foto auswählen oder verbinden</span></button></div>
      <div class="media-upload-paste mt-2" contenteditable="true" role="textbox" aria-label="Foto hier einfügen: Strg+V oder Rechtsklick, Einfügen" data-media-paste><i class="fa-solid fa-paste me-1" aria-hidden="true"></i>Foto einfügen <span>– Strg+V oder Rechtsklick → Einfügen</span></div>
      <div class="mt-2 d-none" data-media-preview aria-live="polite"></div>
    </div>
    <?php if (!$showMetadata): ?><div class="media-upload-hint small text-body-secondary">Das Foto wird als neue Kundeninfo angelegt. Den Titel kannst du danach direkt ändern.</div><div class="media-upload-action"><button class="btn btn-primary media-upload-submit" type="<?= $stageDraft ? 'button' : 'submit' ?>"<?= $stageDraft ? ' data-stage-device-photo-upload' : '' ?>><i class="fa-solid fa-upload me-1" aria-hidden="true"></i><?= htmlspecialchars($submitText) ?></button></div><?php else: ?>
      <div class="media-upload-type"><label class="form-label fw-semibold" for="<?= htmlspecialchars($componentId, ENT_QUOTES) ?>-type">Fotoart</label><select class="form-select" id="<?= htmlspecialchars($componentId, ENT_QUOTES) ?>-type" name="<?= htmlspecialchars($mediaTypeName, ENT_QUOTES) ?>"><?php foreach ($photoTypes as $value => $label): ?><option value="<?= htmlspecialchars($value, ENT_QUOTES) ?>"><?= htmlspecialchars($label) ?></option><?php endforeach; ?></select></div>
      <div class="media-upload-caption"><label class="form-label fw-semibold" for="<?= htmlspecialchars($componentId, ENT_QUOTES) ?>-caption">Bemerkung <span class="fw-normal text-body-secondary">optional</span></label><input class="form-control" id="<?= htmlspecialchars($componentId, ENT_QUOTES) ?>-caption" name="<?= htmlspecialchars($captionName, ENT_QUOTES) ?>" maxlength="1000" placeholder="z. B. Schaden an der Rückseite"></div>
      <div class="media-upload-hint small text-body-secondary"><?php if ($allowTypePlate && !$stageDraft): ?><div class="form-check"><input class="form-check-input" type="checkbox" name="analyse_typeplate" value="1" id="<?= htmlspecialchars($componentId, ENT_QUOTES) ?>-analyse"><label class="form-check-label" for="<?= htmlspecialchars($componentId, ENT_QUOTES) ?>-analyse"><i class="fa-solid fa-wand-magic-sparkles me-1" aria-hidden="true"></i>Typenschild nach Upload erkennen</label></div><?php elseif ($stageDraft): ?><span data-draft-upload-hint>Foto wird beim Speichern dem neuen Gerät zugeordnet.</span><?php else: ?><?= htmlspecialchars($contextHint) ?><?php endif; ?></div>
      <div class="media-upload-action"><button class="btn btn-primary media-upload-submit" type="<?= $stageDraft ? 'button' : 'submit' ?>"<?= $stageDraft ? ' data-stage-device-photo-upload' : '' ?>><i class="fa-solid fa-upload me-1" aria-hidden="true"></i><?= htmlspecialchars($submitText) ?></button></div>
    <?php endif; ?>
  </div>
<?php if ($inline): ?></div><?php else: ?></form><?php endif; ?>
<style>
  .media-upload-component{background:var(--bs-tertiary-bg);container-type:inline-size}
  .media-upload-layout{display:grid;gap:1rem;align-items:end}
  .media-upload-source{min-width:0}.media-upload-source .input-group{min-width:0}
  .media-upload-hint{align-self:center;overflow-wrap:anywhere}.media-upload-action{display:flex;justify-content:flex-end}
  .media-upload-component .form-control,.media-upload-component .form-select,.media-upload-component .btn{min-height:2.75rem}
  .media-upload-paste{min-height:2.75rem;padding:.55rem .75rem;border:1px dashed var(--bs-border-color);border-radius:var(--bs-border-radius);color:var(--bs-secondary-color);cursor:text}
  .media-upload-paste:focus{outline:0;border-color:var(--bs-primary);box-shadow:0 0 0 .2rem rgba(var(--bs-primary-rgb),.2)}
  .media-upload-submit{min-width:11.5rem;display:inline-flex;align-items:center;justify-content:center}
  .media-upload-component .form-text,.media-upload-component .small{overflow-wrap:anywhere}
  .media-upload-source{grid-area:source}.media-upload-type{grid-area:type}.media-upload-caption{grid-area:caption}.media-upload-hint{grid-area:hint}.media-upload-action{grid-area:action}
  @container (min-width: 34rem){.media-upload-layout{grid-template-columns:minmax(0,1fr) minmax(0,1fr);grid-template-areas:"source source" "type caption" "hint action"}.media-upload-layout-simple{grid-template-areas:"source source" "hint action"}}
  @container (min-width: 54rem){.media-upload-layout{grid-template-columns:minmax(16rem,1.05fr) minmax(9rem,.45fr) minmax(15rem,1fr) auto;grid-template-areas:"source type caption action" "source hint hint hint"}.media-upload-layout-simple{grid-template-columns:minmax(16rem,1fr) minmax(14rem,1fr) auto;grid-template-areas:"source hint action"}}
  @media(max-width:575.98px){.media-upload-submit{width:100%}.media-upload-paste span{display:block;margin-top:.15rem}.media-upload-action{justify-content:stretch}}
</style>
