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

$auditFilters = $auditFilters ?? [];
$cronRunFilters = $cronRunFilters ?? [];
$auditUrl = static function (array $changes = []) use ($auditFilters): string {
    $query = array_merge($auditFilters, $changes);
    $query = array_filter($query, static fn($value): bool => $value !== '' && $value !== null && $value !== 1 && $value !== '1');
    return url_for('admin/audit-log' . ($query !== [] ? '?' . http_build_query($query) : ''));
};
$previousUrl = $pagination['has_previous'] ? $auditUrl(['page' => $pagination['previous_page']]) : '#';
$nextUrl = $pagination['has_next'] ? $auditUrl(['page' => $pagination['next_page']]) : '#';
$actionPresentation = static function (string $action): array {
    return match ($action) {
        'import_datensatz_importiert' => ['Datensatz importiert', 'file-import', 'success'],
        'import_datensatz_aktualisiert' => ['Datensatz aktualisiert', 'file-pen', 'info'],
        'import_datensatz_uebersprungen' => ['Datensatz übersprungen', 'forward', 'warning text-dark'],
        'import_abgeschlossen' => ['Import abgeschlossen', 'circle-check', 'success'],
        'hintergrundaufgabe_abgeschlossen' => ['Aufgabe abgeschlossen', 'circle-check', 'success'],
        'hintergrundaufgabe_fehlgeschlagen' => ['Aufgabe fehlgeschlagen', 'triangle-exclamation', 'danger'],
        default => [str_replace('_', ' ', $action), 'clock-rotate-left', str_contains($action, 'geloescht') || str_contains($action, 'delete') ? 'danger' : 'secondary'],
    };
};
?>

<div id="audit-page-sections">
<div class="alert alert-info">
    Das Ereignisprotokoll zeigt fachliche Aktionen. Die Datenrevisionen darunter
    werden direkt aus den von ReBean erzeugten Revisionstabellen gelesen.
</div>

<?php if (!empty($importRuns)): ?>
<div class="card mb-4" id="audit-import-runs">
    <div class="card-header fw-semibold d-flex flex-wrap justify-content-between align-items-center gap-2"><span><i class="fa-solid fa-file-import me-2" aria-hidden="true"></i>Letzte Importläufe</span><div class="btn-toolbar gap-2" role="toolbar" aria-label="Importlaufaktionen"><button type="button" class="btn btn-sm btn-secondary" data-run-select-all="import"><i class="fa-solid fa-check-double me-1" aria-hidden="true"></i>Alle auswählen</button><button type="button" class="btn btn-sm btn-primary" data-run-show-selected="import"><i class="fa-solid fa-eye me-1" aria-hidden="true"></i>Auswahl anzeigen</button><button type="button" class="btn btn-sm btn-primary" data-run-show-all="import"><i class="fa-solid fa-list me-1" aria-hidden="true"></i>Alle anzeigen</button><button type="button" class="btn btn-sm btn-outline-secondary" data-run-export="import" data-format="csv"><i class="fa-solid fa-file-csv me-1" aria-hidden="true"></i>CSV</button><button type="button" class="btn btn-sm btn-outline-secondary" data-run-export="import" data-format="json"><i class="fa-solid fa-file-code me-1" aria-hidden="true"></i>JSON</button></div></div>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead><tr><th><span class="visually-hidden">Auswahl</span></th><th>Zeitraum</th><th>Importiert</th><th>Aktualisiert</th><th>Übersprungen</th><th>Ereignisse</th><th>Aktion</th></tr></thead>
            <tbody><?php foreach ($importRuns as $run): ?><tr>
                <?php $correlationId = (string) ($run['correlation_id'] ?? ''); $importSelected = in_array($correlationId, array_filter(explode(',', (string) ($auditFilters['correlations'] ?? ''))), true); $importUrl = $auditUrl(['correlations' => $correlationId, 'correlation_id' => null, 'page' => null]); ?><td><input class="form-check-input audit-run-checkbox" data-run-kind="import" type="checkbox" value="<?= htmlspecialchars($correlationId, ENT_QUOTES) ?>" aria-label="Importlauf <?= htmlspecialchars($correlationId, ENT_QUOTES) ?>"<?= $importSelected ? ' checked' : '' ?>></td><td><span class="text-nowrap"><?= htmlspecialchars((string) ($run['started_at'] ?? '—')) ?></span><br><span class="small text-body-secondary">bis <?= htmlspecialchars((string) ($run['finished_at'] ?? '—')) ?></span></td>
                <td><span class="badge text-bg-success"><?= (int) ($run['imported'] ?? 0) ?></span></td>
                <td><span class="badge text-bg-info"><?= (int) ($run['updated'] ?? 0) ?></span></td>
                <td><span class="badge text-bg-warning text-dark"><?= (int) ($run['skipped'] ?? 0) ?></span></td>
                <td><?= (int) ($run['event_count'] ?? 0) ?></td>
                <td><a class="btn btn-sm btn-secondary" href="<?= htmlspecialchars($importUrl, ENT_QUOTES) ?>" hx-get="<?= htmlspecialchars($importUrl, ENT_QUOTES) ?>" hx-target="#audit-events-panel" hx-select="#audit-events-panel" hx-swap="outerHTML" hx-push-url="true"><i class="fa-solid fa-eye me-1" aria-hidden="true"></i>Anzeigen</a></td>
            </tr><?php endforeach; ?></tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<details class="card mb-4" id="audit-events-panel">
<summary class="card-header audit-panel-summary fw-semibold d-flex justify-content-between align-items-center"><span class="d-flex align-items-center gap-2"><i class="fa-solid fa-clock-rotate-left text-body-secondary" aria-hidden="true"></i><span>Ereignisprotokoll</span></span><span class="d-flex align-items-center gap-2"><label class="small fw-normal mb-0" for="audit-events-auto-refresh"><input class="form-check-input me-1" type="checkbox" id="audit-events-auto-refresh"> automatisch aktualisieren</label><span class="badge text-bg-primary"><?= (int) ($pagination['total_entries'] ?? count($entries)) ?></span></span></summary>
<div class="card-body">
<form method="get" action="<?= htmlspecialchars(url_for('admin/audit-log'), ENT_QUOTES) ?>" hx-get="<?= htmlspecialchars(url_for('admin/audit-log'), ENT_QUOTES) ?>" hx-target="#audit-events-panel" hx-select="#audit-events-panel" hx-swap="outerHTML" hx-push-url="true" class="card card-body bg-body-tertiary mb-3">
    <div class="row g-3 align-items-end">
        <div class="col-12 col-lg-4"><label class="form-label" for="audit-search"><i class="fa-solid fa-magnifying-glass me-1" aria-hidden="true"></i>Suche</label><input class="form-control" id="audit-search" name="search" value="<?= htmlspecialchars((string) ($auditFilters['search'] ?? '')) ?>" placeholder="Aktion, Details oder Benutzer"></div>
        <div class="col-6 col-lg-2"><label class="form-label" for="audit-category"><i class="fa-solid fa-layer-group me-1" aria-hidden="true"></i>Bereich</label><select class="form-select" id="audit-category" name="category"><option value="">Alle Bereiche</option><?php foreach (['import' => 'Import', 'background_job' => 'Hintergrundaufgaben', 'general' => 'Allgemein'] as $value => $label): ?><option value="<?= $value ?>" <?= ($auditFilters['category'] ?? '') === $value ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select></div>
        <div class="col-6 col-lg-2"><label class="form-label" for="audit-status"><i class="fa-solid fa-signal me-1" aria-hidden="true"></i>Status</label><input class="form-control" id="audit-status" name="status" value="<?= htmlspecialchars((string) ($auditFilters['status'] ?? '')) ?>" placeholder="z. B. importiert"></div>
        <div class="col-6 col-lg-2"><label class="form-label" for="audit-from"><i class="fa-solid fa-calendar-day me-1" aria-hidden="true"></i>Von</label><input class="form-control" type="date" id="audit-from" name="from" value="<?= htmlspecialchars((string) ($auditFilters['from'] ?? '')) ?>"></div>
        <div class="col-6 col-lg-2"><label class="form-label" for="audit-to"><i class="fa-solid fa-calendar-check me-1" aria-hidden="true"></i>Bis</label><input class="form-control" type="date" id="audit-to" name="to" value="<?= htmlspecialchars((string) ($auditFilters['to'] ?? '')) ?>"></div>
        <div class="col-12 col-lg-4"><label class="form-label" for="audit-user"><i class="fa-solid fa-user me-1" aria-hidden="true"></i>Benutzer</label><input class="form-control" id="audit-user" name="user" value="<?= htmlspecialchars((string) ($auditFilters['user'] ?? '')) ?>"></div>
        <div class="col-12 col-lg-4"><label class="form-label" for="audit-correlation"><i class="fa-solid fa-link me-1" aria-hidden="true"></i>Vorgang</label><input class="form-control font-monospace" id="audit-correlation" name="correlation_id" value="<?= htmlspecialchars((string) ($auditFilters['correlation_id'] ?? '')) ?>"></div>
        <div class="col-12 col-lg-4 d-flex gap-2"><button class="btn btn-primary" type="submit"><i class="fa-solid fa-filter me-1" aria-hidden="true"></i>Filtern</button><a class="btn btn-secondary" href="<?= htmlspecialchars(url_for('admin/audit-log'), ENT_QUOTES) ?>" hx-get="<?= htmlspecialchars(url_for('admin/audit-log'), ENT_QUOTES) ?>" hx-target="#audit-events-panel" hx-select="#audit-events-panel" hx-swap="outerHTML" hx-push-url="true"><i class="fa-solid fa-rotate-left me-1" aria-hidden="true"></i>Zurücksetzen</a></div>
    </div>
</form>
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
                        href="<?= htmlspecialchars($previousUrl, ENT_QUOTES) ?>" hx-get="<?= htmlspecialchars($previousUrl, ENT_QUOTES) ?>" hx-target="#audit-events-panel" hx-select="#audit-events-panel" hx-swap="outerHTML" hx-push-url="true"
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
                        href="<?= htmlspecialchars($nextUrl, ENT_QUOTES) ?>" hx-get="<?= htmlspecialchars($nextUrl, ENT_QUOTES) ?>" hx-target="#audit-events-panel" hx-select="#audit-events-panel" hx-swap="outerHTML" hx-push-url="true"
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
                        <?php [$actionLabel, $actionIcon, $actionClass] = $actionPresentation((string) $entry['aktion']); ?>
                        <span class="badge text-bg-<?= $actionClass ?>"><i class="fa-solid fa-<?= $actionIcon ?> me-1" aria-hidden="true"></i><?= htmlspecialchars($actionLabel) ?></span>
                        <?php if (!empty($entry['correlation_id'])): ?><div class="small text-body-secondary font-monospace mt-1"><?= htmlspecialchars(substr((string) $entry['correlation_id'], 0, 24)) ?></div><?php endif; ?>
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
<section class="card mb-4" id="audit-tasks-panel">
<div class="card-header audit-panel-summary fw-semibold d-flex justify-content-between align-items-center"><span class="d-flex align-items-center gap-2"><i class="fa-solid fa-list-check text-body-secondary" aria-hidden="true"></i><span>Hintergrundaufgaben</span></span><span class="d-flex align-items-center gap-2"><label class="small fw-normal mb-0" for="audit-tasks-auto-refresh"><input class="form-check-input me-1" type="checkbox" id="audit-tasks-auto-refresh"> automatisch aktualisieren</label><span class="badge text-bg-info"><?= count($cronPendingJobs) ?></span></span></div>
<div class="card-body">
<?php if (!empty($cronPendingJobs)): ?>
<div class="table-responsive"><table class="table table-sm mb-0 align-middle"><thead><tr><th>Aufgabe</th><th>Status</th><th>Fortschritt</th><th>Datensatz</th><th>Meldung</th><th>Aktion</th></tr></thead><tbody>
<?php foreach ($cronPendingJobs as $job): $state = (string) ($job['state'] ?? 'queued'); $step = (int) ($job['step'] ?? 0); $total = (int) ($job['total'] ?? 0); $jobId = (string) ($job['id'] ?? ''); $stateLabel = ['queued' => 'Wartet', 'running' => 'Läuft', 'cancel_requested' => 'Abbruch vorgemerkt'][$state] ?? 'Unbekannt'; $stateClass = $state === 'running' ? 'primary' : ($state === 'cancel_requested' ? 'danger' : 'warning text-dark'); ?>
<tr><td><span class="fw-semibold"><?= htmlspecialchars((string) ($job['label'] ?? 'Hintergrundaufgabe')) ?></span><br><span class="small text-body-secondary font-monospace"><?= htmlspecialchars(substr($jobId, 0, 12)) ?></span></td><td><span class="badge text-bg-<?= $stateClass ?>"><?= htmlspecialchars($stateLabel) ?></span></td><td><?= $total > 0 ? htmlspecialchars($step . ' von ' . $total) : '—' ?></td><td><?= htmlspecialchars((string) ($job['current_device'] ?? '—')) ?></td><td class="text-break"><?= htmlspecialchars((string) ($job['message'] ?: 'Wartet auf einen freien Arbeitsabschnitt.')) ?></td><td><?php if (!empty($job['cancellable'])): ?><form method="post" action="<?= htmlspecialchars(url_for('admin/audit-log/job/' . rawurlencode($jobId) . '/abbrechen'), ENT_QUOTES) ?>" hx-post="<?= htmlspecialchars(url_for('admin/audit-log/job/' . rawurlencode($jobId) . '/abbrechen'), ENT_QUOTES) ?>" hx-target="#audit-tasks-panel" hx-select="#audit-tasks-panel" hx-swap="none" onsubmit="return confirm('Diese Hintergrundaufgabe wirklich abbrechen?');"><button type="submit" class="btn btn-sm btn-danger text-nowrap"><i class="fa-solid fa-stop me-1" aria-hidden="true"></i>Abbrechen</button></form><?php else: ?><span class="small text-body-secondary"><i class="fa-solid fa-lock me-1" aria-hidden="true"></i>Systemaufgabe</span><?php endif; ?></td></tr>
<?php endforeach; ?></tbody></table></div>
<?php else: ?><p class="small text-body-secondary mb-0"><i class="fa-solid fa-check me-1" aria-hidden="true"></i>Keine ausstehenden Hintergrundaufgaben.</p><?php endif; ?>
</div>
</section>
<details class="card mb-4" id="audit-cron-panel"<?= !empty($cronRunFilters) ? ' open' : '' ?> >
<summary class="card-header audit-panel-summary fw-semibold d-flex justify-content-between align-items-center"><span class="d-flex align-items-center gap-2"><i class="fa-solid fa-clock text-body-secondary" aria-hidden="true"></i><span>Prüfapp-Cron</span></span><span class="d-flex align-items-center gap-2"><label class="small fw-normal mb-0" for="audit-cron-show-debug"><input class="form-check-input me-1" type="checkbox" id="audit-cron-show-debug"> Debug-Meldungen anzeigen</label><label class="small fw-normal mb-0" for="audit-cron-auto-refresh"><input class="form-check-input me-1" type="checkbox" id="audit-cron-auto-refresh"> automatisch aktualisieren</label><span class="badge text-bg-info"><?= (int) ($cronTotal ?? count($cronLog)) ?></span></span></summary>
<div class="card-body">
<p class="text-body-secondary">Letzte Cron-Läufe, Berichtserzeugung und Hintergrundjobs.</p>
<?php if (!empty($cronRunFilters)): ?><div class="alert alert-primary py-2 d-flex flex-wrap align-items-center justify-content-between gap-2"><span><i class="fa-solid fa-filter me-1" aria-hidden="true"></i><?= count($cronRunFilters) === 1 ? 'Cron-Lauf' : count($cronRunFilters) . ' Cron-Läufe' ?> ausgewählt: <span class="font-monospace"><?= htmlspecialchars(implode(', ', $cronRunFilters)) ?></span></span><a class="btn btn-sm btn-outline-primary" href="<?= htmlspecialchars(url_for('admin/audit-log#audit-cron-panel'), ENT_QUOTES) ?>" hx-get="<?= htmlspecialchars(url_for('admin/audit-log#audit-cron-panel'), ENT_QUOTES) ?>" hx-target="#audit-cron-panel" hx-select="#audit-cron-panel" hx-swap="outerHTML" hx-push-url="true"><i class="fa-solid fa-filter-circle-xmark me-1" aria-hidden="true"></i>Filter löschen</a></div><?php endif; ?>
<?php $formatCronDate = static function ($value): string { try { return (new DateTimeImmutable((string) $value))->setTimezone(new DateTimeZone('Europe/Berlin'))->format('d.m.Y H:i:s'); } catch (Throwable) { return '—'; } }; ?>
<?php if (empty($cronHealthy)): ?><div class="alert alert-warning d-flex align-items-start gap-2" role="alert"><i class="fa-solid fa-triangle-exclamation mt-1" aria-hidden="true"></i><div><strong>Prüfapp-Cron läuft aktuell nicht zuverlässig.</strong><br><span><?php if (($cronAge ?? null) === null): ?>Es wurde noch kein aktueller Cron-Heartbeat gefunden.<?php else: ?>Der letzte Lauf ist <?= htmlspecialchars((string) max(1, (int) floor(((int) $cronAge) / 60))) ?> Minuten alt. Erwartet wird ein Lauf innerhalb von fünf Minuten.<?php endif; ?> Bitte Cron-Dienst und `/var/www/html/pruefapp/bin/cron.php` prüfen.</span></div></div><?php else: ?><div class="alert alert-success py-2" role="status"><i class="fa-solid fa-circle-check me-1" aria-hidden="true"></i>Cron aktiv, letzter Heartbeat vor <?= htmlspecialchars((string) max(0, (int) floor(((int) $cronAge) / 60))) ?> Minuten.</div><?php endif; ?>
<?php if (!empty($cronRuns)): ?>
<div class="card mb-3">
    <div class="card-header py-2 d-flex flex-wrap justify-content-between align-items-center gap-2"><strong><i class="fa-solid fa-chart-line me-1" aria-hidden="true"></i>Cron-Läufe</strong><div class="btn-toolbar gap-2" role="toolbar" aria-label="Cron-Laufaktionen"><button type="button" class="btn btn-sm btn-secondary" data-run-select-all="cron"><i class="fa-solid fa-check-double me-1" aria-hidden="true"></i>Alle auswählen</button><button type="button" class="btn btn-sm btn-primary" data-run-show-selected="cron"><i class="fa-solid fa-eye me-1" aria-hidden="true"></i>Auswahl anzeigen</button><button type="button" class="btn btn-sm btn-primary" data-run-show-all="cron"><i class="fa-solid fa-list me-1" aria-hidden="true"></i>Alle anzeigen</button><button type="button" class="btn btn-sm btn-outline-secondary" data-run-export="cron" data-format="csv"><i class="fa-solid fa-file-csv me-1" aria-hidden="true"></i>CSV</button><button type="button" class="btn btn-sm btn-outline-secondary" data-run-export="cron" data-format="json"><i class="fa-solid fa-file-code me-1" aria-hidden="true"></i>JSON</button><?php if (!empty($cronRunFilters)): ?><a class="btn btn-sm btn-secondary" href="<?= htmlspecialchars(url_for('admin/audit-log#audit-cron-panel'), ENT_QUOTES) ?>" hx-get="<?= htmlspecialchars(url_for('admin/audit-log#audit-cron-panel'), ENT_QUOTES) ?>" hx-target="#audit-cron-panel" hx-select="#audit-cron-panel" hx-swap="outerHTML" hx-push-url="true"><i class="fa-solid fa-list me-1" aria-hidden="true"></i>Alle Meldungen</a><?php endif; ?></div></div>
    <div class="table-responsive"><table class="table table-sm mb-0 align-middle"><thead><tr><th><span class="visually-hidden">Auswahl</span></th><th>Beginn</th><th>Ende</th><th>Meldungen</th><th>Warnungen</th><th>Fehler</th><th>Aktion</th></tr></thead><tbody>
    <?php foreach ($cronRuns as $run): $runId = (string) ($run['run_id'] ?? ''); $runSelected = in_array($runId, $cronRunFilters ?? [], true); $runUrl = url_for('admin/audit-log?cron_run=' . rawurlencode($runId) . '#audit-cron-panel'); ?><tr class="<?= $runSelected ? 'table-active' : '' ?>" data-run-row="<?= htmlspecialchars($runId, ENT_QUOTES) ?>"><td><input class="form-check-input audit-run-checkbox" data-run-kind="cron" type="checkbox" value="<?= htmlspecialchars($runId, ENT_QUOTES) ?>" aria-label="Cron-Lauf <?= htmlspecialchars($runId, ENT_QUOTES) ?>"<?= $runSelected ? ' checked' : '' ?>></td><td class="text-nowrap"><?= htmlspecialchars($formatCronDate($run['started_at'] ?? '')) ?></td><td class="text-nowrap"><?= htmlspecialchars($formatCronDate($run['finished_at'] ?? '')) ?></td><td><?= (int) ($run['entries'] ?? 0) ?></td><td><span class="badge text-bg-warning text-dark"><?= (int) ($run['warnings'] ?? 0) ?></span></td><td><span class="badge text-bg-danger"><?= (int) ($run['errors'] ?? 0) ?></span></td><td><a class="btn btn-sm btn-secondary" href="<?= htmlspecialchars($runUrl, ENT_QUOTES) ?>" hx-get="<?= htmlspecialchars($runUrl, ENT_QUOTES) ?>" hx-target="#audit-cron-panel" hx-select="#audit-cron-panel" hx-swap="outerHTML" hx-push-url="true"><i class="fa-solid fa-eye me-1" aria-hidden="true"></i>Anzeigen</a></td></tr><?php if ($runSelected && count($cronRunFilters ?? []) === 1): ?><tr class="audit-inline-detail"><td colspan="7"><div class="small fw-semibold mb-2"><i class="fa-solid fa-list me-1" aria-hidden="true"></i>Meldungen für <?= htmlspecialchars($runId) ?></div><?php if ($cronLog === []): ?><span class="text-body-secondary">Keine Meldungen für diesen Lauf.</span><?php else: ?><div class="vstack gap-1"><?php foreach ($cronLog as $line): ?><div class="d-flex gap-2 align-items-start"><span class="badge text-bg-<?= strtolower((string) ($line['level'] ?? 'info')) === 'error' ? 'danger' : (strtolower((string) ($line['level'] ?? 'info')) === 'warning' ? 'warning text-dark' : 'secondary') ?>"><?= htmlspecialchars(strtoupper((string) ($line['level'] ?? 'info'))) ?></span><span class="font-monospace small"><?= htmlspecialchars((string) ($line['message'] ?? '')) ?></span></div><?php endforeach; ?></div><?php endif; ?></td></tr><?php endif; ?><?php endforeach; ?>
    <?php if (count($cronRunFilters ?? []) > 1): ?><tr class="audit-inline-detail"><td colspan="7"><div class="small fw-semibold mb-2"><i class="fa-solid fa-list me-1" aria-hidden="true"></i><?= count($cronRunFilters) ?> ausgewählte Cron-Läufe</div><span class="text-body-secondary">Die Meldungen der Auswahl sind im Logbereich darunter zusammengefasst.</span></td></tr><?php endif; ?>
    </tbody></table></div>
</div>
<?php endif; ?>
<?php if (empty($cronLog)): ?>
    <div class="alert alert-warning">Noch kein Prüfapp-Cron-Lauf protokolliert.</div>
<?php else: ?>
    <div class="table-responsive"><table class="table table-sm align-middle"><thead><tr><th>Zeit (lokal)</th><th>Status</th><th>Meldung</th></tr></thead><tbody><?php foreach ($cronLog as $line): $message = (string) ($line['message'] ?? ''); $level = strtolower((string) ($line['level'] ?? 'info')); $isDebug = $level === 'debug' || str_starts_with($message, 'Debug:') || str_starts_with($message, '[cron debug]'); $levelClass = $level === 'error' ? 'danger' : ($level === 'warning' ? 'warning text-dark' : ($isDebug ? 'secondary' : 'info')); ?><tr class="<?= $isDebug ? 'cron-debug-row' : '' ?>"><td class="text-nowrap"><?= htmlspecialchars($formatCronDate($line['run_at'] ?? '')) ?></td><td><span class="badge text-bg-<?= $levelClass ?>"><?= htmlspecialchars(strtoupper($isDebug ? 'DEBUG' : $level)) ?></span></td><td class="text-break"><?php if (mb_strlen($message) > 220): ?><details><summary><?= htmlspecialchars(mb_substr($message, 0, 220) . ' …') ?></summary><pre class="small mt-2 mb-0 text-wrap"><?= htmlspecialchars($message) ?></pre></details><?php else: ?><?= htmlspecialchars($message) ?><?php endif; ?></td></tr><?php endforeach; ?></tbody></table></div>
    <?php if (($cronPages ?? 1) > 1): ?><nav aria-label="Cron-Log-Seiten"><ul class="pagination pagination-sm"><?php for ($p = 1; $p <= $cronPages; $p++): $cronQuery = ['cron_page' => $p]; if (!empty($cronRunFilters)) $cronQuery['cron_runs'] = implode(',', $cronRunFilters); $cronPageUrl = url_for('admin/audit-log?' . http_build_query($cronQuery) . '#audit-cron-panel'); ?><li class="page-item<?= $p === $cronPage ? ' active' : '' ?>"><a class="page-link" href="<?= htmlspecialchars($cronPageUrl, ENT_QUOTES) ?>" hx-get="<?= htmlspecialchars($cronPageUrl, ENT_QUOTES) ?>" hx-target="#audit-cron-panel" hx-select="#audit-cron-panel" hx-swap="outerHTML" hx-push-url="true"><?= $p ?></a></li><?php endfor; ?></ul></nav><?php endif; ?>
<?php endif; ?>
</div>
</details>
<script>
(() => {
  const configs = [['audit-tasks-panel', 'audit-tasks-auto-refresh', 'pruefapp-audit-tasks-refresh'], ['audit-events-panel', 'audit-events-auto-refresh', 'pruefapp-audit-events-refresh'], ['audit-cron-panel', 'audit-cron-auto-refresh', 'pruefapp-audit-cron-refresh'], ['audit-revisions-panel', 'audit-revisions-auto-refresh', 'pruefapp-audit-revisions-refresh']];
  if (!window.pruefappAuditSummaryControlsBound) {
    window.pruefappAuditSummaryControlsBound = true;
    document.addEventListener('click', event => {
      const control = event.target.closest('.audit-panel-summary input[type="checkbox"], .audit-panel-summary label');
      if (!control) return;
      const checkbox = control.matches('input') ? control : (control.querySelector('input[type="checkbox"]') || document.getElementById(control.htmlFor));
      if (!checkbox || checkbox.disabled) return;
      event.preventDefault();
      event.stopPropagation();
      checkbox.checked = !checkbox.checked;
      checkbox.dispatchEvent(new Event('change', {bubbles: true}));
    }, true);
  }
  const setup = ([panelId, toggleId, key]) => {
    const panel = document.getElementById(panelId); const toggle = document.getElementById(toggleId);
    if (!panel || !toggle || toggle.dataset.bound === '1') return;
    toggle.dataset.bound = '1';
    try { toggle.checked = localStorage.getItem(key) === '1'; } catch (_) {}
    const stateKey = key + '-open';
    const save = () => { try { sessionStorage.setItem(stateKey, panel.open ? '1' : '0'); } catch (_) {} };
    const restore = () => { try { if (sessionStorage.getItem(stateKey) === '1') panel.open = true; } catch (_) {} };
    const enable = () => {
      try { localStorage.setItem(key, toggle.checked ? '1' : '0'); } catch (_) {}
      if (!toggle.checked) return;
      if (panel.tagName === 'DETAILS') panel.open = true; save();
      if (window.htmx) { panel.setAttribute('hx-get', window.location.href); panel.setAttribute('hx-trigger', 'every 30s'); panel.setAttribute('hx-target', '#' + panelId); panel.setAttribute('hx-select', '#' + panelId); panel.setAttribute('hx-swap', 'outerHTML'); window.htmx.process(panel); }
      else { toggle.checked = false; toggle.disabled = true; toggle.title = 'Automatische Aktualisierung benötigt HTMX.'; }
    };
    toggle.addEventListener('change', enable); panel.addEventListener('toggle', save); restore(); enable();
  };
  configs.forEach(setup);
  const debugToggle = () => {
    const toggle = document.getElementById('audit-cron-show-debug');
    if (!toggle || toggle.dataset.bound === '1') return;
    toggle.dataset.bound = '1';
    try { toggle.checked = localStorage.getItem('pruefapp-audit-cron-show-debug') === '1'; } catch (_) {}
    const apply = () => {
      try { localStorage.setItem('pruefapp-audit-cron-show-debug', toggle.checked ? '1' : '0'); } catch (_) {}
      document.querySelectorAll('#audit-cron-panel .cron-debug-row').forEach(row => { row.classList.toggle('d-none', !toggle.checked); });
    };
    toggle.addEventListener('change', apply); apply();
  };
  debugToggle();
  const selectedRuns = kind => [...document.querySelectorAll(`.audit-run-checkbox[data-run-kind="${kind}"]:checked`)].map(input => input.value).filter(Boolean);
  const targetFor = kind => kind === 'cron' ? '#audit-cron-panel' : '#audit-events-panel';
  const selectedUrl = kind => {
    const url = new URL(window.location.href);
    url.hash = kind === 'cron' ? 'audit-cron-panel' : 'audit-events-panel';
    if (kind === 'cron') { url.searchParams.delete('cron_run'); url.searchParams.delete('cron_page'); url.searchParams.set('cron_runs', selectedRuns(kind).join(',')); }
    else { url.searchParams.delete('correlation_id'); url.searchParams.delete('page'); url.searchParams.set('correlations', selectedRuns(kind).join(',')); url.searchParams.set('category', 'import'); }
    return url.toString();
  };
  const refreshSelection = kind => {
    if (!window.htmx) return;
    const values = selectedRuns(kind); if (!values.length) return;
    const url = selectedUrl(kind); window.htmx.ajax('GET', url, {target: targetFor(kind), select: targetFor(kind), swap: 'outerHTML', pushUrl: url});
  };
  const bindRunSelection = () => {
    ['cron', 'import'].forEach(kind => {
      const boxes = [...document.querySelectorAll(`.audit-run-checkbox[data-run-kind="${kind}"]`)]; let last = null;
      boxes.forEach((box, index) => { if (box.dataset.bound === '1') return; box.dataset.bound = '1'; box.addEventListener('click', event => { if (event.shiftKey && last !== null) { const start = Math.min(last, index); const end = Math.max(last, index); boxes.slice(start, end + 1).forEach(item => { item.checked = box.checked; }); } last = index; }); });
      document.querySelector(`[data-run-select-all="${kind}"]`)?.addEventListener('click', () => boxes.forEach(box => { box.checked = true; }));
      document.querySelector(`[data-run-show-selected="${kind}"]`)?.addEventListener('click', () => refreshSelection(kind));
      document.querySelector(`[data-run-show-all="${kind}"]`)?.addEventListener('click', () => { boxes.forEach(box => { box.checked = true; }); refreshSelection(kind); });
      document.querySelectorAll(`[data-run-export="${kind}"]`).forEach(button => { if (button.dataset.bound === '1') return; button.dataset.bound = '1'; button.addEventListener('click', () => { const values = selectedRuns(kind); if (!values.length) return; const form = document.createElement('form'); form.method = 'post'; form.action = '<?= htmlspecialchars(url_for('admin/audit-log/export'), ENT_QUOTES) ?>'; form.target = '_blank'; [['kind', kind], ['format', button.dataset.format || 'json'], ['ids', values.join(',')]].forEach(([name, value]) => { const input = document.createElement('input'); input.type = 'hidden'; input.name = name; input.value = value; form.appendChild(input); }); document.body.appendChild(form); form.submit(); form.remove(); }); });
    });
  };
  bindRunSelection();
  window.addEventListener('DOMContentLoaded', () => { configs.forEach(setup); debugToggle(); bindRunSelection(); });
  document.body.addEventListener('htmx:afterSwap', () => { configs.forEach(setup); debugToggle(); bindRunSelection(); });
  document.body.addEventListener('audit-tasks-refresh', () => { if (window.htmx) window.htmx.ajax('GET', window.location.href, {target: '#audit-tasks-panel', select: '#audit-tasks-panel', swap: 'outerHTML'}); });
})();
</script>
<style>
.audit-panel-summary{justify-content:flex-start!important;text-align:left!important}.audit-panel-summary>span:first-child{min-width:0;justify-content:flex-start;text-align:left}.audit-panel-summary>span:last-child{margin-left:auto}.audit-panel-summary>span:first-child>i{flex:0 0 auto}.audit-panel-summary label{white-space:nowrap}
#audit-page-sections{display:flex;flex-direction:column;gap:0}#audit-page-sections>#audit-tasks-panel{order:1}#audit-page-sections>#audit-cron-panel{order:2}#audit-page-sections>#audit-import-runs,#audit-page-sections>#audit-events-panel{order:3}#audit-page-sections>#audit-revisions-panel{order:4}.audit-inline-detail td{background:var(--bs-tertiary-bg);border-left:4px solid var(--bs-primary)}
</style>

<details class="card mb-4" id="audit-revisions-panel">
<summary class="card-header audit-panel-summary fw-semibold d-flex justify-content-between align-items-center"><span class="d-flex align-items-center gap-2"><i class="fa-solid fa-clock-rotate-left text-body-secondary" aria-hidden="true"></i><span>Datenrevisionen (ReBean)</span></span><span class="d-flex align-items-center gap-2"><label class="small fw-normal mb-0" for="audit-revisions-auto-refresh"><input class="form-check-input me-1" type="checkbox" id="audit-revisions-auto-refresh"> automatisch aktualisieren</label><span class="badge text-bg-secondary"><?= (int) ($revisionTotal ?? count($revisions)) ?></span></span></summary>
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
    <?php if (($revisionPages ?? 1) > 1): ?><nav aria-label="Paginierung der Datenrevisionen"><ul class="pagination pagination-sm mt-3"><?php for ($p = 1; $p <= $revisionPages; $p++): $revisionUrl = url_for('admin/audit-log?revision_page=' . $p); ?><li class="page-item<?= $p === $revisionPage ? ' active' : '' ?>"><a class="page-link" href="<?= htmlspecialchars($revisionUrl, ENT_QUOTES) ?>" hx-get="<?= htmlspecialchars($revisionUrl, ENT_QUOTES) ?>" hx-target="#audit-revisions-panel" hx-select="#audit-revisions-panel" hx-swap="outerHTML" hx-push-url="true"><?= $p ?></a></li><?php endfor; ?></ul></nav><?php endif; ?>
<?php endif; ?>
</div>
</details>
</div>
