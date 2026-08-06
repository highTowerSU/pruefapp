<header class="page-header mb-4">
  <h1 class="mb-1"><i class="fa-solid fa-download me-2" aria-hidden="true"></i>Downloads</h1>
  <p class="mb-0 text-body-secondary"><?= !empty($canSeeAll) ? 'Alle Hintergrundaufgaben und fertigen Exporte' : 'Deine Hintergrundaufgaben und fertigen Exporte' ?></p>
</header>

<?php if (!empty($notifications)): ?><?= render_template('_notifications_list.php', ['notifications' => $notifications]) ?><?php endif; ?>

<section class="card shadow-sm">
  <div class="card-header d-flex justify-content-between align-items-center gap-2">
    <span class="fw-semibold"><i class="fa-solid fa-box-archive me-2" aria-hidden="true"></i>Export- und Hintergrundaufgaben</span>
    <div class="d-flex align-items-center gap-2"><span class="badge text-bg-secondary"><?= count($jobs) ?></span><form method="post" action="<?= htmlspecialchars(url_for('downloads/gelesen'), ENT_QUOTES) ?>"><button class="btn btn-sm btn-outline-secondary" type="submit"><i class="fa-solid fa-check-double me-1" aria-hidden="true"></i>Alle als gelesen markieren</button></form></div>
  </div>
  <div class="card-body p-0">
    <?php if ($jobs === []): ?>
      <div class="p-4 text-body-secondary"><i class="fa-solid fa-circle-info me-2" aria-hidden="true"></i>Noch keine Aufgaben oder Downloads vorhanden.</div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead><tr><th>Aufgabe</th><th>Status</th><th>Fortschritt</th><th>Erstellt</th><th>Aktion</th></tr></thead>
          <tbody>
          <?php foreach ($jobs as $job): ?>
            <?php
              $state = (string) ($job['state'] ?? '');
              $badge = $state === 'done' ? 'success' : (in_array($state, ['error', 'cancelled'], true) ? 'danger' : ($state === 'running' ? 'primary' : 'warning text-dark'));
              $created = '—';
              try { $created = (new DateTimeImmutable((string) ($job['created_at'] ?? '')))->format('d.m.Y H:i'); } catch (Throwable) {}
              $step = (int) ($job['step'] ?? 0);
              $total = (int) ($job['total'] ?? 0);
            ?>
            <tr>
              <td class="text-break"><span class="fw-semibold"><?= htmlspecialchars((string) ($job['type_label'] ?? 'Hintergrundaufgabe')) ?></span><?php if (!empty($job['historical'])): ?><span class="badge text-bg-secondary ms-1"><i class="fa-solid fa-clock-rotate-left me-1" aria-hidden="true"></i>Historie</span><?php endif; ?><br><span class="small text-body-secondary"><?= htmlspecialchars(substr((string) ($job['id'] ?? ''), 0, 12)) ?></span></td>
              <td><span class="badge text-bg-<?= $badge ?>"><?= htmlspecialchars((string) ($job['status_label'] ?? 'Unbekannt')) ?></span></td>
              <td><?= $total > 0 ? htmlspecialchars($step . ' von ' . $total) : '—' ?><?php if (!empty($job['current_device'])): ?><br><span class="small text-body-secondary"><?= htmlspecialchars((string) $job['current_device']) ?></span><?php endif; ?></td>
              <td class="text-nowrap"><?= htmlspecialchars($created) ?></td>
              <td class="text-break">
                <div class="d-flex flex-wrap gap-2 align-items-center">
                <?php if (!empty($job['download_url'])): ?>
                  <a class="btn btn-sm btn-success text-nowrap" href="<?= htmlspecialchars((string) $job['download_url'], ENT_QUOTES) ?>"><i class="fa-solid fa-download me-1" aria-hidden="true"></i>Herunterladen</a>
                <?php elseif ($state === 'queued' || $state === 'running'): ?>
                  <span class="small text-body-secondary"><?= htmlspecialchars((string) ($job['message'] ?? 'Wird im Hintergrund verarbeitet.')) ?></span>
                <?php else: ?>
                  <span class="small text-body-secondary"><?= htmlspecialchars((string) ($job['message'] ?? ($job['error'] ?? 'Kein Download verfügbar.'))) ?></span>
                <?php endif; ?>
                <?php if (!empty($job['notification_unread'])): ?><form method="post" action="<?= htmlspecialchars(url_for('downloads/' . rawurlencode((string) $job['id']) . '/gelesen'), ENT_QUOTES) ?>" class="mb-0"><button class="btn btn-sm btn-outline-secondary text-nowrap" type="submit"><i class="fa-solid fa-check me-1" aria-hidden="true"></i>Als gelesen markieren</button></form><?php else: ?><span class="small text-body-secondary text-nowrap"><i class="fa-solid fa-check-double me-1" aria-hidden="true"></i>Gelesen</span><?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</section>
