<?php
$batch = $batch ?? null;
$hasBatch = !empty($batch['rows']);
$money = static fn(int $cents): string => number_format($cents / 100, 2, ',', '.') . ' €';
$batchFilters = array_diff_key($filters ?? [], array_flip(['page', 'preselect', 'suggest_batch', 'batch_min', 'batch_max']));
?>
<section class="card mb-4" id="billing-next-invoice">
    <div class="card-header fw-semibold"><i class="fa-solid fa-wand-magic-sparkles me-2" aria-hidden="true"></i>Nächste Rechnung nach Prüfdatum</div>
    <div class="card-body">
        <p>Die ältesten freigegebenen, noch nicht zugeordneten Prüfungen werden je Rechnungsempfänger und Leistungsjahr gebündelt. Die Vorschau berücksichtigt Gerätepreis, Regie und Umsatzsteuer.</p>
        <form method="get" action="<?= htmlspecialchars(url_for('admin/abrechnung') . '#billing-next-invoice', ENT_QUOTES) ?>" class="row g-3 align-items-end">
            <?php foreach ($batchFilters as $key => $value): if (!is_scalar($value)) continue; ?>
                <input type="hidden" name="<?= htmlspecialchars((string) $key, ENT_QUOTES) ?>" value="<?= htmlspecialchars((string) $value, ENT_QUOTES) ?>">
            <?php endforeach; ?>
            <input type="hidden" name="suggest_batch" value="1">
            <div class="col-12 col-sm-6 col-lg-3"><label class="form-label" for="batch-min"><i class="fa-solid fa-euro-sign me-1" aria-hidden="true"></i>Brutto über</label><div class="input-group"><input class="form-control" id="batch-min" name="batch_min" type="number" min="0.01" max="9999999" step="0.01" value="<?= htmlspecialchars((string) ($filters['batch_min'] ?? '500'), ENT_QUOTES) ?>" required><span class="input-group-text">€</span></div></div>
            <div class="col-12 col-sm-6 col-lg-3"><label class="form-label" for="batch-max"><i class="fa-solid fa-euro-sign me-1" aria-hidden="true"></i>Brutto unter</label><div class="input-group"><input class="form-control" id="batch-max" name="batch_max" type="number" min="0.02" max="9999999" step="0.01" value="<?= htmlspecialchars((string) ($filters['batch_max'] ?? '1000'), ENT_QUOTES) ?>" required><span class="input-group-text">€</span></div></div>
            <div class="col-12 col-lg-6"><button class="btn btn-primary" type="submit"><i class="fa-solid fa-calculator me-1" aria-hidden="true"></i>Nächste Rechnung vorschlagen</button></div>
        </form>
        <?php if (!empty($batchError)): ?><div class="alert alert-warning mt-3 mb-0" role="alert"><?= htmlspecialchars((string) $batchError) ?></div><?php endif; ?>
        <?php if (($unapprovedCount ?? 0) > 0):
            $reviewUrl = url_for('admin/abrechnung?' . http_build_query(array_merge($batchFilters, ['eligibility' => 'all', 'billing_status' => 'not_billed', 'sort' => 'test_date_asc'])));
        ?>
            <div class="alert alert-info mt-3 mb-0"><p>Für diesen Filter gibt es keine freigegebenen Prüfungen. <?= (int) $unapprovedCount ?> offene Prüfungen sind noch nicht als abrechenbar freigegeben. Prüfe sie zuerst und wähle unter „Weitere Aktionen“ die Freigabe der passenden Auswahl.</p><a class="btn btn-outline-primary" href="<?= htmlspecialchars($reviewUrl, ENT_QUOTES) ?>"><i class="fa-solid fa-list-check me-1" aria-hidden="true"></i>Offene Prüfungen und Freigaben prüfen</a></div>
        <?php endif; ?>
        <?php if ($hasBatch): ?>
            <div class="alert alert-success mt-3 mb-0">
                <h2 class="h5"><i class="fa-solid fa-file-invoice-dollar me-1" aria-hidden="true"></i>Vorschlag: <?= $money($batch['totals']['gross_cents']) ?> brutto</h2>
                <p class="mb-1"><?= htmlspecialchars($batch['customer']) ?> · Leistungsjahr <?= htmlspecialchars($batch['year']) ?> · <?= htmlspecialchars($batch['from']) ?> bis <?= htmlspecialchars($batch['until']) ?></p>
                <p class="mb-1"><?= (int) $batch['totals']['count'] ?> Prüfungen · <?= (int) $batch['totals']['minutes'] ?> Regieminuten · <?= $money($batch['totals']['net_cents']) ?> netto + <?= $money($batch['totals']['tax_cents']) ?> USt.</p>
                <p class="small mb-0">Die Prüfungen sind unten vorausgewählt. Prüfe die Auswahl und klicke auf „SevDesk-Entwurf“. Die Summe ist eine Vorschau anhand der aktuellen Mandantenpreise; geändert werden noch keine Rechnungen.</p>
            </div>
        <?php elseif ($batch !== null && empty($batchError) && ($unapprovedCount ?? 0) === 0): ?>
            <div class="alert alert-info mt-3 mb-0">Aktuell erreicht keine chronologische Auswahl derselben Rechnungsempfänger und Leistungsjahre die gewählten Grenzen. Restmengen und einzelne zu große Positionen bleiben zur Prüfung offen.</div>
        <?php endif; ?>
        <?php if (!empty($batch['remainders'])): ?>
            <div class="table-responsive mt-3"><table class="table table-sm mb-0"><caption>Frühere offene Gruppen ohne passenden Rechnungsvorschlag</caption><thead><tr><th>Kunde</th><th>Jahr</th><th>Prüfungen</th><th>Offene Gesamtsumme brutto</th></tr></thead><tbody><?php foreach ($batch['remainders'] as $remainder): ?><tr><td><?= htmlspecialchars($remainder['customer']) ?></td><td><?= htmlspecialchars($remainder['year']) ?></td><td><?= (int) $remainder['count'] ?></td><td><?= $money($remainder['gross_cents']) ?></td></tr><?php endforeach; ?></tbody></table></div>
        <?php endif; ?>
        <?php if (!empty($batch['excluded'])): ?><p class="small text-body-secondary mt-3 mb-0"><?= (int) $batch['excluded'] ?> Prüfungen ohne eindeutigen Rechnungsempfänger/Prüftag oder mit vorhandener Zuordnung wurden nicht vorgeschlagen.</p><?php endif; ?>
    </div>
</section>
