<?php
$media = $media ?? []; $canManageMedia = $canManageMedia ?? false;
$labels = ['condition' => 'Foto', 'defect' => 'Mangel', 'disposal' => 'Aussonderung', 'other' => 'Sonstiges'];
$titles = ['customer' => 'Kundenfotos', 'site' => 'Standortfotos', 'building' => 'Gebäudefotos', 'floor' => 'Etagenfotos', 'area' => 'Bereichsfotos', 'room' => 'Raumfotos'];
$panelId = 'structure-media-' . $type . '-' . (int) $entityId;
?>
<details class="border rounded-3 p-3 mt-3" id="<?= htmlspecialchars($panelId, ENT_QUOTES) ?>">
  <summary class="fw-semibold"><i class="fa-solid fa-camera me-2" aria-hidden="true"></i><?= htmlspecialchars($titles[$type] ?? 'Fotos') ?> <span class="small text-body-secondary fw-normal">optional</span></summary>
  <div class="pt-3">
    <?php if ($media !== []): ?><div class="d-flex flex-wrap gap-2 mb-3"><?php foreach ($media as $photo): ?><article class="border rounded p-1"><a href="<?= htmlspecialchars(url_for('struktur/fotos/' . (int) $photo['id']), ENT_QUOTES) ?>" target="_blank" rel="noopener"><img class="inspection-media-thumb rounded" src="<?= htmlspecialchars(url_for('struktur/fotos/' . (int) $photo['id']), ENT_QUOTES) ?>" alt="<?= htmlspecialchars((string) ($photo['caption'] ?: 'Strukturfoto'), ENT_QUOTES) ?>"></a><div class="small px-1"><?= htmlspecialchars($labels[(string) $photo['media_type']] ?? 'Foto') ?></div><?php if ($canManageMedia): ?><button class="btn btn-sm btn-danger m-1" type="button" hx-post="<?= htmlspecialchars(url_for('struktur/fotos/' . (int) $photo['id'] . '/loeschen'), ENT_QUOTES) ?>" hx-target="#<?= htmlspecialchars($panelId, ENT_QUOTES) ?>" hx-swap="outerHTML" hx-confirm="Foto wirklich löschen?"><i class="fa-solid fa-trash" aria-hidden="true"></i><span class="visually-hidden">Löschen</span></button><?php endif; ?></article><?php endforeach; ?></div><?php endif; ?>
    <?php if ($canManageMedia): ?><?= render_template('media_upload_component.php', ['componentId' => $panelId, 'action' => url_for('struktur/' . $type . '/' . (int) $entityId . '/fotos'), 'hxTarget' => '#' . $panelId]) ?><?php endif; ?>
  </div>
</details>
<style>.inspection-media-thumb{width:7rem;height:5rem;object-fit:cover;background:var(--bs-tertiary-bg)}</style>
