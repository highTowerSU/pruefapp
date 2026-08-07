<?php
/** @var RedBeanPHPOODBBean $inspection */
$inspectionMedia = $inspectionMedia ?? [];
$canManageMedia = current_user_has_role('admin', 'editor');
?>
<section class="border rounded-3 p-3 mb-3" id="inspection-media-panel-<?= (int) $inspection->id ?>">
  <h2 class="h5"><i class="fa-solid fa-camera me-2" aria-hidden="true"></i>Fotodokumentation</h2>
  <p class="small text-body-secondary">Optional: Zustand, Mangel oder Aussonderung mit einem Foto dokumentieren.</p>
  <?php if ($inspectionMedia !== []): ?><div class="d-flex flex-wrap gap-2 mb-3"><?php foreach ($inspectionMedia as $photo): ?><a href="<?= htmlspecialchars(url_for('geraete/fotos/' . (int) $photo['id']), ENT_QUOTES) ?>" target="_blank" rel="noopener"><img class="inspection-media-thumb rounded border" src="<?= htmlspecialchars(url_for('geraete/fotos/' . (int) $photo['id']), ENT_QUOTES) ?>" alt="<?= htmlspecialchars((string) ($photo['caption'] ?: 'Prüfungsfoto'), ENT_QUOTES) ?>" title="<?= htmlspecialchars((string) ($photo['caption'] ?: 'Prüfungsfoto'), ENT_QUOTES) ?>"></a><?php endforeach; ?></div><?php endif; ?>
  <?php if ($canManageMedia): ?><?= render_template('media_upload_component.php', ['componentId' => 'inspection-photo-' . (int) $inspection->id, 'action' => url_for('pruefungen/' . (int) $inspection->id . '/fotos'), 'hxTarget' => '#inspection-media-panel-' . (int) $inspection->id, 'allowTypePlate' => false]) ?><?php endif; ?>
</section>
<style>.inspection-media-thumb{width:7rem;height:5rem;object-fit:cover;background:var(--bs-tertiary-bg)}</style>
