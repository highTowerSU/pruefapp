<?php
/** @var array<int, array<string, mixed>> $entries */
/** @var array<string, mixed> $pagination */
/** @var array<int, array<string, mixed>> $revisions */

$pagination = $pagination ?? [
    'total_entries' => count($entries),
    'first_item' => count($entries) > 0 ? 1 : 0,
    'last_item' => count($entries),
    'page' => 1,
    'total_pages' => 1,
    'has_previous' => false,
    'has_next' => false,
    'previous_page' => null,
    'next_page' => null,
];

$baseAuditUrl = url_for('admin/audit-log');
$previousUrl = $pagination['has_previous']
    ? ($pagination['previous_page'] === 1
        ? $baseAuditUrl
        : url_for('admin/audit-log?page=' . (string) $pagination['previous_page']))
    : '#';
$nextUrl = $pagination['has_next']
    ? url_for('admin/audit-log?page=' . (string) $pagination['next_page'])
    : '#';
?>

<div class="alert alert-info">
    Das Ereignisprotokoll zeigt fachliche Aktionen. Die Datenrevisionen darunter
    werden direkt aus den von ReBean erzeugten Revisionstabellen gelesen.
</div>

<details class="card mb-4">
<summary class="card-header fw-semibold d-flex justify-content-between align-items-center"><span>Ereignisprotokoll</span><span class="badge text-bg-primary"><?= (int) ($pagination['total_entries'] ?? count($entries)) ?></span></summary>
<div class="card-body">
<?php if (empty($entries)): ?>
    <p class="text-body-secondary">Es wurden noch keine Aktionen protokolliert.</p>
<?php else: ?>
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
        <div class="text-body-secondary small">
            Einträge <?= (int) $pagination['first_item'] ?>–<?= (int) $pagination['last_item'] ?> von <?= (int) $pagination['total_entries'] ?>
        </div>
        <nav aria-label="Paginierung des Ereignisprotokolls">
            <ul class="pagination pagination-sm mb-0">
                <li class="page-item<?= $pagination['has_previous'] ? '' : ' disabled' ?>">
                    <a
                        class="page-link"
                        href="<?= htmlspecialchars($previousUrl, ENT_QUOTES) ?>"
                        aria-label="Vorherige Seite"
                        <?= $pagination['has_previous'] ? '' : 'tabindex="-1" aria-disabled="true"' ?>
                    >
                        <span aria-hidden="true">&laquo;</span>
                    </a>
                </li>
                <li class="page-item active" aria-current="page">
                    <span class="page-link">
                        Seite <?= (int) $pagination['page'] ?> von <?= (int) $pagination['total_pages'] ?>
                    </span>
                </li>
                <li class="page-item<?= $pagination['has_next'] ? '' : ' disabled' ?>">
                    <a
                        class="page-link"
                        href="<?= htmlspecialchars($nextUrl, ENT_QUOTES) ?>"
                        aria-label="Nächste Seite"
                        <?= $pagination['has_next'] ? '' : 'tabindex="-1" aria-disabled="true"' ?>
                    >
                        <span aria-hidden="true">&raquo;</span>
                    </a>
                </li>
            </ul>
        </nav>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
            <tr>
                <th scope="col" class="text-nowrap">Zeitpunkt</th>
                <th scope="col" class="text-nowrap">Benutzer</th>
                <th scope="col" class="text-nowrap">Aktion</th>
                <th scope="col">Details</th>
                <th scope="col" class="text-nowrap">IP-Adresse</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($entries as $entry): ?>
                <tr>
                    <td class="text-nowrap">
                        <?php if ($entry['zeitpunkt'] instanceof DateTimeImmutable): ?>
                            <?= htmlspecialchars($entry['zeitpunkt']->format('d.m.Y H:i:s')) ?>
                        <?php elseif (!empty($entry['zeitpunkt_roh'])): ?>
                            <?= htmlspecialchars($entry['zeitpunkt_roh']) ?>
                        <?php else: ?>
                            <span class="text-body-secondary">–</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (!empty($entry['anzeige_name'])): ?>
                            <div><?= htmlspecialchars($entry['anzeige_name']) ?></div>
                        <?php endif; ?>
                        <?php if (!empty($entry['nutzername']) && $entry['nutzername'] !== ($entry['anzeige_name'] ?? '')): ?>
                            <div class="small text-body-secondary">(<?= htmlspecialchars($entry['nutzername']) ?>)</div>
                        <?php elseif (empty($entry['anzeige_name']) && !empty($entry['nutzername'])): ?>
                            <?= htmlspecialchars($entry['nutzername']) ?>
                        <?php elseif (empty($entry['anzeige_name']) && empty($entry['nutzername'])): ?>
                            <span class="text-body-secondary">Unbekannt</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-nowrap">
                        <?php $action = strtolower((string) $entry['aktion']); $actionClass = str_contains($action, 'lösch') || str_contains($action, 'delete') ? 'danger' : (str_contains($action, 'neu') || str_contains($action, 'create') ? 'success' : (str_contains($action, 'änder') || str_contains($action, 'update') ? 'warning text-dark' : 'secondary')); ?>
                        <span class="badge text-bg-<?= $actionClass ?>"><?= htmlspecialchars($entry['aktion']) ?></span>
                    </td>
                    <td class="text-break">
                        <?php
                        $renderDetail = static function ($value) use (&$renderDetail) {
                            if (is_array($value)) {
                                if ($value === []) {
                                    echo '<span class="text-body-secondary">–</span>';
                                    return;
                                }

                                $collapse = count($value) > 5;
                                if ($collapse) echo '<details class="border rounded p-2"><summary class="small">' . count($value) . ' Einträge anzeigen</summary>';
                                echo '<ul class="list-unstyled mb-0' . ($collapse ? ' mt-2' : '') . '">';
                                foreach ($value as $key => $item) {
                                    echo '<li class="mb-2">';
                                    echo '<div class="small fw-semibold text-body-secondary">' . htmlspecialchars((string) $key) . '</div>';
                                    echo '<div class="ms-3">';
                                    $renderDetail($item);
                                    echo '</div>';
                                    echo '</li>';
                                }
                                echo '</ul>';
                                if ($collapse) echo '</details>';
                                return;
                            }

                            if ($value === null || $value === '') {
                                echo '<span class="text-body-secondary">–</span>';
                                return;
                            }

                            if (is_bool($value)) {
                                echo $value ? 'Ja' : 'Nein';
                                return;
                            }

                            echo '<span class="text-break">' . htmlspecialchars((string) $value) . '</span>';
                        };

                        $details = $entry['details'];
                        if (!is_array($details) || $details === []): ?>
                            <span class="text-body-secondary">Keine weiteren Details</span>
                        <?php else: ?>
                            <details>
                                <summary class="small text-primary">Details anzeigen</summary>
                                <div class="mt-2"><?php $renderDetail($details); ?></div>
                            </details>
                        <?php endif; ?>
                    </td>
                    <td class="text-nowrap">
                        <?= $entry['ip_adresse'] !== '' ? htmlspecialchars($entry['ip_adresse']) : '<span class="text-body-secondary">–</span>' ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
</div>
</details>

<details class="card mb-4">
<summary class="card-header fw-semibold d-flex justify-content-between align-items-center"><span>Prüfapp-Cron</span><span class="badge text-bg-info"><?= (int) ($cronTotal ?? count($cronLog)) ?></span></summary>
<div class="card-body">
<p class="text-body-secondary">Letzte Cron-Läufe, Berichtserzeugung und Hintergrundjobs.</p>
<?php if (empty($cronHealthy)): ?><div class="alert alert-warning d-flex align-items-start gap-2" role="alert"><i class="fa-solid fa-triangle-exclamation mt-1" aria-hidden="true"></i><div><strong>Prüfapp-Cron läuft aktuell nicht zuverlässig.</strong><br><span><?php if (($cronAge ?? null) === null): ?>Es wurde noch kein aktueller Cron-Heartbeat gefunden.<?php else: ?>Der letzte Lauf ist <?= htmlspecialchars((string) max(1, (int) floor(((int) $cronAge) / 60))) ?> Minuten alt. Erwartet wird ein Lauf innerhalb von fünf Minuten.<?php endif; ?> Bitte Cron-Dienst und `/var/www/html/pruefapp/bin/cron.php` prüfen.</span></div></div><?php else: ?><div class="alert alert-success py-2" role="status"><i class="fa-solid fa-circle-check me-1" aria-hidden="true"></i>Cron aktiv, letzter Heartbeat vor <?= htmlspecialchars((string) max(0, (int) floor(((int) $cronAge) / 60))) ?> Minuten.</div><?php endif; ?>
<?php if (!empty($cronPendingJobs)): ?><div class="card border-info mb-3"><div class="card-header py-2 d-flex justify-content-between align-items-center"><strong><i class="fa-solid fa-list-check me-1" aria-hidden="true"></i>Ausstehende Cron-Todos</strong><span class="badge text-bg-info"><?= count($cronPendingJobs) ?></span></div><div class="table-responsive"><table class="table table-sm mb-0 align-middle"><thead><tr><th>Job</th><th>Status</th><th>Fortschritt</th><th>Gerät</th><th>Meldung</th></tr></thead><tbody><?php foreach ($cronPendingJobs as $job): $jobType = (string) ($job['type'] ?? 'Hintergrundjob'); $step = (int) ($job['step'] ?? 0); $total = (int) ($job['total'] ?? 0); ?><tr><td class="text-break"><code><?= htmlspecialchars(substr((string) ($job['id'] ?? ''), 0, 12)) ?></code><br><span class="small text-body-secondary"><?= htmlspecialchars($jobType) ?></span></td><td><span class="badge text-bg-<?= ($job['state'] ?? '') === 'running' ? 'primary' : 'secondary' ?>"><?= htmlspecialchars((string) ($job['state'] ?? 'unbekannt')) ?></span></td><td><?= $total > 0 ? htmlspecialchars($step . ' / ' . $total) : '—' ?></td><td><?= htmlspecialchars((string) ($job['current_device'] ?? '—')) ?></td><td class="text-break"><?= htmlspecialchars((string) ($job['message'] ?? 'Wartet auf den nächsten Cron-Lauf.')) ?></td></tr><?php endforeach; ?></tbody></table></div></div><?php else: ?><p class="small text-body-secondary"><i class="fa-solid fa-check me-1" aria-hidden="true"></i>Keine ausstehenden Cron-Todos.</p><?php endif; ?>
<?php if (empty($cronLog)): ?>
    <div class="alert alert-warning">Noch kein Prüfapp-Cron-Lauf protokolliert.</div>
<?php else: ?>
    <div class="table-responsive"><table class="table table-sm align-middle"><thead><tr><th>Zeit</th><th>Status</th><th>Meldung</th></tr></thead><tbody><?php foreach ($cronLog as $line): $message = (string) ($line['message'] ?? ''); $level = strtolower((string) ($line['level'] ?? 'info')); $levelClass = $level === 'error' ? 'danger' : ($level === 'warning' ? 'warning text-dark' : 'info'); ?><tr><td class="text-nowrap"><?= htmlspecialchars((new DateTimeImmutable((string) $line['run_at']))->format('d.m.Y H:i:s') ) ?></td><td><span class="badge text-bg-<?= $levelClass ?>"><?= htmlspecialchars(strtoupper($level)) ?></span></td><td class="text-break"><?php if (mb_strlen($message) > 220): ?><details><summary><?= htmlspecialchars(mb_substr($message, 0, 220) . ' …') ?></summary><pre class="small mt-2 mb-0 text-wrap"><?= htmlspecialchars($message) ?></pre></details><?php else: ?><?= htmlspecialchars($message) ?><?php endif; ?></td></tr><?php endforeach; ?></tbody></table></div>
    <?php if (($cronPages ?? 1) > 1): ?><nav aria-label="Cron-Log-Seiten"><ul class="pagination pagination-sm"><?php for ($p = 1; $p <= $cronPages; $p++): ?><li class="page-item<?= $p === $cronPage ? ' active' : '' ?>"><a class="page-link" href="<?= htmlspecialchars(url_for('admin/audit-log?cron_page=' . $p), ENT_QUOTES) ?>"><?= $p ?></a></li><?php endfor; ?></ul></nav><?php endif; ?>
<?php endif; ?>
</div>
</details>

<details class="card mb-4">
<summary class="card-header fw-semibold d-flex justify-content-between align-items-center"><span>Datenrevisionen (ReBean)</span><span class="badge text-bg-secondary"><?= (int) ($revisionTotal ?? count($revisions)) ?></span></summary>
<div class="card-body">
<?php if (empty($revisions)): ?>
    <p class="text-body-secondary">Es wurden noch keine Datenänderungen revisioniert.</p>
<?php else: ?>
    <div class="small text-body-secondary mb-2">Einträge <?= $revisionTotal > 0 ? (($revisionPage - 1) * 50 + 1) : 0 ?>–<?= min($revisionPage * 50, $revisionTotal) ?> von <?= (int) $revisionTotal ?></div>
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
            <tr>
                <th>Zeitpunkt</th>
                <th>Tabelle</th>
                <th>Aktion</th>
                <th>Datensatz</th>
                <th>Gespeicherter Stand</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($revisions as $revision): ?>
                <tr>
                    <td class="text-nowrap"><?= htmlspecialchars((string) $revision['timestamp']) ?></td>
                    <td><code><?= htmlspecialchars((string) $revision['table']) ?></code></td>
                    <td><?php $revisionAction = strtolower((string) $revision['action']); $revisionClass = str_contains($revisionAction, 'delete') || str_contains($revisionAction, 'lösch') ? 'danger' : (str_contains($revisionAction, 'create') || str_contains($revisionAction, 'neu') ? 'success' : 'warning text-dark'); ?><span class="badge text-bg-<?= $revisionClass ?>"><?= htmlspecialchars((string) $revision['action']) ?></span></td>
                    <td>#<?= (int) $revision['original_id'] ?></td>
                    <td>
                        <details>
                            <summary>Details anzeigen</summary>
                            <pre class="small mb-0 mt-2 text-wrap"><?= htmlspecialchars(
                                (string) json_encode(
                                    $revision['snapshot'],
                                    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                                )
                            ) ?></pre>
                        </details>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if (($revisionPages ?? 1) > 1): ?><nav aria-label="Paginierung der Datenrevisionen"><ul class="pagination pagination-sm mt-3"><?php for ($p = 1; $p <= $revisionPages; $p++): ?><li class="page-item<?= $p === $revisionPage ? ' active' : '' ?>"><a class="page-link" href="<?= htmlspecialchars(url_for('admin/audit-log?revision_page=' . $p), ENT_QUOTES) ?>"><?= $p ?></a></li><?php endfor; ?></ul></nav><?php endif; ?>
<?php endif; ?>
</div>
</details>
