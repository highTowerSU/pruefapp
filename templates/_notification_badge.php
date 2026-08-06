<?php /** @var int $unreadCount */ ?>
<span id="notifications-unread-badge"<?= !empty($oob) ? ' hx-swap-oob="outerHTML"' : '' ?> class="position-absolute top-0 start-100 translate-middle badge rounded-pill text-bg-danger<?= $unreadCount > 0 ? '' : ' d-none' ?>"><?= $unreadCount ?><span class="visually-hidden"> ungelesene Benachrichtigungen</span></span>
