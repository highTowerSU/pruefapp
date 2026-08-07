<section class="card shadow-sm" id="vocabulary-review-panel" data-action-nav="Stammdaten bereinigen" data-action-icon="fa-spell-check">
  <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
    <h1 class="h5 mb-0"><i class="fa-solid fa-spell-check me-2" aria-hidden="true"></i>Stammdaten bereinigen</h1>
    <span class="badge text-bg-primary"><?= count($reviews) ?> offen</span>
  </div>
  <div class="card-body">
    <p class="text-body-secondary">KI-Vorschläge ändern nichts automatisch. Prüfe die Zusammenführung vor dem Freigeben; Rohimportdaten und Legacy-PDFs bleiben unverändert.</p>
    <?php if ($reviews === []): ?><div class="alert alert-success mb-0"><i class="fa-solid fa-circle-check me-1" aria-hidden="true"></i>Keine offenen Vorschläge.</div><?php endif; ?>
    <?php foreach (DeviceVocabularyService::FIELDS as $field): $rows = array_values(array_filter($reviews, static fn($review): bool => (string) $review->field_name === $field)); if ($rows === []) continue; $label = ['manufacturer' => 'Hersteller', 'device_model' => 'Modelle', 'name' => 'Gerätebezeichnungen'][$field]; ?>
      <h2 class="h6 mt-4"><i class="fa-solid fa-tag me-1" aria-hidden="true"></i><?= htmlspecialchars($label) ?></h2>
      <div class="table-responsive"><table class="table table-sm align-middle"><thead><tr><th>Quellwert</th><th>Vorschlag</th><th>Sicherheit</th><th>Begründung</th><th>Aktion</th></tr></thead><tbody>
      <?php foreach ($rows as $review): ?><tr><td><code><?= htmlspecialchars((string) $review->source_value) ?></code></td><td><?= htmlspecialchars((string) $review->suggested_value ?: 'Kein sicherer Vorschlag') ?></td><td><?= number_format((float) $review->confidence * 100, 0) ?> %</td><td class="small text-body-secondary"><?= htmlspecialchars((string) $review->reason) ?></td><td><form method="post" class="d-flex flex-wrap gap-1"><input type="hidden" name="review_id" value="<?= (int) $review->id ?>"><input class="form-control form-control-sm" name="canonical_value" value="<?= htmlspecialchars((string) $review->suggested_value) ?>" placeholder="Zielwert"><button class="btn btn-sm btn-primary" name="action" value="merge"><i class="fa-solid fa-code-merge me-1" aria-hidden="true"></i>Zusammenführen</button><button class="btn btn-sm btn-outline-secondary" name="action" value="keep"><i class="fa-solid fa-keep me-1" aria-hidden="true"></i>Behalten</button></form></td></tr><?php endforeach; ?>
      </tbody></table></div>
    <?php endforeach; ?>
  </div>
</section>
