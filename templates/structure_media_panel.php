<?php
$media = $media ?? []; $canManageMedia = $canManageMedia ?? false;
$labels = ['condition' => 'Foto', 'defect' => 'Mangel', 'disposal' => 'Aussonderung', 'other' => 'Sonstiges'];
$titles = ['customer' => 'Kundenfotos', 'site' => 'Standortfotos', 'building' => 'Gebäudefotos', 'floor' => 'Etagenfotos', 'area' => 'Bereichsfotos', 'room' => 'Raumfotos'];
$panelId = 'structure-media-' . $type . '-' . (int) $entityId;
?>
<details class="border rounded-3 p-3 mt-3" id="<?= htmlspecialchars($panelId, ENT_QUOTES) ?>">
  <summary class="fw-semibold"><i class="fa-solid fa-camera me-2" aria-hidden="true"></i><?= htmlspecialchars($titles[$type] ?? 'Fotos') ?> <span class="small text-body-secondary fw-normal">optional</span></summary>
  <div class="pt-3">
    <?php if ($media !== []): ?><div class="row g-3 mb-3"><?php foreach ($media as $photo): ?><div class="col-12 col-md-6 col-xl-4"><?= render_template('media_photo_card.php', ['photo' => $photo, 'panelId' => $panelId, 'canManageMedia' => $canManageMedia, 'allowTypePlate' => false, 'fileUrl' => url_for('struktur/fotos/' . (int) $photo['id']), 'updateUrl' => url_for('struktur/fotos/' . (int) $photo['id'] . '/aktualisieren'), 'deleteUrl' => url_for('struktur/fotos/' . (int) $photo['id'] . '/loeschen')]) ?></div><?php endforeach; ?></div><?php endif; ?>
    <?php if ($canManageMedia): ?><?= render_template('media_upload_component.php', ['componentId' => $panelId, 'action' => url_for('struktur/' . $type . '/' . (int) $entityId . '/fotos'), 'hxTarget' => '#' . $panelId, 'contextHint' => 'Bild und Bemerkung werden diesem Struktureintrag zugeordnet.']) ?><?php endif; ?>
  </div>
</details>
<style>.inspection-media-thumb{width:7rem;height:5rem;object-fit:cover;background:var(--bs-tertiary-bg)}</style>
