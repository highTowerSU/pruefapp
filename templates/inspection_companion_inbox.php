<?php
$pending = array_values(array_filter($items, static fn(array $item): bool => ($item['status'] ?? '') === 'pending'));
$used = array_values(array_filter($items, static fn(array $item): bool => ($item['status'] ?? '') !== 'pending'));
$hasActiveConnection = InspectionCompanionService::activeForUser((int) current_user()->id) !== [];
$label = static function (array $item): string {
    if (($item['kind'] ?? '') === 'barcode') return (string) $item['value'];
    return ((string) ($item['media_type'] ?? '') === 'type_plate' ? 'Typenschildfoto' : 'Companion-Foto') . ((string) ($item['caption'] ?? '') !== '' ? ' · ' . (string) $item['caption'] : '');
};
?>
<section class="card shadow-sm mb-3" id="companion-inbox" data-companion-inbox data-has-active-connection="<?= $hasActiveConnection ? '1' : '0' ?>" data-inbox-url="<?= htmlspecialchars(url_for('companion/eingang'), ENT_QUOTES) ?>" data-events-url="<?= htmlspecialchars(url_for('companion/ereignisse'), ENT_QUOTES) ?>">
  <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <strong><i class="fa-solid fa-mobile-screen-button me-2" aria-hidden="true"></i>Companion-Eingang</strong>
    <span class="badge text-bg-<?= $pending === [] ? 'secondary' : 'primary' ?>" data-companion-count><?= count($pending) ?></span>
  </div>
  <div class="card-body py-2">
    <?php if ($pending === []): ?>
      <span class="small text-body-secondary"><i class="fa-solid fa-circle-info me-1" aria-hidden="true"></i>Neue Scans und Fotos vom verbundenen Smartphone erscheinen hier.</span>
    <?php else: ?>
      <div class="list-group list-group-flush">
      <?php foreach ($pending as $item): ?>
        <div class="list-group-item px-0 d-flex flex-wrap align-items-center gap-2" data-companion-item data-item-id="<?= (int) $item['id'] ?>" data-item-kind="<?= htmlspecialchars((string) $item['kind'], ENT_QUOTES) ?>" data-item-value="<?= htmlspecialchars((string) $item['value'], ENT_QUOTES) ?>">
          <i class="fa-solid <?= ($item['kind'] ?? '') === 'barcode' ? 'fa-barcode' : 'fa-camera' ?> text-primary" aria-hidden="true"></i>
          <span class="flex-grow-1 text-break"><?= htmlspecialchars($label($item)) ?></span>
          <?php if (($item['kind'] ?? '') === 'photo'): ?><span class="badge text-bg-secondary">Foto</span><?php else: ?><span class="badge text-bg-primary">Scan</span><?php endif; ?>
        </div>
      <?php endforeach; ?>
      </div>
    <?php endif; ?>
    <?php if ($used !== []): ?><details class="mt-2"><summary class="small text-body-secondary">Bereits übernommen (<?= count($used) ?>)</summary><div class="small text-body-secondary mt-1"><?php foreach (array_slice($used, 0, 8) as $item): ?><div><?= htmlspecialchars($label($item)) ?></div><?php endforeach; ?></div></details><?php endif; ?>
  </div>
</section>
