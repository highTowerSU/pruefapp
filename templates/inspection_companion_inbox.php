<?php
$pending = array_values(array_filter($items, static fn(array $item): bool => ($item['status'] ?? '') === 'pending'));
$used = array_values(array_filter($items, static fn(array $item): bool => ($item['status'] ?? '') !== 'pending'));
$hasActiveConnection = InspectionCompanionService::activeForUser((int) current_user()->id) !== [];
$label = static function (array $item): string {
    if (($item['kind'] ?? '') === 'barcode') return (string) $item['value'];
    return ((string) ($item['media_type'] ?? '') === 'type_plate' ? 'Typenschildfoto' : 'Companion-Foto') . ((string) ($item['caption'] ?? '') !== '' ? ' · ' . (string) $item['caption'] : '');
};
$photoUrl = static fn(array $item): string => url_for('companion/eingang/' . (int) $item['id'] . '/foto');
?>
<details class="companion-inbox shadow-sm" id="companion-inbox" data-companion-inbox data-has-active-connection="<?= $hasActiveConnection ? '1' : '0' ?>" data-inbox-url="<?= htmlspecialchars(url_for('companion/eingang'), ENT_QUOTES) ?>" data-events-url="<?= htmlspecialchars(url_for('companion/ereignisse'), ENT_QUOTES) ?>">
  <summary class="companion-inbox-toggle">
    <span><i class="fa-solid fa-paperclip me-2" aria-hidden="true"></i><span class="companion-inbox-title">Companion-Eingang</span></span>
    <span class="badge text-bg-<?= $pending === [] ? 'secondary' : 'primary' ?>" data-companion-count><?= count($pending) ?></span>
  </summary>
  <div class="companion-inbox-body">
    <?php if ($pending === []): ?>
      <span class="small text-body-secondary"><i class="fa-solid fa-circle-info me-1" aria-hidden="true"></i>Neue Scans und Fotos vom verbundenen Smartphone erscheinen hier.</span>
    <?php else: ?>
      <div class="list-group list-group-flush">
      <?php foreach ($pending as $item): ?>
        <div class="list-group-item px-0 d-flex flex-wrap align-items-center gap-2" data-companion-item data-item-id="<?= (int) $item['id'] ?>" data-item-kind="<?= htmlspecialchars((string) $item['kind'], ENT_QUOTES) ?>" data-item-value="<?= htmlspecialchars((string) $item['value'], ENT_QUOTES) ?>">
          <i class="fa-solid <?= ($item['kind'] ?? '') === 'barcode' ? 'fa-barcode' : 'fa-camera' ?> text-primary" aria-hidden="true"></i>
          <?php if (($item['kind'] ?? '') === 'barcode'): ?>
            <button type="button" class="btn btn-link text-start p-0 flex-grow-1 text-break" data-companion-copy="<?= htmlspecialchars((string) $item['value'], ENT_QUOTES) ?>" title="In die Zwischenablage kopieren"><?= htmlspecialchars($label($item)) ?></button>
            <span class="badge text-bg-primary">Scan</span>
          <?php else: ?>
            <button type="button" class="btn btn-link text-start p-0 flex-grow-1 text-break d-flex align-items-center gap-2" data-companion-copy-photo="<?= (int) $item['id'] ?>" title="Bild in die Zwischenablage kopieren"><img class="companion-photo-thumb" src="<?= htmlspecialchars($photoUrl($item), ENT_QUOTES) ?>" alt="Vorschau"><span><?= htmlspecialchars($label($item)) ?></span></button><span class="badge text-bg-secondary">Foto</span>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
      </div>
    <?php endif; ?>
    <?php if ($used !== []): ?><details class="mt-2"><summary class="small text-body-secondary">Zuvor verwendet (<?= count($used) ?>)</summary><div class="small text-body-secondary mt-1 d-grid gap-1"><?php foreach (array_slice($used, 0, 12) as $item): ?><?php if (($item['kind'] ?? '') === 'barcode'): ?><button type="button" class="btn btn-sm btn-secondary text-start" data-companion-copy="<?= htmlspecialchars((string) $item['value'], ENT_QUOTES) ?>"><i class="fa-solid fa-clock-rotate-left me-1" aria-hidden="true"></i><?= htmlspecialchars($label($item)) ?></button><?php else: ?><button type="button" class="btn btn-sm btn-secondary text-start d-flex align-items-center gap-2" data-companion-copy-photo="<?= (int) $item['id'] ?>"><img class="companion-photo-thumb" src="<?= htmlspecialchars($photoUrl($item), ENT_QUOTES) ?>" alt="Vorschau"><span><?= htmlspecialchars($label($item)) ?></span></button><?php endif; ?><?php endforeach; ?></div></details><?php endif; ?>
  </div>
</details>
<style>
.companion-inbox{border:1px solid var(--bs-border-color);border-radius:.75rem;background:var(--bs-body-bg);margin-bottom:1rem;overflow:hidden}.companion-inbox-toggle{cursor:pointer;display:flex;align-items:center;justify-content:space-between;gap:.75rem;padding:.7rem .9rem;font-weight:600;list-style:none}.companion-inbox-toggle::-webkit-details-marker{display:none}.companion-inbox-body{padding:.35rem .9rem .8rem}.companion-inbox [data-companion-copy]{text-decoration:none}.companion-inbox [data-companion-copy]:hover{text-decoration:underline}.companion-photo-thumb{width:42px;height:32px;object-fit:cover;border:1px solid var(--bs-border-color);border-radius:.25rem;background:var(--bs-tertiary-bg);flex:0 0 auto}@media(min-width:992px){.companion-inbox{position:fixed;right:1rem;bottom:1rem;width:min(24rem,calc(100vw - 2rem));z-index:1030;margin:0;box-shadow:0 .5rem 1rem rgba(0,0,0,.18)!important}.companion-inbox:not([open]){width:auto;min-width:0}.companion-inbox:not([open]) .companion-inbox-title{display:none}.companion-inbox:not([open]) .companion-inbox-toggle{padding:.7rem .8rem}.companion-inbox[open] .companion-inbox-toggle{border-bottom:1px solid var(--bs-border-color)}}
</style>
