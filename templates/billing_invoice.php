<div class="d-flex justify-content-between align-items-start gap-3 mb-4 flex-wrap">
  <div><h1 class="h3 mb-1">Rechnung #<?= (int) $invoice->id ?></h1><p class="text-body-secondary mb-0">Abrechnungsnachweis und verknüpfte Prüfungen</p></div>
  <a class="btn btn-outline-secondary" href="<?= htmlspecialchars(url_for('admin/abrechnung'), ENT_QUOTES) ?>">Zur Abrechnung</a>
</div>
<dl class="row">
  <dt class="col-sm-3">Rechnungsnummer</dt><dd class="col-sm-9"><?= htmlspecialchars((string) ($invoice->invoice_number ?: '—')) ?></dd>
  <dt class="col-sm-3">SevDesk-ID</dt><dd class="col-sm-9"><?= htmlspecialchars((string) ($invoice->sevdesk_invoice_id ?: '—')) ?></dd>
  <dt class="col-sm-3">Datum</dt><dd class="col-sm-9"><?= htmlspecialchars((string) ($invoice->invoice_date ?: '—')) ?></dd>
  <dt class="col-sm-3">Status</dt><dd class="col-sm-9"><span class="badge text-bg-secondary"><?= htmlspecialchars((string) $invoice->status) ?></span></dd>
</dl>
<h2 class="h5 mt-4">Verknüpfte Prüfungen und Geräte</h2>
<form method="post" action="<?= htmlspecialchars(url_for('geraete/export'), ENT_QUOTES) ?>" class="mb-3 d-flex align-items-end gap-2 flex-wrap"><input type="hidden" name="scope" value="selection"><input type="hidden" name="invoice_id" value="<?= (int) $invoice->id ?>"><label>Max. Seiten<input class="form-control form-control-sm" type="number" name="bundle_max_pages" min="10" max="5000" value="500"></label><button class="btn btn-sm btn-success" name="format" value="bundle_pdf">Sammel-PDF dieser Rechnung erzeugen</button></form>
<div class="table-responsive"><table class="table table-sm align-middle"><thead><tr><th>Gerät</th><th>Prüfung</th><th>Datum</th><th>Menge</th></tr></thead><tbody>
<?php foreach ($items as $item): ?><tr><td><a href="<?= htmlspecialchars(url_for('geraete?device_id=' . (int) $item['device_id']), ENT_QUOTES) ?>"><?= htmlspecialchars((string) $item['device_number']) ?> · <?= htmlspecialchars((string) $item['device_name']) ?></a></td><td><a href="<?= htmlspecialchars(url_for('admin/pruefungen/' . (int) $item['inspection_id']), ENT_QUOTES) ?>"><?= htmlspecialchars((string) $item['inspection_number']) ?></a></td><td><?= htmlspecialchars((string) $item['test_date']) ?></td><td><?= htmlspecialchars((string) $item['quantity']) ?></td></tr><?php endforeach; ?>
<?php if ($items === []): ?><tr><td colspan="4" class="text-body-secondary">Keine verknüpften Positionen.</td></tr><?php endif; ?></tbody></table></div>
