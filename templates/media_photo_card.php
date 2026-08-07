<?php
/** @var array<string,mixed> $photo */
$photo = $photo ?? [];
$panelId = (string) ($panelId ?? 'media-panel');
$canManageMedia = !empty($canManageMedia);
$allowTypePlate = !empty($allowTypePlate);
$labels = ['type_plate' => 'Typenschild', 'condition' => 'Gerät', 'defect' => 'Mangel', 'disposal' => 'Aussonderung', 'other' => 'Sonstiges'];
$types = $allowTypePlate ? $labels : array_diff_key($labels, ['type_plate' => true]);
$id = (int) ($photo['id'] ?? 0);
$fileUrl = (string) ($fileUrl ?? url_for('geraete/fotos/' . $id));
$updateUrl = (string) ($updateUrl ?? url_for('geraete/fotos/' . $id . '/aktualisieren'));
$deleteUrl = (string) ($deleteUrl ?? url_for('geraete/fotos/' . $id . '/loeschen'));
?>
<article class="border rounded-3 p-2 h-100 media-photo-card">
  <a href="<?= htmlspecialchars($fileUrl, ENT_QUOTES) ?>" target="_blank" rel="noopener"><img class="img-fluid rounded media-thumb" src="<?= htmlspecialchars($fileUrl, ENT_QUOTES) ?>" alt="<?= htmlspecialchars((string) (($photo['caption'] ?? '') ?: ($labels[(string) ($photo['media_type'] ?? '')] ?? 'Foto')), ENT_QUOTES) ?>"></a>
  <?php if ($canManageMedia): ?><form class="mt-2" method="post" action="<?= htmlspecialchars($updateUrl, ENT_QUOTES) ?>" hx-post="<?= htmlspecialchars($updateUrl, ENT_QUOTES) ?>" hx-target="#<?= htmlspecialchars($panelId, ENT_QUOTES) ?>" hx-swap="outerHTML"><div class="row g-2"><div class="col-5"><label class="visually-hidden" for="media-type-<?= $id ?>">Fotoart</label><select class="form-select form-select-sm" id="media-type-<?= $id ?>" name="media_type"><?php foreach ($types as $value => $label): ?><option value="<?= htmlspecialchars($value, ENT_QUOTES) ?>"<?= (string) ($photo['media_type'] ?? '') === $value ? ' selected' : '' ?>><?= htmlspecialchars($label) ?></option><?php endforeach; ?></select></div><div class="col-7"><label class="visually-hidden" for="media-caption-<?= $id ?>">Bemerkung</label><input class="form-control form-control-sm" id="media-caption-<?= $id ?>" name="caption" maxlength="1000" value="<?= htmlspecialchars((string) ($photo['caption'] ?? ''), ENT_QUOTES) ?>" placeholder="Bemerkung"></div><div class="col-12 d-flex gap-2"><button class="btn btn-sm btn-primary" type="submit"><i class="fa-solid fa-floppy-disk me-1" aria-hidden="true"></i>Speichern</button><button class="btn btn-sm btn-danger" type="button" hx-post="<?= htmlspecialchars($deleteUrl, ENT_QUOTES) ?>" hx-target="#<?= htmlspecialchars($panelId, ENT_QUOTES) ?>" hx-swap="outerHTML" hx-confirm="Foto wirklich löschen?"><i class="fa-solid fa-trash me-1" aria-hidden="true"></i>Löschen</button></div></div></form><?php else: ?><div class="small fw-semibold mt-2"><i class="fa-solid fa-camera me-1" aria-hidden="true"></i><?= htmlspecialchars($labels[(string) ($photo['media_type'] ?? '')] ?? 'Foto') ?></div><?php if (trim((string) ($photo['caption'] ?? '')) !== ''): ?><div class="small text-body-secondary"><?= htmlspecialchars((string) $photo['caption']) ?></div><?php endif; ?><?php endif; ?>
</article>
