<?php /** @var array<int, array<string, mixed>> $notifications */ ?>
<section id="downloads-notifications" class="card shadow-sm mb-4">
  <div class="card-header d-flex justify-content-between align-items-center gap-2"><span class="fw-semibold"><i class="fa-solid fa-bell me-2" aria-hidden="true"></i>Benachrichtigungen</span><span class="badge text-bg-secondary"><?= count($notifications) ?></span></div>
  <div class="list-group list-group-flush">
    <?php foreach ($notifications as $notification): $unread = !empty($notification['notification_unread']); $severity = (string) ($notification['severity'] ?? 'info'); $icon = $severity === 'success' ? 'circle-check text-success' : ($severity === 'error' ? 'circle-exclamation text-danger' : ($severity === 'warning' ? 'triangle-exclamation text-warning' : 'circle-info text-info')); ?>
      <div class="list-group-item d-flex flex-wrap flex-lg-nowrap align-items-start gap-3<?= $unread ? ' bg-body-tertiary' : '' ?>">
        <i class="fa-solid fa-<?= $icon ?> mt-1" aria-hidden="true"></i>
        <div class="flex-grow-1"><div class="<?= $unread ? 'fw-semibold' : '' ?>"><?= htmlspecialchars((string) ($notification['title'] ?? 'Benachrichtigung')) ?></div><div class="small text-body-secondary"><?= htmlspecialchars((string) ($notification['message'] ?? '')) ?></div></div>
        <div class="d-flex flex-wrap gap-2 align-items-center"><?php if (trim((string) ($notification['action_url'] ?? '')) !== ''): ?><a class="btn btn-sm btn-secondary" href="<?= htmlspecialchars((string) $notification['action_url'], ENT_QUOTES) ?>"><i class="fa-solid fa-arrow-up-right-from-square me-1" aria-hidden="true"></i>Öffnen</a><?php endif; ?><?php if ($unread): ?><button class="btn btn-sm btn-secondary" type="button" hx-post="<?= htmlspecialchars(url_for('downloads/benachrichtigung/' . (int) $notification['notification_id'] . '/gelesen'), ENT_QUOTES) ?>" hx-target="#downloads-notifications" hx-swap="outerHTML"><i class="fa-solid fa-check me-1" aria-hidden="true"></i>Gelesen</button><?php else: ?><span class="small text-body-secondary"><i class="fa-solid fa-check-double me-1" aria-hidden="true"></i>Gelesen</span><?php endif; ?></div>
      </div>
    <?php endforeach; ?>
  </div>
</section>
