<div class="d-flex justify-content-between align-items-start gap-3 mb-4">
  <div><h1 class="h4 mb-1">Kundeninfos: <?= htmlspecialchars((string) $customer->name) ?></h1><p class="text-body-secondary mb-0">Anleitungen, Bilder, PDFs und Hinweise für diesen Kunden.</p></div>
  <a class="btn btn-outline-secondary" href="<?= htmlspecialchars(url_for('struktur'), ENT_QUOTES) ?>">Zur Struktur</a>
</div>
<?php if ($canManage): ?><details class="card shadow-sm mb-4" open><summary class="card-header"><strong>Neue Kundeninfo</strong></summary><div class="card-body"><?php $info = null; require __DIR__ . '/customer_info_form.php'; ?></div></details><?php endif; ?>
<div class="row g-3">
<?php foreach ($infos as $info): ?>
  <div class="col-12 col-lg-6"><article class="card h-100 shadow-sm"><div class="card-body">
    <h2 class="h5"><a href="<?= htmlspecialchars(url_for('kundeninfos/' . (int) $info->id), ENT_QUOTES) ?>"><?= htmlspecialchars((string) $info->title) ?></a></h2>
    <p class="small text-body-secondary">Zuletzt geändert: <?= htmlspecialchars((string) ($info->updated_at ?: '—')) ?></p>
    <?php if (trim((string) $info->file_name) !== ''): ?><span class="badge text-bg-light border"><i class="fa-solid fa-paperclip me-1" aria-hidden="true"></i><?= htmlspecialchars((string) $info->file_name) ?></span><?php endif; ?>
    <div class="mt-3 d-flex flex-wrap gap-2"><a class="btn btn-sm btn-outline-primary" href="<?= htmlspecialchars(url_for('kundeninfos/' . (int) $info->id), ENT_QUOTES) ?>">Öffnen</a><?php if ($canManage): ?><a class="btn btn-sm btn-outline-secondary" href="<?= htmlspecialchars(url_for('kunden/' . (int) $customer->id . '/infos/' . (int) $info->id . '/bearbeiten'), ENT_QUOTES) ?>">Bearbeiten</a><form class="d-inline" method="post" action="<?= htmlspecialchars(url_for('kunden/' . (int) $customer->id . '/infos/' . (int) $info->id . '/loeschen'), ENT_QUOTES) ?>" onsubmit="return confirm('Diese Kundeninfo wirklich löschen?');"><button class="btn btn-sm btn-outline-danger">Löschen</button></form><?php endif; ?></div>
  </div></article></div>
<?php endforeach; ?>
<?php if ($infos === []): ?><div class="col-12"><div class="alert alert-info">Für diesen Kunden sind noch keine Infos hinterlegt.</div></div><?php endif; ?>
</div>
