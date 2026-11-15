<?php if (InspectionEvaluationService::isUnfinished((string) $inspection->result_status, (string) $inspection->status) && current_user_has_role('admin', 'editor', 'inspector')): ?>
<button class="btn btn-outline-danger" type="submit" formnovalidate data-double-confirm formaction="<?= htmlspecialchars(url_for('admin/pruefungen/' . (int) $inspection->id . '/loeschen'), ENT_QUOTES) ?>" formmethod="post"><i class="fa-solid fa-trash me-1" aria-hidden="true"></i>Prüfung löschen</button>
<?php endif; ?>
