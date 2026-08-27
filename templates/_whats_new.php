<?php /** @var array<int,array<string,mixed>> $whatsNewEntries */ ?>
<?php $checked = $whatsNewChecked ?? []; $remaining = count(array_filter($whatsNewEntries, static fn(array $entry): bool => empty($checked[$entry['id'] ?? '']))); ?>
<section class="card shadow-sm mb-4" id="whats-new" data-action-nav="Was ist neu?" data-action-icon="fa-sparkles">
  <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2 fw-semibold"><span><i class="fa-solid fa-sparkles me-2" aria-hidden="true"></i>Was ist neu?</span><?php if ($remaining > 0): ?><button class="btn btn-sm btn-outline-light" type="button" hx-post="<?= htmlspecialchars(url_for('downloads/was-ist-neu/alle/erledigt'), ENT_QUOTES) ?>" hx-target="#whats-new" hx-swap="outerHTML"><i class="fa-solid fa-check-double me-1" aria-hidden="true"></i>Alle abhaken</button><?php endif; ?></div>
  <div class="card-body">
    <?php foreach ($whatsNewEntries as $entryIndex => $entry): $done = !empty($checked[$entry['id'] ?? '']); ?>
      <article class="<?= $done ? 'bg-body-tertiary text-body-secondary' : 'border border-warning border-2 bg-warning-subtle' ?> <?= $entryIndex !== array_key_last((array) $whatsNewEntries) ? 'mb-3' : 'mb-0' ?> p-3 rounded">
        <div class="d-flex flex-wrap justify-content-between align-items-baseline gap-2"><strong><?= htmlspecialchars((string) $entry['title']) ?></strong><span class="small text-body-secondary text-nowrap"><?= htmlspecialchars((string) $entry['date']) ?></span></div>
        <ul class="mb-2 mt-2"><?php foreach ((array) $entry['items'] as $item): ?><li><?= htmlspecialchars((string) $item) ?></li><?php endforeach; ?></ul>
        <?php if ($done): ?><span class="small"><i class="fa-solid fa-check-double me-1" aria-hidden="true"></i>Abgehakt</span><?php else: ?><button class="btn btn-sm btn-warning" type="button" hx-post="<?= htmlspecialchars(url_for('downloads/was-ist-neu/' . rawurlencode((string) $entry['id']) . '/erledigt'), ENT_QUOTES) ?>" hx-target="#whats-new" hx-swap="outerHTML"><i class="fa-solid fa-check me-1" aria-hidden="true"></i>Abhaken</button><?php endif; ?>
      </article>
    <?php endforeach; ?>
  </div>
</section>
