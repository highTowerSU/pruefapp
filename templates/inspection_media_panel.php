<?php
/** @var RedBeanPHPOODBBean $inspection */
$inspectionMedia = $inspectionMedia ?? [];
$canManageMedia = current_user_has_role('admin', 'editor');
$panelId = 'inspection-media-panel-' . (int) $inspection->id;
?>
<section class="border rounded-3 p-3 mb-3" id="<?= $panelId ?>">
  <h2 class="h5"><i class="fa-solid fa-camera me-2" aria-hidden="true"></i>Fotodokumentation</h2>
  <p class="small text-body-secondary">Optional: Zustand, Mangel oder Aussonderung dokumentieren. Fotoart und Bemerkung können danach direkt bearbeitet werden.</p>
  <?php if ($inspectionMedia !== []): ?><div class="row g-3 mb-3"><?php foreach ($inspectionMedia as $photo): ?><div class="col-12 col-md-6 col-xl-4"><?= render_template('media_photo_card.php', ['photo' => $photo, 'panelId' => $panelId, 'canManageMedia' => $canManageMedia, 'allowTypePlate' => false]) ?></div><?php endforeach; ?></div><?php endif; ?>
  <?php if ($canManageMedia): ?><?= render_template('media_upload_component.php', ['componentId' => 'inspection-photo-' . (int) $inspection->id, 'action' => url_for('pruefungen/' . (int) $inspection->id . '/fotos'), 'hxTarget' => '#' . $panelId, 'allowTypePlate' => false, 'contextHint' => 'Bild und Bemerkung werden dieser Prüfung zugeordnet.']) ?><?php endif; ?>
</section>
<style>.media-thumb{width:100%;height:10rem;object-fit:cover;background:var(--bs-tertiary-bg)}</style>
