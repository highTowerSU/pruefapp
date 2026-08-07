<?php
$pending = array_values(array_filter($items, static fn(array $item): bool => ($item['status'] ?? '') === 'pending' && ($item['kind'] ?? '') === 'barcode'));
?>
<?php if ($pending === []): ?>
  <span class="small text-body-secondary">Noch keine neuen Scans vom verbundenen Smartphone.</span>
<?php else: ?>
  <div class="d-grid gap-1">
  <?php foreach ($pending as $item): ?>
    <button type="button" class="btn btn-sm btn-primary text-start" data-companion-choose="<?= (int) $item['id'] ?>" data-companion-target="<?= htmlspecialchars($field, ENT_QUOTES) ?>" data-companion-value="<?= htmlspecialchars((string) $item['value'], ENT_QUOTES) ?>"><i class="fa-solid fa-arrow-down me-1" aria-hidden="true"></i><?= htmlspecialchars((string) $item['value']) ?></button>
  <?php endforeach; ?>
  </div>
<?php endif; ?>
