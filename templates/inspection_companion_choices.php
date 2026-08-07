<?php
$pending = array_values(array_filter($items, static fn(array $item): bool => ($item['status'] ?? '') === 'pending' && ($item['kind'] ?? '') === 'barcode'));
$used = array_values(array_filter($items, static fn(array $item): bool => ($item['status'] ?? '') !== 'pending' && ($item['kind'] ?? '') === 'barcode'));
?>
<?php if ($pending === [] && $used === []): ?>
  <span class="small text-body-secondary">Noch keine neuen Scans vom verbundenen Smartphone.</span>
<?php else: ?>
  <div class="d-grid gap-1">
  <?php foreach ($pending as $item): ?>
    <button type="button" class="btn btn-sm btn-primary text-start" data-companion-choose="<?= (int) $item['id'] ?>" data-companion-target="<?= htmlspecialchars($field, ENT_QUOTES) ?>" data-companion-value="<?= htmlspecialchars((string) $item['value'], ENT_QUOTES) ?>"><i class="fa-solid fa-arrow-down me-1" aria-hidden="true"></i><?= htmlspecialchars((string) $item['value']) ?></button>
  <?php endforeach; ?>
  <?php if ($used !== []): ?><div class="small text-body-secondary border-top pt-2 mt-1">Zuvor verwendet</div><?php endif; ?>
  <?php foreach (array_slice($used, 0, 12) as $item): ?>
    <button type="button" class="btn btn-sm btn-secondary text-start" data-companion-choose="<?= (int) $item['id'] ?>" data-companion-target="<?= htmlspecialchars($field, ENT_QUOTES) ?>" data-companion-value="<?= htmlspecialchars((string) $item['value'], ENT_QUOTES) ?>"><i class="fa-solid fa-clock-rotate-left me-1" aria-hidden="true"></i><?= htmlspecialchars((string) $item['value']) ?></button>
  <?php endforeach; ?>
  </div>
<?php endif; ?>
