<?php $formatInfoDate = static function ($value): string { $value = trim((string) $value); if ($value === '') return '—'; try { return (new DateTimeImmutable($value))->setTimezone(new DateTimeZone('Europe/Berlin'))->format('d.m.Y, H:i') . ' Uhr'; } catch (Throwable) { return '—'; } }; ?>
<?php if (!empty($uploadMessage)): ?><div class="alert alert-info py-2" role="status"><?= htmlspecialchars((string) $uploadMessage) ?></div><?php endif; ?>
<div class="row g-3">
<?php foreach ($infos as $info): ?>
  <div class="col-12 col-md-6 col-xl-4">
    <article class="card h-100 shadow-sm">
      <?php if (str_starts_with((string) $info->file_mime, 'image/')): ?><a href="<?= htmlspecialchars(url_for('kundeninfos/' . (int) $info->id), ENT_QUOTES) ?>"><img class="card-img-top customer-info-thumb" src="<?= htmlspecialchars(url_for('kundeninfos/' . (int) $info->id . '/datei'), ENT_QUOTES) ?>" alt="<?= htmlspecialchars((string) $info->title) ?>" loading="lazy"></a><?php endif; ?>
      <div class="card-body">
        <?php if ($canManage): ?><form class="customer-info-title-form mb-2" hx-post="<?= htmlspecialchars(url_for('kunden/' . (int) $customer->id . '/infos/' . (int) $info->id . '/titel'), ENT_QUOTES) ?>" hx-target="#customer-info-list" hx-swap="innerHTML"><label class="visually-hidden" for="customer-info-title-<?= (int) $info->id ?>">Titel</label><div class="input-group input-group-sm"><input class="form-control fw-semibold" id="customer-info-title-<?= (int) $info->id ?>" name="title" value="<?= htmlspecialchars((string) $info->title) ?>" required><button class="btn btn-outline-primary" title="Titel speichern" aria-label="Titel speichern"><i class="fa-solid fa-check" aria-hidden="true"></i></button></div></form><?php else: ?><h2 class="h6 mb-2"><?= htmlspecialchars((string) $info->title) ?></h2><?php endif; ?>
        <p class="small text-body-secondary mb-2">Zuletzt aktualisiert: <?= htmlspecialchars($formatInfoDate($info->updated_at)) ?></p>
        <?php if (trim((string) $info->file_name) !== ''): ?><span class="badge text-bg-light border text-truncate mw-100"><i class="fa-solid fa-paperclip me-1" aria-hidden="true"></i><?= htmlspecialchars((string) $info->file_name) ?></span><?php endif; ?>
        <div class="mt-3 d-flex flex-wrap gap-2"><a class="btn btn-sm btn-outline-primary" href="<?= htmlspecialchars(url_for('kundeninfos/' . (int) $info->id), ENT_QUOTES) ?>">Öffnen</a><a class="btn btn-sm btn-outline-secondary" href="<?= htmlspecialchars(url_for('kunden/' . (int) $customer->id . '/infos/' . (int) $info->id . '/bearbeiten'), ENT_QUOTES) ?>">Bearbeiten</a><?php if ($canManage): ?><form class="d-inline" method="post" action="<?= htmlspecialchars(url_for('kunden/' . (int) $customer->id . '/infos/' . (int) $info->id . '/loeschen'), ENT_QUOTES) ?>" onsubmit="return confirm('Diese Kundeninfo wirklich löschen?');"><button class="btn btn-sm btn-outline-danger">Löschen</button></form><?php endif; ?></div>
      </div>
    </article>
  </div>
<?php endforeach; ?>
<?php if ($infos === []): ?><div class="col-12"><div class="alert alert-info">Für diesen Kunden sind noch keine Infos hinterlegt.</div></div><?php endif; ?>
</div>
<style>.customer-info-thumb{height:180px;object-fit:cover;background:var(--bs-tertiary-bg)}.customer-info-title-form input{min-width:0}</style>
