<?php
$pending = array_values(array_filter($items, static fn(array $item): bool => ($item['status'] ?? '') === 'pending' && ($item['kind'] ?? '') === 'photo'));
$used = array_values(array_filter($items, static fn(array $item): bool => ($item['status'] ?? '') !== 'pending' && ($item['kind'] ?? '') === 'photo'));
$labels = ['type_plate' => 'Typenschild', 'condition' => 'Gerät', 'defect' => 'Mangel', 'disposal' => 'Aussonderung', 'other' => 'Sonstiges'];
$photoUrl = static fn(array $item): string => url_for('companion/eingang/' . (int) $item['id'] . '/foto');
?>
<?php if ($pending === [] && $used === []): ?>
  <span class="small text-body-secondary">Noch keine Fotos vom verbundenen Smartphone.</span>
<?php else: ?>
  <div class="d-grid gap-1">
  <?php foreach ($pending as $item): ?>
    <button type="button" class="btn btn-sm btn-primary text-start d-flex align-items-center gap-2" data-companion-draft-photo-choose="<?= (int) $item['id'] ?>"><img class="rounded border bg-light" style="width:42px;height:32px;object-fit:cover" src="<?= htmlspecialchars($photoUrl($item), ENT_QUOTES) ?>" alt="Vorschau"><span><i class="fa-solid fa-camera me-1" aria-hidden="true"></i><?= htmlspecialchars($labels[(string) ($item['media_type'] ?? '')] ?? 'Foto') ?><?= trim((string) ($item['caption'] ?? '')) !== '' ? ' · ' . htmlspecialchars((string) $item['caption']) : '' ?></span></button>
  <?php endforeach; ?>
  <?php if ($used !== []): ?><div class="small text-body-secondary border-top pt-2 mt-1">Zuvor verwendet</div><?php endif; ?>
  <?php foreach (array_slice($used, 0, 12) as $item): ?>
    <button type="button" class="btn btn-sm btn-secondary text-start d-flex align-items-center gap-2" data-companion-draft-photo-choose="<?= (int) $item['id'] ?>"><img class="rounded border bg-light" style="width:42px;height:32px;object-fit:cover" src="<?= htmlspecialchars($photoUrl($item), ENT_QUOTES) ?>" alt="Vorschau"><span><i class="fa-solid fa-clock-rotate-left me-1" aria-hidden="true"></i><?= htmlspecialchars($labels[(string) ($item['media_type'] ?? '')] ?? 'Foto') ?><?= trim((string) ($item['caption'] ?? '')) !== '' ? ' · ' . htmlspecialchars((string) $item['caption']) : '' ?></span></button>
  <?php endforeach; ?>
  </div>
<?php endif; ?>
