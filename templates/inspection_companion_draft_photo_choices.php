<?php
$pending = array_values(array_filter($items, static fn(array $item): bool => ($item['status'] ?? '') === 'pending' && ($item['kind'] ?? '') === 'photo'));
$labels = ['type_plate' => 'Typenschild', 'condition' => 'Gerät', 'defect' => 'Mangel', 'disposal' => 'Aussonderung', 'other' => 'Sonstiges'];
?>
<?php if ($pending === []): ?>
  <span class="small text-body-secondary">Noch keine Fotos vom verbundenen Smartphone.</span>
<?php else: ?>
  <div class="d-grid gap-1">
  <?php foreach ($pending as $item): ?>
    <button type="button" class="btn btn-sm btn-primary text-start" data-companion-draft-photo-choose="<?= (int) $item['id'] ?>"><i class="fa-solid fa-camera me-1" aria-hidden="true"></i><?= htmlspecialchars($labels[(string) ($item['media_type'] ?? '')] ?? 'Foto') ?><?= trim((string) ($item['caption'] ?? '')) !== '' ? ' · ' . htmlspecialchars((string) $item['caption']) : '' ?></button>
  <?php endforeach; ?>
  </div>
<?php endif; ?>
