<?php
/** @var RedBeanPHPOODBBean $inspection */
$inspectionMedia = $inspectionMedia ?? [];
?>
<section class="border rounded-3 p-3 mb-3">
  <h2 class="h5"><i class="fa-solid fa-camera me-2" aria-hidden="true"></i>Fotodokumentation</h2>
  <p class="small text-body-secondary">Optional: Zustand, Mangel oder Aussonderung mit einem Foto dokumentieren.</p>
  <?php if ($inspectionMedia !== []): ?><div class="d-flex flex-wrap gap-2 mb-3"><?php foreach ($inspectionMedia as $photo): ?><a href="<?= htmlspecialchars(url_for('geraete/fotos/' . (int) $photo['id']), ENT_QUOTES) ?>" target="_blank" rel="noopener"><img class="inspection-media-thumb rounded border" src="<?= htmlspecialchars(url_for('geraete/fotos/' . (int) $photo['id']), ENT_QUOTES) ?>" alt="<?= htmlspecialchars((string) ($photo['caption'] ?: 'Prüfungsfoto'), ENT_QUOTES) ?>" title="<?= htmlspecialchars((string) ($photo['caption'] ?: 'Prüfungsfoto'), ENT_QUOTES) ?>"></a><?php endforeach; ?></div><?php endif; ?>
  <form method="post" action="<?= htmlspecialchars(url_for('pruefungen/' . (int) $inspection->id . '/fotos'), ENT_QUOTES) ?>" enctype="multipart/form-data" class="row g-2 align-items-end"><div class="col-md-5"><label class="form-label">Foto</label><input class="form-control" name="photo" type="file" accept="image/jpeg,image/png,image/webp" capture="environment" required></div><div class="col-md-3"><label class="form-label">Art</label><select class="form-select" name="media_type"><option value="condition">Zustand</option><option value="defect">Mangel</option><option value="disposal">Aussonderung</option><option value="other">Sonstiges</option></select></div><div class="col-md-3"><label class="form-label">Bemerkung</label><input class="form-control" name="caption" maxlength="1000" placeholder="optional"></div><div class="col-md-1"><button class="btn btn-secondary w-100" title="Foto speichern"><i class="fa-solid fa-upload" aria-hidden="true"></i><span class="visually-hidden">Speichern</span></button></div></form>
</section>
<style>.inspection-media-thumb{width:7rem;height:5rem;object-fit:cover;background:var(--bs-tertiary-bg)}</style>
