<div class="card shadow-sm"><div class="card-body">
  <h1 class="h4"><i class="fa-solid fa-stairs me-2" aria-hidden="true"></i>Leiterprüfung</h1>
  <?= render_template('inspection_device_summary.php', ['device' => $device, 'inspectionTypeCode' => InspectionTypeService::LADDER]) ?>
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <span class="badge text-bg-<?= htmlspecialchars($resultPresentation['class'], ENT_QUOTES) ?>"><i class="fa-solid <?= htmlspecialchars($resultPresentation['icon'], ENT_QUOTES) ?> me-1" aria-hidden="true"></i><?= htmlspecialchars($resultPresentation['label']) ?></span>
    <span class="text-body-secondary"><?= (int) $answeredCount ?> von <?= count($catalog) ?> Prüfpunkten bewertet</span>
  </div>
  <?php if ($missingRequirements !== []): ?>
    <div class="alert alert-warning"><strong><i class="fa-solid fa-list-check me-1" aria-hidden="true"></i>Zum Abschluss fehlt noch:</strong><ul class="mb-0 mt-2"><?php foreach ($missingRequirements as $missing): ?><li><?= htmlspecialchars((string) $missing) ?></li><?php endforeach; ?></ul></div>
  <?php endif; ?>
  <?php if ($error !== null): ?><div class="alert alert-danger"><i class="fa-solid fa-triangle-exclamation me-1" aria-hidden="true"></i><?= htmlspecialchars($error) ?></div><?php endif; ?>
  <?= render_template('inspection_companion_panel.php', ['inspection' => $inspection, 'session' => $companionSession ?? []]) ?>
  <?= render_template('inspection_companion_inbox.php', ['items' => InspectionCompanionInboxService::itemsForOwner((int) current_user()->id)]) ?>
  <?= render_template('inspection_media_panel.php', ['inspection' => $inspection, 'inspectionMedia' => $inspectionMedia ?? []]) ?>
  <form method="post" class="row g-3">
    <div class="col-md-4"><label class="form-label"><i class="fa-solid fa-calendar-day me-1" aria-hidden="true"></i>Prüfdatum *</label><input class="form-control" type="date" name="test_date" value="<?= htmlspecialchars((string) $inspection->test_date) ?>" required></div>
    <div class="col-md-4"><label class="form-label"><i class="fa-solid fa-calendar-check me-1" aria-hidden="true"></i>Nächstes Prüfdatum *</label><input class="form-control" type="date" name="next_due_date" value="<?= htmlspecialchars((string) $inspection->next_due_date) ?>" required></div>
    <div class="col-md-4"><label class="form-label"><i class="fa-solid fa-user-check me-1" aria-hidden="true"></i>Prüfer</label><input class="form-control" value="<?= htmlspecialchars(display_examiner_name((string) $inspection->examiner)) ?>" disabled></div>
    <div class="col-12"><h2 class="h5 border-bottom pb-2"><i class="fa-solid fa-ruler-combined me-2" aria-hidden="true"></i>Leiterdaten</h2><p class="small text-body-secondary mb-0">Vorhandene Stammdaten und Werte dieser Prüfung sind übernommen. Ergänzende Leiterdaten nur eintragen, soweit bekannt.</p></div>
    <?php foreach (InspectionTypeService::attributeDefinitions(InspectionTypeService::LADDER) as $key => $definition): ?><div class="col-md-4"><label class="form-label" for="attribute-<?= htmlspecialchars($key) ?>"><?= htmlspecialchars($definition['label']) ?></label><?php if ($definition['type'] === 'select'): ?><select class="form-select" id="attribute-<?= htmlspecialchars($key) ?>" name="attributes[<?= htmlspecialchars($key) ?>]"><option value="">Bitte wählen</option><?php foreach ($definition['options'] as $value => $label): ?><option value="<?= htmlspecialchars($value) ?>"<?= (string) ($attributes[$key] ?? '') === $value ? ' selected' : '' ?>><?= htmlspecialchars($label) ?></option><?php endforeach; ?></select><?php else: ?><input class="form-control" id="attribute-<?= htmlspecialchars($key) ?>" type="<?= $definition['type'] === 'number' ? 'number' : ($definition['type'] === 'date' ? 'date' : 'text') ?>" name="attributes[<?= htmlspecialchars($key) ?>]" value="<?= htmlspecialchars((string) ($attributes[$key] ?? '')) ?>"<?= $definition['type'] === 'number' ? ' step="any"' : '' ?>><?php endif; ?></div><?php endforeach; ?>
    <div class="col-12">
      <h2 class="h5 border-bottom pb-2"><i class="fa-solid fa-list-check me-2" aria-hidden="true"></i>Prüfpunkte – Sicht- und Funktionsprüfung</h2>
      <p class="text-body-secondary mb-2">Jeden zutreffenden Punkt am Arbeitsmittel prüfen. Bei bauartabhängigen Bauteilen ist „Nicht zutreffend“ mit kurzer Begründung möglich. Fehlt ein für diese Bauart erforderliches Teil, ist das ein Mangel.</p>
      <p class="small mb-0"><a href="<?= htmlspecialchars(LadderInspectionService::REFERENCE_URL, ENT_QUOTES) ?>" target="_blank" rel="noopener"><i class="fa-solid fa-book-open me-1" aria-hidden="true"></i>DGUV Information 208-016 – Hinweise zur Leiterprüfung</a></p>
    </div>
    <?php foreach ($catalog as $item): ?>
      <?php
      $key = (string) $item['item_key'];
      $answer = $answersByKey[$key] ?? [];
      $value = (string) ($answer['answer_value'] ?? '');
      $prefix = 'ladder-' . (int) $inspection->id . '-' . $key;
      $choices = ['' => ['Noch nicht geprüft', 'secondary'], 'ok' => ['In Ordnung', 'success'], 'green' => ['Hinweis · Grün', 'success'], 'orange' => ['Mangel · Orange', 'warning'], 'red' => ['Mangel · Rot', 'danger']];
      if ($item['allow_na']) $choices['na'] = ['Nicht zutreffend', 'secondary'];
      ?>
      <div class="col-12"><fieldset class="border rounded-3 p-3 h-100">
        <legend class="float-none w-auto h6 px-2 mb-2"><?= htmlspecialchars((string) $item['category']) ?></legend>
        <p class="fw-semibold mb-1"><?= htmlspecialchars((string) $item['question']) ?></p>
        <p class="small text-body-secondary mb-2"><?= htmlspecialchars((string) $item['criterion']) ?></p>
        <?php if ($item['applicability_note'] !== ''): ?><p class="small mb-2"><i class="fa-solid fa-circle-info me-1" aria-hidden="true"></i><?= htmlspecialchars($item['applicability_note']) ?></p><?php endif; ?>
        <div class="d-flex flex-wrap gap-2 mb-3" role="group" aria-label="<?= htmlspecialchars((string) $item['category'], ENT_QUOTES) ?> bewerten">
          <?php foreach ($choices as $choice => [$label, $color]): $choiceId = $prefix . '-' . ($choice === '' ? 'open' : $choice); ?>
            <input class="btn-check" type="radio" id="<?= htmlspecialchars($choiceId, ENT_QUOTES) ?>" name="finding[<?= htmlspecialchars($key, ENT_QUOTES) ?>]" value="<?= htmlspecialchars($choice, ENT_QUOTES) ?>"<?= $value === $choice ? ' checked' : '' ?>>
            <label class="btn btn-sm btn-outline-<?= $color ?>" for="<?= htmlspecialchars($choiceId, ENT_QUOTES) ?>"><?= htmlspecialchars($label) ?></label>
          <?php endforeach; ?>
        </div>
        <label class="form-label small" for="<?= htmlspecialchars($prefix . '-remark', ENT_QUOTES) ?>"><i class="fa-solid fa-comment me-1" aria-hidden="true"></i>Bemerkung / Begründung</label>
        <textarea class="form-control" id="<?= htmlspecialchars($prefix . '-remark', ENT_QUOTES) ?>" name="remark[<?= htmlspecialchars($key, ENT_QUOTES) ?>]" rows="2" aria-describedby="<?= htmlspecialchars($prefix . '-help', ENT_QUOTES) ?>"><?= htmlspecialchars((string) ($answer['remark'] ?? '')) ?></textarea>
        <div class="form-text" id="<?= htmlspecialchars($prefix . '-help', ENT_QUOTES) ?>">Bei einem Hinweis, Mangel oder „Nicht zutreffend“ bitte den Sachverhalt kurz beschreiben.</div>
      </fieldset></div>
    <?php endforeach; ?>
    <div class="col-md-6"><label class="form-label"><i class="fa-solid fa-comment me-1" aria-hidden="true"></i>Allgemeine Bemerkung</label><textarea class="form-control" name="metadata_notes" rows="3"><?= htmlspecialchars((string) $inspection->metadata_notes) ?></textarea></div>
    <div class="col-md-6"><label class="form-label"><i class="fa-solid fa-comment-dots me-1" aria-hidden="true"></i>Kundenhinweis</label><textarea class="form-control" name="customer_hint" rows="3" placeholder="Wird am Gerät nachverfolgt."><?= htmlspecialchars((string) ($inspection->customer_hint ?? '')) ?></textarea></div>
    <div class="col-md-6"><label class="form-label"><i class="fa-solid fa-screwdriver-wrench me-1" aria-hidden="true"></i>Maßnahme bei nicht bestanden</label><select class="form-select" name="failed_action"><option value="">Bei einem sicherheitsrelevanten Mangel auswählen</option><option value="blocked"<?= (string) $inspection->failed_action === 'blocked' ? ' selected' : '' ?>>Gesperrt</option><option value="repair"<?= (string) $inspection->failed_action === 'repair' ? ' selected' : '' ?>>Zur Reparatur</option><option value="disposed"<?= (string) $inspection->failed_action === 'disposed' ? ' selected' : '' ?>>Entsorgt</option></select></div>
    <div class="col-12"><p class="small text-body-secondary"><i class="fa-solid fa-ban me-1" aria-hidden="true"></i>Orange und Rot bedeuten: nicht verwenden, bis der Mangel behoben ist. Beim Abschluss wird die Maßnahme dokumentiert.</p></div>
    <div class="col-12 d-flex flex-wrap justify-content-end gap-2"><button class="btn btn-outline-primary" type="submit"><i class="fa-solid fa-floppy-disk me-1" aria-hidden="true"></i>Zwischenspeichern</button><button class="btn btn-primary ms-2" name="complete" value="1" type="submit"><i class="fa-solid fa-circle-check me-1" aria-hidden="true"></i>Prüfung abschließen</button><?= render_template('inspection_delete_button.php', ['inspection' => $inspection]) ?></div>
  </form>
</div></div>
