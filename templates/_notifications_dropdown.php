<?php
/** @var array<int, array<string, mixed>> $notifications */
/** @var string $downloadsUrl */
$unreadNotifications = array_filter($notifications, static fn(array $entry): bool => !empty($entry['notification_unread']));
?>
<div id="notifications-dropdown-content" hx-get="<?= htmlspecialchars(url_for('downloads/benachrichtigungen'), ENT_QUOTES) ?>" hx-trigger="shown.bs.dropdown from:#notificationsDropdown" hx-swap="outerHTML">
  <button class="btn btn-outline-navbar position-relative" type="button" id="notificationsDropdown" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Benachrichtigungen" title="Benachrichtigungen">
    <i class="fa-solid fa-bell" aria-hidden="true"></i>
    <?php if ($unreadNotifications !== []): ?><span class="position-absolute top-0 start-100 translate-middle badge rounded-pill text-bg-danger"><?= count($unreadNotifications) ?><span class="visually-hidden"> ungelesene Benachrichtigungen</span></span><?php endif; ?>
  </button>
  <ul class="dropdown-menu dropdown-menu-end p-2 notification-menu" aria-labelledby="notificationsDropdown">
    <li class="dropdown-header d-flex justify-content-between align-items-center gap-2"><span><i class="fa-solid fa-bell me-1" aria-hidden="true"></i>Benachrichtigungen</span><span class="d-flex gap-2"><a class="small" href="<?= htmlspecialchars($downloadsUrl, ENT_QUOTES) ?>">Alle anzeigen</a><?php if ($unreadNotifications !== []): ?><button class="btn btn-link btn-sm p-0 small" type="button" hx-post="<?= htmlspecialchars(url_for('downloads/gelesen'), ENT_QUOTES) ?>" hx-target="#notifications-dropdown-content" hx-swap="outerHTML"><i class="fa-solid fa-check-double me-1" aria-hidden="true"></i>Alle gelesen</button><?php endif; ?></span></li>
    <?php if ($notifications === []): ?>
      <li><span class="dropdown-item-text small text-body-secondary">Keine aktuellen Benachrichtigungen.</span></li>
    <?php else: ?>
      <?php foreach ($notifications as $notification): ?>
        <?php $severity = (string) ($notification['severity'] ?? 'info'); $category = (string) ($notification['category'] ?? ''); $notificationIcon = $severity === 'success' ? 'fa-circle-check text-success' : ($severity === 'error' ? 'fa-circle-exclamation text-danger' : ($category === 'profile' ? 'fa-signature text-warning' : ($category === 'inspection' ? 'fa-clipboard-check text-warning' : ($severity === 'warning' ? 'fa-triangle-exclamation text-warning' : ($category === 'import' ? 'fa-file-import text-primary' : 'fa-circle-info text-info'))))); $notificationHref = trim((string) ($notification['action_url'] ?? '')) ?: $downloadsUrl; ?>
        <li class="d-flex align-items-start"><a class="dropdown-item small d-flex flex-grow-1 gap-2 align-items-start rounded-2<?= !empty($notification['notification_unread']) ? ' fw-semibold' : '' ?>" href="<?= htmlspecialchars($notificationHref, ENT_QUOTES) ?>"><i class="fa-solid <?= $notificationIcon ?> mt-1" aria-hidden="true"></i><span><strong><?= htmlspecialchars((string) ($notification['title'] ?? 'Benachrichtigung')) ?></strong><br><span class="text-body-secondary fw-normal"><?= htmlspecialchars((string) ($notification['message'] ?? '')) ?></span></span></a><?php if (!empty($notification['notification_unread'])): ?><button class="btn btn-sm btn-link text-body-secondary px-2 mt-1" type="button" title="Als gelesen markieren" aria-label="Als gelesen markieren" hx-post="<?= htmlspecialchars(url_for('downloads/benachrichtigung/' . (int) $notification['notification_id'] . '/gelesen'), ENT_QUOTES) ?>" hx-target="#notifications-dropdown-content" hx-swap="outerHTML"><i class="fa-solid fa-check" aria-hidden="true"></i></button><?php endif; ?></li>
      <?php endforeach; ?>
    <?php endif; ?>
  </ul>
</div>
