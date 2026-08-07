<?php
$scalar = static function ($value): string {
    if (is_array($value)) {
        foreach (['brezel_name', 'name', 'email'] as $key) {
            if (isset($value[$key]) && is_scalar($value[$key])) return (string) $value[$key];
        }
    }
    return is_scalar($value) ? (string) $value : '';
};
$status = InspectionEvaluationService::presentation((string) ($inspection->result_status ?? ''), (string) ($inspection->status ?? ''));
$classification = trim((string) ($inspection->classification ?? ''));
$isLegacy = $classification === 'legacy';
$canEdit = current_user_has_role('admin', 'editor');
$reportAllowed = InspectionEvaluationService::reportPathAllowed(
    $status['status'],
    $classification,
    (string) ($inspection->report_path ?? ''),
    current_user_is_superadmin()
);
?>
<div class="card shadow-sm">
  <div class="card-body">
    <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
      <div>
        <div class="small text-body-secondary mb-1"><i class="fa-solid fa-clipboard-check me-1" aria-hidden="true"></i>Prüfnummer <?= htmlspecialchars((string) ($inspection->external_number ?: '—')) ?></div>
        <h1 class="h4 mb-1">Prüfung <?= htmlspecialchars((string) ($inspection->external_number ?: '#' . $inspection->id)) ?></h1>
        <a class="text-decoration-none" href="<?= htmlspecialchars(url_for('geraete?device_id=' . (int) $device->id), ENT_QUOTES) ?>"><i class="fa-solid fa-plug me-1" aria-hidden="true"></i><?= htmlspecialchars((string) $device->external_number) ?> · <?= htmlspecialchars((string) $device->name) ?></a>
      </div>
      <div class="d-flex flex-wrap gap-2">
        <a class="btn btn-outline-secondary btn-sm" href="<?= htmlspecialchars(url_for('geraete?device_id=' . (int) $device->id), ENT_QUOTES) ?>"><i class="fa-solid fa-arrow-left me-1" aria-hidden="true"></i>Gerätehistorie</a>
        <?php if (($canEdit && !$isLegacy) || ($isLegacy && current_user_is_superadmin())): ?><a class="btn btn-primary btn-sm" href="<?= htmlspecialchars(url_for('admin/pruefungen/' . (int) $inspection->id . '/bearbeiten'), ENT_QUOTES) ?>"><i class="fa-solid fa-pen-to-square me-1" aria-hidden="true"></i><?= $isLegacy ? 'Metadaten korrigieren' : ($status['status'] === InspectionEvaluationService::IN_PROGRESS ? 'Weiterbearbeiten' : 'Daten bearbeiten') ?></a><?php endif; ?>
      </div>
    </div>

    <div class="d-flex flex-wrap gap-2 mt-3">
      <span class="badge text-bg-<?= htmlspecialchars($status['class'], ENT_QUOTES) ?>"><i class="fa-solid <?= htmlspecialchars($status['icon'], ENT_QUOTES) ?> me-1" aria-hidden="true"></i><?= htmlspecialchars($status['label']) ?></span>
      <?php if ($isLegacy): ?><span class="badge text-bg-secondary"><i class="fa-solid fa-box-archive me-1" aria-hidden="true"></i>Legacy bis 2024</span><?php elseif ($classification === 'migrated_import'): ?><span class="badge text-bg-info"><i class="fa-solid fa-file-import me-1" aria-hidden="true"></i>Migrierter Import</span><?php else: ?><span class="badge text-bg-primary"><i class="fa-solid fa-database me-1" aria-hidden="true"></i>Prüfapp-Daten</span><?php endif; ?>
      <?php if (!empty($inspectionType)): ?><span class="badge text-bg-secondary"><i class="fa-solid <?= htmlspecialchars((string) ($inspectionType['icon'] ?: 'fa-clipboard-check'), ENT_QUOTES) ?> me-1" aria-hidden="true"></i><?= htmlspecialchars((string) $inspectionType['name']) ?></span><?php endif; ?>
      <?php if (current_user_has_role('admin')): $billingEligibility = (string) ($inspection->billing_eligibility ?? ($inspection->billable ? 'billable' : 'not_billable')); ?><span class="badge text-bg-<?= $billingEligibility === 'billable' ? 'success' : 'secondary' ?>"><i class="fa-solid fa-coins me-1" aria-hidden="true"></i><?= $billingEligibility === 'billable' ? 'Abrechenbar' : 'Nicht abrechenbar' ?></span><?php endif; ?>
    </div>

    <?php if (trim((string) ($inspection->result_reason_text ?? '')) !== ''): ?><div class="alert alert-<?= $status['status'] === InspectionEvaluationService::FAILED ? 'danger' : ($status['status'] === InspectionEvaluationService::PASSED ? 'success' : 'warning') ?> py-2 mt-3 mb-0"><strong><?= htmlspecialchars($status['label']) ?>:</strong> <?= htmlspecialchars((string) $inspection->result_reason_text) ?></div><?php endif; ?>

    <div class="row g-3 mt-2">
      <?php foreach ([['fa-calendar-day','Datum',(string) ($inspection->test_date ?: '—')],['fa-user-check','Prüfer',display_examiner_name((string) ($inspection->examiner ?: $scalar($raw['created_by'] ?? '—')))],['fa-shield-halved','Schutzklasse',(string) ($inspection->protection_class ?: '—')],['fa-ruler-horizontal','Kabellänge',$inspection->cable_length_m !== null && $inspection->cable_length_m !== '' ? (string) $inspection->cable_length_m . ' m' : '—'],['fa-temperature-high','Wärmegerät',!empty($inspection->warming_device_snapshot ?? $device->warming_device) ? 'Ja' : 'Nein'],['fa-calendar-check','Nächste Prüfung',(string) ($inspection->next_due_date ?: '—')],['fa-location-dot','Raum',(string) ($inspection->room_snapshot ?: '—')],['fa-file-import','Quelle',(string) ($inspection->source_file ?: $inspection->source_type ?: '—')]] as [$icon,$label,$value]): ?>
        <div class="col-sm-6 col-xl-3"><div class="border rounded h-100 p-3"><div class="small text-body-secondary"><i class="fa-solid <?= $icon ?> me-1" aria-hidden="true"></i><?= htmlspecialchars($label) ?></div><div class="fw-semibold text-break mt-1"><?= htmlspecialchars($value) ?></div></div></div>
      <?php endforeach; ?>
    </div>

    <div class="d-flex flex-wrap gap-2 mt-4">
      <?php if ($reportAllowed): ?><a class="btn btn-primary" href="<?= htmlspecialchars(url_for('pruefungen/' . (int) $inspection->id . '/bericht'), ENT_QUOTES) ?>" target="_blank" rel="noopener"><i class="fa-solid fa-file-pdf me-1" aria-hidden="true"></i><?= $isLegacy ? 'Original-Prüfbericht' : 'Prüfbericht' ?> öffnen</a><?php endif; ?>
      <?php if (!$reportAllowed): ?><span class="text-body-secondary align-self-center"><i class="fa-solid fa-circle-info me-1" aria-hidden="true"></i>Ein regulärer Bericht wird erst bei eindeutig bestandenem oder nicht bestandenem Ergebnis bereitgestellt.</span><?php endif; ?>
      <?php if (current_user_is_superadmin() && !$isLegacy && InspectionEvaluationService::isCompleted($status['status'])): ?><form method="post" action="<?= htmlspecialchars(url_for('admin/pruefungen/' . (int) $inspection->id . '/bericht/neu-erzeugen'), ENT_QUOTES) ?>"><button class="btn btn-warning" type="submit"><i class="fa-solid fa-rotate me-1" aria-hidden="true"></i>Bericht neu erzeugen</button></form><?php endif; ?>
    </div>

    <?php if (current_user_has_role('admin') && ($billingInvoice || $billingHistory)): ?>
      <details class="mt-4 border rounded p-3">
        <summary class="fw-semibold"><i class="fa-solid fa-file-invoice-dollar me-2" aria-hidden="true"></i>Abrechnungshistorie</summary>
        <?php if ($billingInvoice): ?><div class="alert alert-success mt-3 mb-2"><strong>Aktuelle Rechnung:</strong> <a href="<?= htmlspecialchars(url_for('admin/abrechnung/rechnung/' . (int) $billingInvoice['invoice_id']), ENT_QUOTES) ?>"><?= htmlspecialchars((string) ($billingInvoice['sevdesk_invoice_number'] ?: $billingInvoice['invoice_number'] ?: '#' . $billingInvoice['invoice_id'])) ?></a></div><?php endif; ?>
        <div class="table-responsive mt-3"><table class="table table-sm align-middle mb-0"><thead><tr><th>Rechnung</th><th>Zugeordnet</th><th>Status</th><th>Grund der Aufhebung</th></tr></thead><tbody><?php foreach ($billingHistory as $entry): ?><tr><td><a href="<?= htmlspecialchars(url_for('admin/abrechnung/rechnung/' . (int) $entry['invoice_id']), ENT_QUOTES) ?>"><?= htmlspecialchars((string) ($entry['sevdesk_invoice_number'] ?: $entry['invoice_number'] ?: '#' . $entry['invoice_id'])) ?></a></td><td><?= htmlspecialchars((string) ($entry['assigned_at'] ?: '—')) ?></td><td><span class="badge text-bg-<?= !empty($entry['active']) ? 'success' : 'secondary' ?>"><?= !empty($entry['active']) ? 'Aktiv' : 'Historisch' ?></span></td><td><?= htmlspecialchars((string) ($entry['deactivation_reason'] ?: '—')) ?></td></tr><?php endforeach; ?></tbody></table></div>
      </details>
    <?php endif; ?>

    <?php if (!empty($findings)): ?><section class="mt-4"><h2 class="h5"><i class="fa-solid fa-triangle-exclamation me-2" aria-hidden="true"></i>Offene Kundenhinweise und Mängel</h2><div class="table-responsive"><table class="table table-sm align-middle"><thead><tr><th>Schwere</th><th>Hinweis</th><th>Frist</th><th>Maßnahme</th></tr></thead><tbody><?php foreach ($findings as $finding): $class = $finding['severity'] === 'red' ? 'danger' : ($finding['severity'] === 'orange' ? 'warning text-dark' : 'success'); ?><tr><td><span class="badge text-bg-<?= $class ?>"><?= htmlspecialchars(ucfirst((string) $finding['severity'])) ?></span><?= !empty($finding['blocked']) ? ' <span class="badge text-bg-danger">Gesperrt</span>' : '' ?></td><td><?= htmlspecialchars((string) ($finding['description'] ?: $finding['item_key'])) ?></td><td><?= htmlspecialchars((string) ($finding['due_date'] ?: '—')) ?></td><td><?= htmlspecialchars((string) ($finding['action'] ?: '—')) ?></td></tr><?php endforeach; ?></tbody></table></div></section><?php endif; ?>

    <?php if (!empty($inspectionMedia)): ?><section class="mt-4"><h2 class="h5"><i class="fa-solid fa-camera me-2" aria-hidden="true"></i>Fotos zur Prüfung</h2><div class="row g-3"><?php foreach ($inspectionMedia as $media): ?><div class="col-6 col-md-4 col-xl-3"><figure class="card h-100 mb-0"><a href="<?= htmlspecialchars(url_for('geraete/fotos/' . (int) $media['id']), ENT_QUOTES) ?>" target="_blank" rel="noopener"><img class="card-img-top object-fit-cover" style="height: 10rem" src="<?= htmlspecialchars(url_for('geraete/fotos/' . (int) $media['id']), ENT_QUOTES) ?>" alt="<?= htmlspecialchars((string) ($media['caption'] ?: 'Prüfungsfoto')) ?>"></a><figcaption class="card-body py-2 small text-body-secondary"><?= htmlspecialchars((string) ($media['caption'] ?: 'Prüfungsfoto')) ?></figcaption></figure></div><?php endforeach; ?></div></section><?php endif; ?>

    <?php if (!$isLegacy): ?>
      <hr class="my-4">
      <h2 class="h5"><i class="fa-solid fa-list-check me-2" aria-hidden="true"></i>Prüffragen</h2>
      <?php if ($checklist): ?><div class="table-responsive"><table class="table table-hover align-middle"><thead><tr><th>Kategorie</th><th>Prüffrage</th><th>Kriterium</th><th>Antwort</th><th>Ergebnis</th></tr></thead><tbody><?php foreach ($checklist as $answer): $answerOutcome = (string) ($answer['outcome'] ?? 'missing'); $answerStatus = $answerOutcome === 'passed' ? ['success','fa-check','Bestanden'] : ($answerOutcome === 'failed' ? ['danger','fa-xmark','Nicht bestanden'] : ($answerOutcome === 'skipped' ? ['secondary','fa-forward','Nicht durchgeführt'] : ['warning text-dark','fa-circle-exclamation','Daten fehlen'])); $skipReason = trim((string) ($answer['skip_reason'] ?? '')); ?><tr><td><?= htmlspecialchars((string) ($answer['category'] ?? '')) ?></td><td class="fw-semibold"><?= htmlspecialchars((string) ($answer['question_snapshot'] ?? $answer['step'] ?? '')) ?></td><td class="small text-body-secondary"><?= htmlspecialchars((string) ($answer['criterion_snapshot'] ?? $answer['criterion'] ?? '')) ?></td><td><?= htmlspecialchars($skipReason !== '' ? $skipReason : (string) ($answer['answer_value'] ?? $answer['result'] ?? '—')) ?></td><td><span class="badge text-bg-<?= $answerStatus[0] ?>"><i class="fa-solid <?= $answerStatus[1] ?> me-1" aria-hidden="true"></i><?= $answerStatus[2] ?></span></td></tr><?php endforeach; ?></tbody></table></div><?php else: ?><div class="alert alert-warning"><i class="fa-solid fa-circle-exclamation me-1" aria-hidden="true"></i>Es sind noch keine strukturierten Prüfantworten vorhanden.</div><?php endif; ?>

      <h2 class="h5 mt-4"><i class="fa-solid fa-gauge-high me-2" aria-hidden="true"></i>Messwerte</h2>
      <?php if ($measurements): ?><div class="table-responsive"><table class="table table-hover align-middle"><thead><tr><th>Messung</th><th>Messwert</th><th>Einheit</th><th>Grenzwert</th><th>Prüfspannung</th><th>Ergebnis</th></tr></thead><tbody><?php foreach ($measurements as $measurement): $canonical = array_key_exists('measurement_key', $measurement); $measurementOutcome = $canonical ? (string) $measurement['outcome'] : InspectionEvaluationService::normalizeOutcome((string) ($measurement['result'] ?? '')); $measurementStatus = InspectionEvaluationService::presentation($measurementOutcome); ?><tr><td class="fw-semibold"><?= htmlspecialchars((string) ($measurement['name_snapshot'] ?? $measurement['name'] ?? $measurement['measurement_key'] ?? '')) ?></td><td><?= htmlspecialchars((string) (($canonical && ($measurement['numeric_value'] ?? null) !== null) ? $measurement['numeric_value'] : ($measurement['text_value'] ?? $measurement['value'] ?? '—'))) ?></td><td><?= htmlspecialchars((string) ($measurement['unit'] ?? '')) ?></td><td><?= $canonical && ($measurement['limit_value'] ?? null) !== null ? htmlspecialchars((string) $measurement['limit_value'] . ' ' . (string) $measurement['limit_unit']) : htmlspecialchars((string) ($measurement['limit'] ?? '—')) ?></td><td><?= htmlspecialchars(trim((string) ($measurement['voltage'] ?? '')) ?: '—') ?></td><td><span class="badge text-bg-<?= htmlspecialchars($measurementStatus['class'], ENT_QUOTES) ?>"><i class="fa-solid <?= htmlspecialchars($measurementStatus['icon'], ENT_QUOTES) ?> me-1" aria-hidden="true"></i><?= htmlspecialchars($measurementStatus['label']) ?></span></td></tr><?php endforeach; ?></tbody></table></div><?php else: ?><div class="alert alert-warning"><i class="fa-solid fa-circle-exclamation me-1" aria-hidden="true"></i>Es sind keine strukturierten Messwerte vorhanden.</div><?php endif; ?>
    <?php endif; ?>

    <?php if (current_user_is_superadmin() && !empty($diagnostics)): ?><details class="mt-4 border rounded p-3"><summary class="fw-semibold"><i class="fa-solid fa-stethoscope me-1" aria-hidden="true"></i>Import- und Datenanalyse (<?= count($diagnostics) ?>)</summary><div class="vstack gap-2 mt-3"><?php foreach ($diagnostics as $diagnostic): ?><div class="alert alert-<?= ($diagnostic['severity'] ?? '') === 'error' ? 'danger' : 'warning' ?> mb-0"><strong><?= htmlspecialchars((string) $diagnostic['code']) ?>:</strong> <?= htmlspecialchars((string) $diagnostic['message']) ?></div><?php endforeach; ?></div></details><?php endif; ?>
  </div>
</div>
