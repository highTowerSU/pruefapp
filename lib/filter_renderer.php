<?php

declare(strict_types=1);

/** Shared Bootstrap/HTMX filter renderer for device and billing views. */
function render_common_filter_panel(string $context, array $filters, array $data = []): string
{
    $action = $context === 'billing' ? url_for('admin/abrechnung') : url_for('geraete');
    $id = $context === 'billing' ? 'billing-common-filter' : 'device-common-filter';
    $value = static fn(string $key, string $fallback = ''): string => htmlspecialchars((string) ($filters[$key] ?? $fallback), ENT_QUOTES);
    $selected = static fn(string $key, string $option): string => (string) ($filters[$key] ?? '') === $option ? ' selected' : '';
    $roomLabels = is_array($data['roomLabels'] ?? null) ? $data['roomLabels'] : [];
    $options = static function (array $items, string $selectedValue, string $labelKey = 'name', array $labels = []): string {
        $html = '';
        foreach ($items as $item) {
            $id = (string) ((int) ($item->id ?? 0));
            $label = (string) ($item->{$labelKey} ?? $item->name ?? '');
            if ($labelKey === 'number') $label = (string) (($item->number ?? '') ?: ($item->name ?? ''));
            if ($labels !== [] && isset($labels[(int) ($item->id ?? 0)])) $label = (string) $labels[(int) $item->id];
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
    $examiners = $data['examinerOptions'] ?? [];
    ?>
    <form id="<?= $id ?>" class="common-filter-panel card card-body mb-4" method="get" action="<?= htmlspecialchars($action, ENT_QUOTES) ?>" hx-get="<?= htmlspecialchars($action, ENT_QUOTES) ?>" hx-target="<?= $context === 'billing' ? '#billing-content' : '#device-page' ?>" hx-select="<?= $context === 'billing' ? '#billing-content' : '#device-page' ?>" hx-swap="outerHTML" hx-push-url="true" hx-indicator="#<?= $id ?>-progress" hx-trigger="submit, change from:select delay:120ms" data-filter-form>
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="h6 mb-0"><i class="fa-solid fa-filter me-2" aria-hidden="true"></i>Filter</h2>
        <button class="btn btn-sm btn-outline-secondary d-md-none" type="button" data-bs-toggle="collapse" data-bs-target="#<?= $id ?>-fields"><i class="fa-solid fa-sliders me-1" aria-hidden="true"></i>Filter anzeigen</button>
      </div>
      <div id="<?= $id ?>-fields" class="row g-3 align-items-end">
        <div class="col-12 col-md-4 col-xl-4"><label class="form-label" for="<?= $id ?>-q"><i class="fa-solid fa-magnifying-glass me-1" aria-hidden="true"></i>Suche</label><input class="form-control" id="<?= $id ?>-q" name="q" value="<?= $value('q') ?>" placeholder="Gerät, Nummer, Kunde"></div>
        <div class="col-12 col-sm-6 col-md-4 col-xl-2"><label class="form-label"><i class="fa-solid fa-building me-1" aria-hidden="true"></i>Kunde</label><select class="form-select" name="customer_id"><option value="">Alle Kunden</option><?= $options($customers, (string) ($filters['customer_id'] ?? '')) ?></select></div>
        <div class="col-12 col-sm-6 col-md-4 col-xl-2"><label class="form-label"><i class="fa-solid fa-location-dot me-1" aria-hidden="true"></i>Standort</label><select class="form-select" name="site_id"><option value="">Alle Standorte</option><?= $options($sites, (string) ($filters['site_id'] ?? '')) ?></select></div>
        <div class="col-12 col-sm-6 col-md-4 col-xl-2"><label class="form-label"><i class="fa-solid fa-city me-1" aria-hidden="true"></i>Gebäude</label><select class="form-select" name="building_id"><option value="">Alle Gebäude</option><?= $options($buildings, (string) ($filters['building_id'] ?? '')) ?></select></div>
        <div class="col-12 col-sm-6 col-md-4 col-xl-2"><label class="form-label"><i class="fa-solid fa-layer-group me-1" aria-hidden="true"></i>Etage</label><select class="form-select" name="floor_id"><option value="">Alle Etagen</option><?= $options($floors, (string) ($filters['floor_id'] ?? '')) ?></select></div>
        <div class="col-12 col-sm-6 col-md-4 col-xl-2"><label class="form-label"><i class="fa-solid fa-door-open me-1" aria-hidden="true"></i>Raum</label><select class="form-select" name="room_id"><option value="">Alle Räume</option><?= $options($rooms, (string) ($filters['room_id'] ?? ''), 'number', $roomLabels) ?></select></div>
        <div class="col-6 col-md-3 col-xl-2"><label class="form-label"><i class="fa-solid fa-calendar-days me-1" aria-hidden="true"></i>Von</label><input class="form-control" type="date" name="from" value="<?= $value('from') ?>"></div>
        <div class="col-6 col-md-3 col-xl-2"><label class="form-label"><i class="fa-solid fa-calendar-days me-1" aria-hidden="true"></i>Bis</label><input class="form-control" type="date" name="to" value="<?= $value('to') ?>"></div>
        <?php if ($context === 'device'): ?>
        <div class="col-6 col-md-3 col-xl-2"><label class="form-label"><i class="fa-solid fa-calendar me-1" aria-hidden="true"></i>Jahr</label><input class="form-control" name="year" inputmode="numeric" pattern="[0-9]{4}" maxlength="4" value="<?= $value('year') ?>" placeholder="z. B. 2026"></div>
        <?php endif; ?>
        <div class="col-12 col-sm-6 col-md-3 col-xl-2"><label class="form-label"><i class="fa-solid fa-user-check me-1" aria-hidden="true"></i>Prüfer</label><select class="form-select" name="examiner"><option value="">Alle Prüfer</option><?php foreach ($examiners as $examiner): ?><option value="<?= htmlspecialchars((string) ($examiner['value'] ?? ''), ENT_QUOTES) ?>"<?= (string) ($filters['examiner'] ?? '') === (string) ($examiner['value'] ?? '') ? ' selected' : '' ?>><?= htmlspecialchars((string) ($examiner['label'] ?? $examiner['value'] ?? '')) ?></option><?php endforeach; ?></select></div>
        <div class="col-12 col-sm-6 col-md-3 col-xl-2"><label class="form-label"><i class="fa-solid fa-hourglass-half me-1" aria-hidden="true"></i>Fälligkeit</label><select class="form-select" name="due_status"><option value="">Alle Termine</option><?php foreach (InspectionFilterService::dueOptions() as $dueValue => $dueLabel): ?><option value="<?= htmlspecialchars($dueValue, ENT_QUOTES) ?>"<?= $selected('due_status', $dueValue) ?>><?= htmlspecialchars($dueLabel) ?></option><?php endforeach; ?></select></div>
        <?php if ($context === 'device'): ?>
        <div class="col-12 col-sm-6 col-md-3 col-xl-2"><label class="form-label"><i class="fa-solid fa-clipboard-check me-1" aria-hidden="true"></i>Prüfstatus</label><select class="form-select" name="inspection_status"><option value="">Alle Prüfungen</option><option value="data_missing"<?= $selected('inspection_status', 'data_missing') ?>>Daten fehlen</option><option value="in_progress"<?= $selected('inspection_status', 'in_progress') ?>>In Bearbeitung</option><option value="passed"<?= $selected('inspection_status', 'passed') ?>>Bestanden</option><option value="failed"<?= $selected('inspection_status', 'failed') ?>>Nicht bestanden</option><option value="legacy"<?= $selected('inspection_status', 'legacy') ?>>Legacy</option><option value="completed"<?= $selected('inspection_status', 'completed') ?>>Abgeschlossen</option></select></div>
        <div class="col-12 col-sm-6 col-md-3 col-xl-2"><label class="form-label"><i class="fa-solid fa-coins me-1" aria-hidden="true"></i>Abrechenbarkeit</label><select class="form-select" name="billing_eligibility"><option value="">Alle</option><option value="billable"<?= $selected('billing_eligibility', 'billable') ?>>Abrechenbar</option><option value="not_billable"<?= $selected('billing_eligibility', 'not_billable') ?>>Nicht abrechenbar</option></select></div>
        <?php else: ?>
        <div class="col-12 col-sm-6 col-md-3 col-xl-2"><label class="form-label"><i class="fa-solid fa-coins me-1" aria-hidden="true"></i>Abrechenbarkeit</label><select class="form-select" name="eligibility"><option value="all"<?= $selected('eligibility', 'all') ?>>Alle</option><option value="billable"<?= ((string) ($filters['eligibility'] ?? 'billable') === 'billable') ? ' selected' : '' ?>>Abrechenbar</option><option value="not_billable"<?= $selected('eligibility', 'not_billable') ?>>Nicht abrechenbar</option></select></div>
        <div class="col-12 col-sm-6 col-md-3 col-xl-2"><label class="form-label"><i class="fa-solid fa-link me-1" aria-hidden="true"></i>Kundenzuordnung</label><select class="form-select" name="customer_link"><option value=""<?= $selected('customer_link', '') ?>>Alle</option><option value="assigned"<?= $selected('customer_link', 'assigned') ?>>Kunde vorhanden</option><option value="missing"<?= $selected('customer_link', 'missing') ?>>Kein Kunde</option><option value="sevdesk_linked"<?= $selected('customer_link', 'sevdesk_linked') ?>>SevDesk verknüpft</option><option value="sevdesk_missing"<?= $selected('customer_link', 'sevdesk_missing') ?>>SevDesk fehlt</option></select></div>
        <?php endif; ?>
        <div class="col-12 col-sm-6 col-md-3 col-xl-2"><label class="form-label"><i class="fa-solid fa-file-invoice me-1" aria-hidden="true"></i>Rechnungsstatus</label><select class="form-select" name="billing_status"><?php if ($context === 'billing'): ?><option value="all"<?= $selected('billing_status', 'all') ?>>Alle Status</option><option value=""<?= !isset($filters['billing_status']) || (string) ($filters['billing_status'] ?? '') === '' ? ' selected' : '' ?>>Noch nicht abgerechnet (Standard)</option><?php else: ?><option value="">Alle Status</option><?php endif; ?><option value="not_exported"<?= $selected('billing_status', 'not_exported') ?>>Nicht exportiert</option><option value="exported"<?= $selected('billing_status', 'exported') ?>>Exportiert / abgerechnet</option><option value="export_failed"<?= $selected('billing_status', 'export_failed') ?>>Export fehlgeschlagen</option><option value="manually_unexported"<?= $selected('billing_status', 'manually_unexported') ?>>Manuell zurückgesetzt</option></select></div>
        <?php if ($context === 'device'): ?>
        <div class="col-12 col-sm-6 col-md-3 col-xl-2"><label class="form-label"><i class="fa-solid fa-arrow-down-a-z me-1" aria-hidden="true"></i>Sortieren</label><select class="form-select" name="sort"><option value="name"<?= $selected('sort', 'name') ?>>Name</option><option value="manufacturer"<?= $selected('sort', 'manufacturer') ?>>Hersteller</option><option value="external_number"<?= $selected('sort', 'external_number') ?>>Gerätenummer</option><option value="room"<?= $selected('sort', 'room') ?>>Raum</option><option value="inspection_newest"<?= $selected('sort', 'inspection_newest') ?>>Prüfdatum (neueste)</option><option value="inspection_oldest"<?= $selected('sort', 'inspection_oldest') ?>>Prüfdatum (älteste)</option></select></div>
        <div class="col-6 col-sm-4 col-md-2 col-xl-1"><label class="form-label"><i class="fa-solid fa-list-ol me-1" aria-hidden="true"></i>Je Seite</label><select class="form-select" name="per_page"><option value="25"<?= $selected('per_page', '25') ?>>25</option><option value="50"<?= $selected('per_page', '50') ?>>50</option><option value="100"<?= $selected('per_page', '100') ?>>100</option><option value="200"<?= $selected('per_page', '200') ?>>200</option></select></div>
        <?php else: ?>
        <div class="col-12 col-sm-6 col-md-3 col-xl-2"><label class="form-label"><i class="fa-solid fa-arrow-down-a-z me-1" aria-hidden="true"></i>Sortieren</label><select class="form-select" name="sort"><option value="customer"<?= ((string) ($filters['sort'] ?? 'customer') === 'customer') ? ' selected' : '' ?>>Kunde, Prüfdatum</option><option value="test_date_desc"<?= $selected('sort', 'test_date_desc') ?>>Prüfdatum (neueste)</option><option value="test_date_asc"<?= $selected('sort', 'test_date_asc') ?>>Prüfdatum (älteste)</option><option value="inspection"<?= $selected('sort', 'inspection') ?>>Prüfnummer</option><option value="device"<?= $selected('sort', 'device') ?>>Gerätenummer</option><option value="billing_status"<?= $selected('sort', 'billing_status') ?>>Rechnungsstatus</option></select></div>
        <?php endif; ?>
        <div class="col-12 col-md-auto d-flex flex-wrap align-items-center gap-2"><button class="btn btn-primary" type="submit"><i class="fa-solid fa-filter me-1" aria-hidden="true"></i>Filtern</button><span id="<?= $id ?>-progress" class="htmx-indicator small text-body-secondary" role="status"><span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>Filter wird angewendet …</span><a class="btn btn-secondary" href="<?= htmlspecialchars($action, ENT_QUOTES) ?>" hx-get="<?= htmlspecialchars($action, ENT_QUOTES) ?>" hx-target="<?= $context === 'billing' ? '#billing-content' : '#device-page' ?>" hx-select="<?= $context === 'billing' ? '#billing-content' : '#device-page' ?>" hx-swap="outerHTML" hx-push-url="true"><i class="fa-solid fa-rotate-left me-1" aria-hidden="true"></i>Reset</a><?php if ($context === 'device'): ?><div class="btn-group" role="group" aria-label="Geräteansicht"><button type="button" class="btn btn-secondary" data-device-details-action="expand"><i class="fa-solid fa-angles-down me-1" aria-hidden="true"></i>Alle ausklappen</button><button type="button" class="btn btn-secondary" data-device-details-action="collapse"><i class="fa-solid fa-angles-up me-1" aria-hidden="true"></i>Alle einklappen</button></div><?php endif; ?></div>
      </div>
    </form>
    <?php
    return (string) ob_get_clean();
}
