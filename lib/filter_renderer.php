<?php

declare(strict_types=1);

/** Shared Bootstrap/HTMX filter renderer for device and billing views. */
function render_common_filter_panel(string $context, array $filters, array $data = []): string
{
    $action = $context === 'billing' ? url_for('admin/abrechnung') : url_for('geraete');
    $id = $context === 'billing' ? 'billing-common-filter' : 'device-common-filter';
    $value = static fn(string $key, string $fallback = ''): string => htmlspecialchars((string) ($filters[$key] ?? $fallback), ENT_QUOTES);
    $selected = static fn(string $key, string $option): string => (string) ($filters[$key] ?? '') === $option ? ' selected' : '';
    $options = static function (array $items, string $selectedValue, string $labelKey = 'name'): string {
        $html = '';
        foreach ($items as $item) {
            $id = (string) ((int) ($item->id ?? 0));
            $label = (string) ($item->{$labelKey} ?? $item->name ?? '');
            if ($labelKey === 'number') $label = (string) (($item->number ?? '') ?: ($item->name ?? ''));
            $html .= '<option value="' . htmlspecialchars($id, ENT_QUOTES) . '"' . ($selectedValue === $id ? ' selected' : '') . '>' . htmlspecialchars($label) . '</option>';
        }
        return $html;
    };
    ob_start();
    $customers = $data['customers'] ?? [];
    $sites = $data['sites'] ?? [];
    $buildings = $data['buildings'] ?? [];
    $floors = $data['floors'] ?? [];
    $rooms = $data['rooms'] ?? [];
    ?>
    <form id="<?= $id ?>" class="common-filter-panel card card-body mb-4" method="get" action="<?= htmlspecialchars($action, ENT_QUOTES) ?>" hx-get="<?= htmlspecialchars($action, ENT_QUOTES) ?>" hx-target="<?= $context === 'billing' ? '#billing-content' : '#device-page' ?>" hx-select="<?= $context === 'billing' ? '#billing-content' : '#device-page' ?>" hx-swap="outerHTML" hx-push-url="true">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="h6 mb-0"><i class="fa-solid fa-filter me-2" aria-hidden="true"></i>Filter</h2>
        <button class="btn btn-sm btn-outline-secondary d-md-none" type="button" data-bs-toggle="collapse" data-bs-target="#<?= $id ?>-fields"><i class="fa-solid fa-sliders me-1" aria-hidden="true"></i>Filter anzeigen</button>
      </div>
      <div id="<?= $id ?>-fields" class="row g-3 align-items-end">
        <div class="col-12 col-md-4 col-xl-3"><label class="form-label" for="<?= $id ?>-q"><i class="fa-solid fa-magnifying-glass me-1" aria-hidden="true"></i>Suche</label><input class="form-control" id="<?= $id ?>-q" name="q" value="<?= $value('q') ?>" placeholder="Gerät, Nummer, Kunde"></div>
        <div class="col-12 col-sm-6 col-md-4 col-xl-2"><label class="form-label"><i class="fa-solid fa-building me-1" aria-hidden="true"></i>Kunde</label><select class="form-select" name="customer_id"><option value="">Alle Kunden</option><?= $options($customers, (string) ($filters['customer_id'] ?? '')) ?></select></div>
        <div class="col-12 col-sm-6 col-md-4 col-xl-2"><label class="form-label"><i class="fa-solid fa-location-dot me-1" aria-hidden="true"></i>Standort</label><select class="form-select" name="site_id"><option value="">Alle Standorte</option><?= $options($sites, (string) ($filters['site_id'] ?? '')) ?></select></div>
        <div class="col-12 col-sm-6 col-md-4 col-xl-2"><label class="form-label"><i class="fa-solid fa-city me-1" aria-hidden="true"></i>Gebäude</label><select class="form-select" name="building_id"><option value="">Alle Gebäude</option><?= $options($buildings, (string) ($filters['building_id'] ?? '')) ?></select></div>
        <div class="col-12 col-sm-6 col-md-4 col-xl-2"><label class="form-label"><i class="fa-solid fa-layer-group me-1" aria-hidden="true"></i>Etage</label><select class="form-select" name="floor_id"><option value="">Alle Etagen</option><?= $options($floors, (string) ($filters['floor_id'] ?? '')) ?></select></div>
        <div class="col-12 col-sm-6 col-md-4 col-xl-2"><label class="form-label"><i class="fa-solid fa-door-open me-1" aria-hidden="true"></i>Raum</label><select class="form-select" name="room_id"><option value="">Alle Räume</option><?= $options($rooms, (string) ($filters['room_id'] ?? ''), 'number') ?></select></div>
        <div class="col-6 col-md-3 col-xl-2"><label class="form-label"><i class="fa-solid fa-calendar-days me-1" aria-hidden="true"></i>Von</label><input class="form-control" type="date" name="from" value="<?= $value('from') ?>"></div>
        <div class="col-6 col-md-3 col-xl-2"><label class="form-label"><i class="fa-solid fa-calendar-days me-1" aria-hidden="true"></i>Bis</label><input class="form-control" type="date" name="to" value="<?= $value('to') ?>"></div>
        <?php if ($context === 'device'): ?>
        <div class="col-12 col-sm-6 col-md-3 col-xl-2"><label class="form-label"><i class="fa-solid fa-clipboard-check me-1" aria-hidden="true"></i>Prüfstatus</label><select class="form-select" name="inspection_status"><option value="">Alle Prüfungen</option><option value="data_missing"<?= $selected('inspection_status', 'data_missing') ?>>Daten fehlen</option><option value="in_progress"<?= $selected('inspection_status', 'in_progress') ?>>In Bearbeitung</option><option value="passed"<?= $selected('inspection_status', 'passed') ?>>Bestanden</option><option value="failed"<?= $selected('inspection_status', 'failed') ?>>Nicht bestanden</option><option value="completed"<?= $selected('inspection_status', 'completed') ?>>Abgeschlossen</option></select></div>
        <div class="col-12 col-sm-6 col-md-3 col-xl-2"><label class="form-label"><i class="fa-solid fa-coins me-1" aria-hidden="true"></i>Abrechenbarkeit</label><select class="form-select" name="billing_eligibility"><option value="">Alle</option><option value="billable"<?= $selected('billing_eligibility', 'billable') ?>>Abrechenbar</option><option value="not_billable"<?= $selected('billing_eligibility', 'not_billable') ?>>Nicht abrechenbar</option></select></div>
        <?php else: ?>
        <div class="col-12 col-sm-6 col-md-3 col-xl-2"><label class="form-label"><i class="fa-solid fa-coins me-1" aria-hidden="true"></i>Abrechenbarkeit</label><select class="form-select" name="eligibility"><option value="billable"<?= ((string) ($filters['eligibility'] ?? 'billable') === 'billable') ? ' selected' : '' ?>>Abrechenbar</option><option value="not_billable"<?= ((string) ($filters['eligibility'] ?? '') === 'not_billable') ? ' selected' : '' ?>>Nicht abrechenbar</option></select></div>
        <?php endif; ?>
        <div class="col-12 col-sm-6 col-md-3 col-xl-2"><label class="form-label"><i class="fa-solid fa-file-invoice me-1" aria-hidden="true"></i>Rechnungsstatus</label><select class="form-select" name="billing_status"><option value="">Alle Status</option><option value="not_exported"<?= $selected('billing_status', 'not_exported') ?>>Nicht exportiert</option><option value="exported"<?= $selected('billing_status', 'exported') ?>>Exportiert</option><option value="export_failed"<?= $selected('billing_status', 'export_failed') ?>>Export fehlgeschlagen</option><option value="manually_unexported"<?= $selected('billing_status', 'manually_unexported') ?>>Manuell zurückgesetzt</option></select></div>
        <div class="col-12 col-md-auto d-flex gap-2"><button class="btn btn-primary" type="submit"><i class="fa-solid fa-filter me-1" aria-hidden="true"></i>Filtern</button><a class="btn btn-outline-secondary" href="<?= htmlspecialchars($action, ENT_QUOTES) ?>" hx-get="<?= htmlspecialchars($action, ENT_QUOTES) ?>" hx-target="<?= $context === 'billing' ? '#billing-content' : '#device-page' ?>" hx-select="<?= $context === 'billing' ? '#billing-content' : '#device-page' ?>" hx-swap="outerHTML" hx-push-url="true"><i class="fa-solid fa-rotate-left me-1" aria-hidden="true"></i>Reset</a></div>
      </div>
    </form>
    <?php
    return (string) ob_get_clean();
}
