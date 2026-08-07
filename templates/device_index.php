<?php
require_once dirname(__DIR__) . '/lib/filter_renderer.php';
$displayImportValue = static function ($value): string { if (is_array($value)) { foreach (['brezel_name', 'name', 'email', 'company'] as $key) if (isset($value[$key]) && is_scalar($value[$key])) return (string) $value[$key]; return implode(', ', array_map(static fn($item): string => is_scalar($item) ? (string) $item : '', $value)); } return is_scalar($value) ? (string) $value : ''; };
$form = static function ($device = null, string $newNumber = '') use ($rooms, $roomLabels, $manufacturerOptions, $modelOptionsByManufacturer, $nameOptionsByManufacturerModel, $suggestedDeviceNumber): void {
    $device ??= (object) ['id' => 0, 'name' => '', 'room_id' => 0, 'serial_number' => '', 'inventory_number' => '', 'device_model' => '', 'manufacturer' => '', 'warming_device' => 0, 'description' => '', 'comment' => '', 'metadata_json' => '{}'];
    $metadataValue = trim((string) ($device->metadata_json ?? ''));
    if ($metadataValue === '{}') $metadataValue = '';
    $formKey = (int) ($device->id ?? 0);
    $initialNumber = $formKey > 0 ? (string) ($device->external_number ?? '') : $newNumber;
    $manufacturerListId = 'manufacturers-' . $formKey;
    $modelListId = 'models-' . $formKey;
    $nameListId = 'device-names-' . $formKey;
    $currentManufacturer = trim((string) ($device->manufacturer ?? ''));
?>
  <form method="post" action="<?= htmlspecialchars(url_for('geraete'), ENT_QUOTES) ?>" class="row g-3 device-form">
    <input type="hidden" name="id" value="<?= $formKey ?>">

    <div class="col-12 device-form-heading"><h2 class="h5 border-bottom pb-2 mb-0">Identifikation</h2></div>
    <div class="col-md-4">
      <div class="d-flex justify-content-between align-items-center gap-2">
        <label class="form-label mb-1" for="external-number-<?= $formKey ?>"><i class="fa-solid fa-plug icon-slot me-1" aria-hidden="true"></i>Gerätenummer</label>
        <?php if ($formKey === 0 && $initialNumber === '' && $suggestedDeviceNumber !== ''): ?><button type="button" class="btn btn-link btn-sm p-0" data-suggest-device-number="<?= htmlspecialchars($suggestedDeviceNumber, ENT_QUOTES) ?>">Vorschlag</button><?php endif; ?>
      </div>
      <input class="form-control" id="external-number-<?= $formKey ?>" name="external_number" value="<?= htmlspecialchars($initialNumber) ?>"<?= $initialNumber !== '' ? ' readonly' : '' ?> required>
      <div class="form-text" data-number-check-hint></div>
    </div>
    <div class="col-md-4"><label class="form-label" for="inventory-number-<?= $formKey ?>"><i class="fa-solid fa-hashtag icon-slot me-1" aria-hidden="true"></i>Inventarnummer</label><input class="form-control" id="inventory-number-<?= $formKey ?>" name="inventory_number" value="<?= htmlspecialchars((string) $device->inventory_number) ?>"></div>
    <div class="col-md-4"><label class="form-label" for="serial-number-<?= $formKey ?>"><i class="fa-solid fa-hashtag icon-slot me-1" aria-hidden="true"></i>Seriennummer</label><input class="form-control" id="serial-number-<?= $formKey ?>" name="serial_number" value="<?= htmlspecialchars((string) $device->serial_number) ?>"></div>

    <div class="col-12 device-form-heading"><h2 class="h5 border-bottom pb-2 mb-0">Gerätedaten</h2></div>
    <div class="col-md-3"><label class="form-label" for="manufacturer-<?= $formKey ?>"><i class="fa-solid fa-tag icon-slot me-1" aria-hidden="true"></i>Hersteller</label><input class="form-control" id="manufacturer-<?= $formKey ?>" name="manufacturer" list="<?= $manufacturerListId ?>" autocomplete="organization" value="<?= htmlspecialchars($currentManufacturer) ?>"><datalist id="<?= $manufacturerListId ?>"><?php foreach ($manufacturerOptions as $manufacturer): ?><option value="<?= htmlspecialchars($manufacturer) ?>"></option><?php endforeach; ?></datalist></div>
    <div class="col-md-3"><label class="form-label" for="device-model-<?= $formKey ?>"><i class="fa-solid fa-tag icon-slot me-1" aria-hidden="true"></i>Typ / Modell</label><input class="form-control" id="device-model-<?= $formKey ?>" name="device_model" list="<?= $modelListId ?>" autocomplete="off" value="<?= htmlspecialchars((string) ($device->device_model ?? '')) ?>"><datalist id="<?= $modelListId ?>"><?php foreach (($modelOptionsByManufacturer[$currentManufacturer] ?? []) as $model): ?><option value="<?= htmlspecialchars($model) ?>"></option><?php endforeach; ?></datalist></div>
    <div class="col-md-6">
      <div class="d-flex justify-content-between align-items-center gap-2">
        <label class="form-label mb-1" for="device-name-<?= $formKey ?>"><i class="fa-solid fa-plug icon-slot me-1" aria-hidden="true"></i>Gerätebezeichnung</label>
        <button type="button" class="btn btn-link btn-sm p-0" data-copy-device-name><i class="fa-solid fa-floppy-disk me-1" aria-hidden="true"></i>passende Bezeichnung übernehmen</button>
      </div>
      <input class="form-control" id="device-name-<?= $formKey ?>" name="name" list="<?= $nameListId ?>" required value="<?= htmlspecialchars((string) $device->name) ?>"><datalist id="<?= $nameListId ?>"></datalist>
    </div>

    <div class="col-12 device-form-heading"><h2 class="h5 border-bottom pb-2 mb-0">Standort und Kennzeichnung</h2></div>
    <div class="col-md-8">
      <div class="d-flex justify-content-between align-items-center gap-2"><label class="form-label mb-1" for="device-room-<?= $formKey ?>"><i class="fa-solid fa-location-dot icon-slot me-1" aria-hidden="true"></i>Raum</label><a href="#" class="small d-none" data-last-room-link></a></div>
      <select class="form-select" id="device-room-<?= $formKey ?>" name="room_id" required data-search-select data-placeholder="Raum suchen"><option value="">Raum wählen</option><?php foreach ($rooms as $room): ?><option value="<?= (int) $room->id ?>"<?= (int) $device->room_id === (int) $room->id ? ' selected' : '' ?>><?= htmlspecialchars($roomLabels[(int) $room->id] ?? (string) $room->name) ?></option><?php endforeach; ?></select>
    </div>
    <div class="col-md-4"><div class="device-option-card form-check form-switch h-100"><input class="form-check-input" type="checkbox" role="switch" name="warming_device" id="warming-<?= $formKey ?>"<?= !empty($device->warming_device) ? ' checked' : '' ?>><label class="form-check-label d-flex align-items-center gap-2" for="warming-<?= $formKey ?>"><span class="device-option-icon rounded-circle d-inline-flex align-items-center justify-content-center" aria-hidden="true"><i class="fa-solid fa-temperature-high"></i></span><span><strong class="d-block">Wärmegerät</strong><small class="text-body-secondary">Heizelement vorhanden</small></span></label></div></div>

    <div class="col-12 device-form-heading"><h2 class="h5 border-bottom pb-2 mb-0">Zusätzliche Angaben</h2></div>
    <div class="col-12"><label class="form-label" for="device-description-<?= $formKey ?>"><i class="fa-solid fa-comment icon-slot me-1" aria-hidden="true"></i>Kurzbeschreibung</label><textarea class="form-control" id="device-description-<?= $formKey ?>" name="description" rows="2" maxlength="240" placeholder="Funktion, Bauart oder Einsatz des Geräts"><?= htmlspecialchars((string) $device->description) ?></textarea><div class="form-text">Wird direkt in der Geräteübersicht angezeigt, maximal 240 Zeichen.</div></div>
    <div class="col-md-6"><label class="form-label" for="device-comment-<?= $formKey ?>"><i class="fa-solid fa-comment icon-slot me-1" aria-hidden="true"></i>Kommentar</label><textarea class="form-control" id="device-comment-<?= $formKey ?>" name="comment" placeholder="Interne Hinweise und Bemerkungen"><?= htmlspecialchars((string) $device->comment) ?></textarea></div>
    <div class="col-md-6"><label class="form-label" for="device-metadata-<?= $formKey ?>"><i class="fa-solid fa-code icon-slot me-1" aria-hidden="true"></i>Metadaten (JSON-Objekt, optional)</label><textarea class="form-control font-monospace" id="device-metadata-<?= $formKey ?>" name="metadata_json" placeholder='z. B. {"kostenstelle":"1000"}'><?= htmlspecialchars($metadataValue) ?></textarea></div>
    <div class="col-12 text-end d-flex justify-content-end gap-2"><button class="btn btn-secondary btn-sm" name="save_only" value="1"><i class="fa-solid fa-floppy-disk me-1" aria-hidden="true"></i>Speichern</button><?php if ($formKey > 0): ?><a class="btn btn-primary btn-sm" href="<?= htmlspecialchars(url_for('geraete/' . $formKey . '/pruefungen/neu'), ENT_QUOTES) ?>"><i class="fa-solid fa-clipboard-check me-1" aria-hidden="true"></i>Neue Prüfung anlegen</a><?php else: ?><button class="btn btn-primary btn-sm" name="save_and_inspect" value="1"><i class="fa-solid fa-clipboard-check me-1" aria-hidden="true"></i>Speichern und neue Prüfung</button><?php endif; ?></div>
  </form>
<?php }; ?>

<div id="device-page">
<?php if ($canManage): ?>
<section id="device-inspection-lookup" class="card card-body mb-4 device-new-inspection" aria-labelledby="device-inspection-lookup-title">
  <div class="row g-3 align-items-end">
    <div class="col-lg-5">
      <h2 id="device-inspection-lookup-title" class="h5 mb-1"><i class="fa-solid fa-clipboard-check me-2" aria-hidden="true"></i>Neue Prüfung</h2>
      <p class="small text-body-secondary mb-0">Gerätenummer eingeben oder den Barcode mit einem angeschlossenen Scanner erfassen.</p>
    </div>
    <div class="col-lg-7">
      <label class="form-label fw-semibold" for="inspection-device-number"><i class="fa-solid fa-barcode icon-slot me-1" aria-hidden="true"></i>Gerätenummer oder Barcode</label>
      <div class="input-group">
        <span class="input-group-text" aria-hidden="true"><i class="fa-solid fa-barcode"></i></span>
        <input class="form-control" id="inspection-device-number" inputmode="text" enterkeyhint="search" autocomplete="off" placeholder="Gerätenummer scannen oder eingeben" autofocus>
        <button class="btn btn-primary" type="button" id="inspection-device-lookup-button"><i class="fa-solid fa-magnifying-glass me-1" aria-hidden="true"></i>Suchen</button>
      </div>
      <div id="inspection-device-result" class="small mt-2" aria-live="polite"></div>
    </div>
  </div>
</section>
<?php endif; ?>
<!-- device-common-filter is rendered by lib/filter_renderer.php -->
<?= render_common_filter_panel('device', $filters ?? [], compact('customers', 'sites', 'buildings', 'floors', 'rooms', 'roomLabels', 'examinerOptions')) ?>
<style>.common-filter-panel{container-type:inline-size}.common-filter-panel .form-label{font-weight:600}.common-filter-panel .form-control,.common-filter-panel .form-select{min-height:2.75rem}@media(max-width:767.98px){.common-filter-panel{padding:1rem}.common-filter-panel .row{--bs-gutter-y:.75rem}}</style>

<?php if (false): ?>
<form method="get" class="card card-body mb-4"><div class="row g-2 align-items-end"><div class="col-md-3"><label class="form-label"><i class="fa-solid fa-coins me-1" aria-hidden="true"></i>Abrechenbarkeit</label><select class="form-select" name="billing_eligibility"><option value="">Alle</option><option value="billable"<?= ($filters['billing_eligibility'] ?? '') === 'billable' ? ' selected' : '' ?>>Abrechenbar</option><option value="not_billable"<?= ($filters['billing_eligibility'] ?? '') === 'not_billable' ? ' selected' : '' ?>>Nicht abrechenbar</option></select></div><div class="col-md-3"><label class="form-label"><i class="fa-solid fa-file-invoice me-1" aria-hidden="true"></i>Rechnungsstatus</label><select class="form-select" name="billing_status"><option value="">Alle</option><?php foreach (['not_exported'=>'Nicht exportiert','exported'=>'Exportiert','export_failed'=>'Export fehlgeschlagen','manually_unexported'=>'Manuell zurückgesetzt'] as $value => $label): ?><option value="<?= $value ?>"<?= ($filters['billing_status'] ?? '') === $value ? ' selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select></div><div class="col-auto"><button class="btn btn-primary" type="submit"><i class="fa-solid fa-filter me-1" aria-hidden="true"></i>Abrechnungsfilter</button></div></div><div class="small text-body-secondary mt-2">Nur Prüfungen ab 2025 werden berücksichtigt; alte Prüfungen bleiben außerhalb der Abrechnung.</div></form>

<form method="get" class="card card-body mb-4"><div class="row g-2 align-items-end"><div class="col-md-3"><label class="form-label">Suche</label><input class="form-control" name="q" value="<?= htmlspecialchars((string) ($filters['q'] ?? '')) ?>" placeholder="Gerät, Nummer, Inventar, Kommentar"></div><div class="col-md-2"><label class="form-label">Firma/Kunde</label><select class="form-select" name="customer_id"><option value="0">Alle Firmen</option><?php foreach ($customers as $customer): ?><option value="<?= (int) $customer->id ?>"<?= (int) ($filters['customer_id'] ?? 0) === (int) $customer->id ? ' selected' : '' ?>><?= htmlspecialchars($customer->code ? $customer->code . ' · ' . $customer->name : $customer->name) ?></option><?php endforeach; ?></select></div><div class="col-md-2"><label class="form-label">Standort</label><select class="form-select" name="site_id"><option value="0">Alle Standorte</option><?php foreach ($sites as $site): ?><option value="<?= (int) $site->id ?>"<?= (int) ($filters['site_id'] ?? 0) === (int) $site->id ? ' selected' : '' ?>><?= htmlspecialchars($siteLabels[(int) $site->id] ?? (string) $site->name) ?></option><?php endforeach; ?></select></div><div class="col-md-2"><label class="form-label">Gebäude</label><select class="form-select" name="building_id"><option value="0">Alle Gebäude</option><?php foreach ($buildings as $building): ?><option value="<?= (int) $building->id ?>"<?= (int) ($filters['building_id'] ?? 0) === (int) $building->id ? ' selected' : '' ?>><?= htmlspecialchars($buildingLabels[(int) $building->id] ?? (string) $building->name) ?></option><?php endforeach; ?></select></div><div class="col-md-2"><label class="form-label">Etage</label><select class="form-select" name="floor_id"><option value="0">Alle Etagen</option><?php foreach ($floors as $floor): ?><option value="<?= (int) $floor->id ?>"<?= (int) ($filters['floor_id'] ?? 0) === (int) $floor->id ? ' selected' : '' ?>><?= htmlspecialchars($floorLabels[(int) $floor->id] ?? (string) $floor->name) ?></option><?php endforeach; ?></select></div><div class="col-md-2"><label class="form-label">Raum</label><select class="form-select" name="room_id" data-search-select data-placeholder="Raum suchen"><option value="0">Alle Räume</option><?php foreach ($rooms as $room): ?><option value="<?= (int) $room->id ?>"<?= (int) ($filters['room_id'] ?? 0) === (int) $room->id ? ' selected' : '' ?>><?= htmlspecialchars($roomLabels[(int) $room->id] ?? (string) $room->name) ?></option><?php endforeach; ?></select></div><div class="col-md-2"><label class="form-label">Jahr</label><input class="form-control" name="year" inputmode="numeric" value="<?= htmlspecialchars((string) ($filters['year'] ?? '')) ?>" placeholder="2024"></div><div class="col-md-2"><label class="form-label">Von</label><input class="form-control" type="date" name="from" value="<?= htmlspecialchars((string) ($filters['from'] ?? '')) ?>"></div><div class="col-md-2"><label class="form-label">Bis</label><input class="form-control" type="date" name="to" value="<?= htmlspecialchars((string) ($filters['to'] ?? '')) ?>"></div><div class="col-md-2"><label class="form-label">Sortieren</label><select class="form-select" name="sort"><option value="name"<?= ($filters['sort'] ?? '') === 'name' ? ' selected' : '' ?>>Name</option><option value="manufacturer"<?= ($filters['sort'] ?? '') === 'manufacturer' ? ' selected' : '' ?>>Hersteller</option><option value="external_number"<?= ($filters['sort'] ?? '') === 'external_number' ? ' selected' : '' ?>>Gerätenummer</option><option value="room"<?= ($filters['sort'] ?? '') === 'room' ? ' selected' : '' ?>>Raum</option><option value="inspection_newest"<?= ($filters['sort'] ?? '') === 'inspection_newest' ? ' selected' : '' ?>>Prüfdatum (neueste)</option><option value="inspection_oldest"<?= ($filters['sort'] ?? '') === 'inspection_oldest' ? ' selected' : '' ?>>Prüfdatum (älteste)</option><option value="id"<?= ($filters['sort'] ?? '') === 'id' ? ' selected' : '' ?>>ID</option></select></div><div class="col-md-1"><label class="form-label">Je Seite</label><select class="form-select" name="per_page"><option<?= (int) $filters['per_page'] === 25 ? ' selected' : '' ?>>25</option><option<?= (int) $filters['per_page'] === 50 ? ' selected' : '' ?>>50</option><option<?= (int) $filters['per_page'] === 100 ? ' selected' : '' ?>>100</option><option<?= (int) $filters['per_page'] === 200 ? ' selected' : '' ?>>200</option></select></div><div class="col-md-1 d-flex gap-2"><button class="btn btn-primary">Filtern</button><a class="btn btn-outline-secondary" href="<?= htmlspecialchars(url_for('geraete'), ENT_QUOTES) ?>">Reset</a></div></div></form>
<form method="get" class="card card-body mb-4"><div class="row g-2 align-items-end"><div class="col-md-4"><label class="form-label">Prüfstatus</label><select class="form-select" name="inspection_status"><option value="">Alle Prüfungen</option><option value="failed"<?= ($filters['inspection_status'] ?? '') === 'failed' ? ' selected' : '' ?>>Letzte Prüfung nicht bestanden</option><option value="pending"<?= ($filters['inspection_status'] ?? '') === 'pending' ? ' selected' : '' ?>>Prüfung nicht abgeschlossen</option></select></div><div class="col-auto"><button class="btn btn-primary">Status filtern</button></div></div></form><script>const deviceFilterForms=document.querySelectorAll('form[method="get"]');deviceFilterForms[0]?.addEventListener('submit',function(){const status=document.querySelector('select[name="inspection_status"]');if(status){const input=document.createElement('input');input.type='hidden';input.name='inspection_status';input.value=status.value;this.appendChild(input);}});deviceFilterForms[1]?.addEventListener('submit',function(){const first=deviceFilterForms[0];if(!first)return;first.querySelectorAll('input,select').forEach(field=>{if(field.name&&field.name!=='inspection_status'&&field.value!==''){const input=document.createElement('input');input.type='hidden';input.name=field.name;input.value=field.value;this.appendChild(input);}});});</script>
<?php endif; ?>
    <?php if ($canManage): ?><details id="device-new-panel" class="card device-card mb-4"><summary class="card-header device-card-summary d-flex align-items-center gap-2"><i class="fa-solid fa-plus-circle text-primary" aria-hidden="true"></i><strong>Neues Gerät</strong><span class="small text-body-secondary device-new-caption">Gerätedaten anlegen</span></summary><div class="card-body"><?php $form(null, (string) ($newNumber ?? '')); ?></div></details><?php endif; ?>
<style>
.device-form{--bs-gutter-y:1.25rem}.device-form-heading{margin-top:.5rem}.device-form .form-label{font-weight:600}.device-form .form-control,.device-form .form-select,.device-form .ts-control{min-height:44px}.device-form [data-copy-device-name]{font-size:.78rem;text-decoration:none}.device-form [data-copy-device-name]:hover{text-decoration:underline}.device-form .ts-wrapper{width:100%}.device-form .ts-control{padding:.65rem .75rem}.device-form textarea.form-control{min-height:90px}.device-form .col-12.text-end{padding-top:.5rem}.device-form .col-12.text-end .btn{min-height:44px}.vstack.gap-3>.card>summary{cursor:pointer}.vstack.gap-3>.card>summary:focus-visible{outline:3px solid var(--bs-primary);outline-offset:-3px}
.device-option-card{display:flex;align-items:center;gap:.65rem;padding:.7rem .8rem;border:1px solid var(--bs-border-color);border-radius:.65rem;background:var(--bs-tertiary-bg);min-height:44px}.device-option-card .form-check-input{float:none;margin:0}.device-option-card .form-check-label{cursor:pointer;flex:1}.device-option-icon{width:2rem;height:2rem;background:var(--bs-warning-bg-subtle);color:var(--bs-warning-text-emphasis)}
.device-export-toolbar{display:flex;flex-wrap:wrap;align-items:flex-end;gap:.65rem 1rem;padding:.35rem 0}.device-export-toolbar + .device-export-toolbar{border-top:1px solid var(--bs-border-color);margin-top:.5rem;padding-top:.85rem}.device-export-toolbar .btn{white-space:nowrap}.device-export-toolbar .btn-group-label{font-weight:600;color:var(--bs-secondary-color)}.device-export-toolbar .ts-wrapper{min-width:13rem}.device-export-toolbar .ts-control{min-height:2.25rem;white-space:nowrap;overflow:hidden}.device-export-toolbar .ts-control .item{max-width:100%;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.device-export-toolbar .btn-group>.btn:not(:last-child){border-right-color:rgba(255,255,255,.55)}
[data-bs-theme="dark"] .device-export-toolbar .btn-group>.btn:not(:last-child){border-right-color:rgba(0,0,0,.45)}
.device-new-inspection{scroll-margin-top:6rem}.device-new-inspection .input-group{width:100%}
details.card>summary.card-header{user-select:none;-webkit-user-select:none}.device-card-summary{min-height:3rem}.device-card>summary strong{font-size:1rem}.device-new-caption{margin-left:auto}.device-due-badge{margin-left:auto}.device-inspection-status{width:1.8rem;text-align:center}
@media(min-width:992px){.device-filter-form .row>.col-md-1.d-flex{width:auto;flex:0 0 auto;flex-wrap:wrap}}
@media(max-width:991.98px){.device-filter-form .row>[class*="col-"]{width:50%}.device-filter-form .row>.col-md-3:first-child{width:100%}.device-filter-form .row>.col-md-1.d-flex{width:100%}}
@media(max-width:767.98px){.device-filter-form,.device-status-filter{padding:1rem!important}.device-filter-form .row>[class*="col-"],.device-status-filter .row>[class*="col-"]{width:100%}.device-filter-form .row{--bs-gutter-y:.75rem}.device-filter-form .btn,.device-status-filter .btn{min-height:48px}.device-form .row>[class*="col-md-"]{width:100%}.device-form .device-form-heading{margin-top:1rem}.device-form .col-12.text-end{display:flex;flex-direction:column;gap:.5rem}.device-form .col-12.text-end .btn{width:100%}.device-form .form-check{margin-top:0!important;padding:1rem;border:1px solid var(--bs-border-color);border-radius:.5rem}.card-header{padding:1rem}.vstack.gap-3>.card{border-radius:.65rem}.table-responsive{margin-inline:-.25rem}.table-responsive table{min-width:680px}.ts-dropdown{max-height:50vh}.ts-dropdown-content{max-height:50vh}}
@media(max-width:575.98px){.device-filter-form .row,.device-status-filter .row{--bs-gutter-x:.65rem;--bs-gutter-y:.65rem}.device-form .device-form-heading h2{font-size:1.05rem}.device-form .form-control,.device-form .form-select,.device-form .ts-control{min-height:48px;font-size:1rem}.device-form .form-text{font-size:.8rem}.device-form [data-copy-device-name]{font-size:.72rem}.device-form .form-check{width:100%;margin-left:0!important}.device-form .col-12.text-end .btn{font-size:1rem}.device-status-filter{margin-bottom:1rem!important}}
</style>
<?php if ((int) ($selectedDeviceId ?? 0) > 0): ?><script>document.addEventListener('DOMContentLoaded',()=>{const device=document.getElementById('geraet-<?= (int) $selectedDeviceId ?>');if(!device)return;device.open=true;requestAnimationFrame(()=>device.scrollIntoView({behavior:'smooth',block:'start'}));});</script><?php endif; ?>
<?php $safeLabelMap = static fn(array $values): array => array_map(static fn($value): string => is_scalar($value) ? (string) $value : '', $values); $safeSiteLabels = $safeLabelMap($siteLabels); $safeBuildingLabels = $safeLabelMap($buildingLabels); $safeFloorLabels = $safeLabelMap($floorLabels); ?>
<script>
(() => {
  const siteCustomer = <?= json_encode($siteCustomerIds, JSON_UNESCAPED_UNICODE) ?>;
  const buildingSite = <?= json_encode($buildingSiteIds, JSON_UNESCAPED_UNICODE) ?>;
  const floorBuilding = <?= json_encode($floorBuildingIds, JSON_UNESCAPED_UNICODE) ?>;
  const siteLabels = <?= json_encode($safeSiteLabels, JSON_UNESCAPED_UNICODE) ?>;
  const buildingLabels = <?= json_encode($safeBuildingLabels, JSON_UNESCAPED_UNICODE) ?>;
  const customerLabels = <?= json_encode(array_reduce($customers, static function (array $out, $customer): array { $code = is_scalar($customer->code ?? null) ? (string) $customer->code : ''; $name = is_scalar($customer->name ?? null) ? (string) $customer->name : ''; $out[(int) $customer->id] = $code !== '' ? $code . ' · ' . $name : $name; return $out; }, []), JSON_UNESCAPED_UNICODE) ?>;
  const manufacturerOptions = <?= json_encode(array_values($manufacturerOptions), JSON_UNESCAPED_UNICODE) ?>;
  const modelOptionsByManufacturer = <?= json_encode($modelOptionsByManufacturer, JSON_UNESCAPED_UNICODE) ?>;
  const nameOptionsByManufacturerModel = <?= json_encode($nameOptionsByManufacturerModel, JSON_UNESCAPED_UNICODE) ?>;
  const normalizedModelOptions = Object.fromEntries(Object.entries(modelOptionsByManufacturer).map(([key, values]) => [key.trim().toLocaleLowerCase(), values]));
  document.querySelectorAll('form[action$="/geraete"]').forEach(form => {
    const manufacturer = form.querySelector('[name="manufacturer"]');
    const model = form.querySelector('[name="device_model"]');
    const modelList = model ? document.getElementById(model.getAttribute('list')) : null;
    if (manufacturer && model && typeof window.TomSelect === 'function' && !manufacturer.tomselect) {
      const manufacturerSelect = new window.TomSelect(manufacturer, {plugins: ['dropdown_input'], options: manufacturerOptions.map(value => ({value: String(value), text: String(value)})), create: true, createOnBlur: true, maxItems: 1, maxOptions: null, openOnFocus: true, selectOnTab: true, closeAfterSelect: true, placeholder: 'Hersteller suchen oder neu eingeben'});
      const modelSelect = new window.TomSelect(model, {plugins: ['dropdown_input'], create: true, createOnBlur: true, maxItems: 1, maxOptions: null, openOnFocus: true, selectOnTab: true, closeAfterSelect: true, placeholder: 'Modell suchen oder neu eingeben'});
      const refreshModels = () => { const typed = String(manufacturerSelect.getValue() || '').trim().toLocaleLowerCase(); const values = [...new Set(Object.entries(normalizedModelOptions).filter(([key]) => typed === '' || key === typed || key.startsWith(typed)).flatMap(([, models]) => Array.isArray(models) ? models : []).filter(value => typeof value === 'string' || typeof value === 'number').map(value => String(value)))].sort((a, b) => a.localeCompare(b, undefined, {numeric: true, sensitivity: 'base'})); modelSelect.clearOptions(); modelSelect.addOptions(values.map(value => ({value, text: value}))); modelSelect.refreshOptions(false); };
      manufacturerSelect.on('change', refreshModels);
      manufacturerSelect.on('type', refreshModels);
      refreshModels();
    }
    const copyNameButton = form.querySelector('[data-copy-device-name]');
    if (copyNameButton) copyNameButton.addEventListener('click', () => { const manufacturerValue = form.querySelector('[name="manufacturer"]')?.value.trim() || ''; const modelValue = form.querySelector('[name="device_model"]')?.value.trim() || ''; const nameField = form.querySelector('[name="name"]'); const suggestion = copyNameButton.dataset.suggestedName || [manufacturerValue, modelValue].filter(Boolean).join(' '); if (!nameField || !suggestion) return; if (nameField.value.trim() !== '' && !window.confirm('Gerätebezeichnung ersetzen?')) return; nameField.value = suggestion; nameField.dispatchEvent(new Event('input', {bubbles: true})); });
  });
  const refreshDeviceNameSuggestions = form => { const manufacturer = form.querySelector('[name="manufacturer"]'); const model = form.querySelector('[name="device_model"]'); const name = form.querySelector('[name="name"]'); const list = name ? document.getElementById(name.getAttribute('list')) : null; const button = form.querySelector('[data-copy-device-name]'); if (!manufacturer || !model || !name || !list) return; const key = `${manufacturer.value.trim().toLocaleLowerCase()}|${model.value.trim().toLocaleLowerCase()}`; const values = Array.isArray(nameOptionsByManufacturerModel[key]) ? nameOptionsByManufacturerModel[key] : []; list.replaceChildren(...values.map(value => { const option = document.createElement('option'); option.value = value; return option; })); if (button) { button.dataset.suggestedName = values[0] || ''; button.title = values.length ? `Vorschlag: ${values.join(', ')}` : 'Keine passende Bezeichnung aus vorhandenen Geräten'; } };
  document.querySelectorAll('form[action$="/geraete"]').forEach(form => { const manufacturer = form.querySelector('[name="manufacturer"]'); const model = form.querySelector('[name="device_model"]'); if (manufacturer && model) { manufacturer.addEventListener('input', () => refreshDeviceNameSuggestions(form)); manufacturer.addEventListener('change', () => refreshDeviceNameSuggestions(form)); model.addEventListener('input', () => refreshDeviceNameSuggestions(form)); model.addEventListener('change', () => refreshDeviceNameSuggestions(form)); refreshDeviceNameSuggestions(form); } });
  const initializeDeviceTomSelect = () => { if (typeof window.TomSelect !== 'function') return; document.querySelectorAll('form[action$="/geraete"]').forEach(form => { const manufacturer = form.querySelector('[name="manufacturer"]'); const model = form.querySelector('[name="device_model"]'); if (!manufacturer || !model || manufacturer.tomselect) return; const manufacturerSelect = new window.TomSelect(manufacturer, {plugins: ['dropdown_input'], options: manufacturerOptions.map(value => ({value: String(value), text: String(value)})), create: true, createOnBlur: true, maxItems: 1, maxOptions: null, openOnFocus: true, selectOnTab: true, closeAfterSelect: true, placeholder: 'Hersteller suchen oder neu eingeben'}); const modelSelect = new window.TomSelect(model, {plugins: ['dropdown_input'], create: true, createOnBlur: true, maxItems: 1, maxOptions: null, openOnFocus: true, selectOnTab: true, closeAfterSelect: true, placeholder: 'Modell suchen oder neu eingeben'}); const refreshModels = () => { const typed = String(manufacturerSelect.getValue() || '').trim().toLocaleLowerCase(); const values = [...new Set(Object.entries(normalizedModelOptions).filter(([key]) => typed === '' || key === typed || key.startsWith(typed)).flatMap(([, models]) => Array.isArray(models) ? models : []).filter(value => typeof value === 'string' || typeof value === 'number').map(value => String(value)))].sort((a, b) => a.localeCompare(b, undefined, {numeric: true, sensitivity: 'base'})); modelSelect.clearOptions(); modelSelect.addOptions(values.map(value => ({value, text: value}))); modelSelect.refreshOptions(false); }; manufacturerSelect.on('change', refreshModels); manufacturerSelect.on('type', refreshModels); refreshModels(); }); };
  if (typeof window.TomSelect === 'function') initializeDeviceTomSelect(); else window.addEventListener('DOMContentLoaded', initializeDeviceTomSelect, {once: true});
  const initializeDeviceNameTomSelect = () => { if (typeof window.TomSelect !== 'function') return; document.querySelectorAll('form[action$="/geraete"]').forEach(form => { const manufacturer = form.querySelector('[name="manufacturer"]'); const model = form.querySelector('[name="device_model"]'); const name = form.querySelector('[name="name"]'); if (!manufacturer || !model || !name || name.tomselect) return; const nameSelect = new window.TomSelect(name, {plugins: ['dropdown_input'], create: true, createOnBlur: true, maxItems: 1, maxOptions: null, openOnFocus: true, selectOnTab: true, closeAfterSelect: true, placeholder: 'Bezeichnung suchen oder neu eingeben'}); const refreshNames = () => { const key = `${String(manufacturer.value || '').trim().toLocaleLowerCase()}|${String(model.value || '').trim().toLocaleLowerCase()}`; const values = Array.isArray(nameOptionsByManufacturerModel[key]) ? nameOptionsByManufacturerModel[key].filter(value => typeof value === 'string' || typeof value === 'number').map(value => String(value)) : []; nameSelect.clearOptions(); nameSelect.addOptions(values.map(value => ({value, text: value}))); nameSelect.refreshOptions(false); const button = form.querySelector('[data-copy-device-name]'); if (button) button.dataset.suggestedName = values[0] || ''; }; manufacturer.addEventListener('change', refreshNames); model.addEventListener('change', refreshNames); refreshNames(); }); };
  if (typeof window.TomSelect === 'function') window.addEventListener('DOMContentLoaded', initializeDeviceNameTomSelect, {once: true}); else window.addEventListener('DOMContentLoaded', initializeDeviceNameTomSelect, {once: true});
  const initializeDeviceShortcuts = () => { const roomLabels = <?= json_encode($roomLabels, JSON_UNESCAPED_UNICODE) ?>; document.querySelectorAll('form[action$="/geraete"]').forEach(form => { const number = form.querySelector('[name="external_number"]'); const suggest = form.querySelector('[data-suggest-device-number]'); const hint = form.querySelector('[data-number-check-hint]'); if (suggest && number) suggest.addEventListener('click', () => { const value = suggest.dataset.suggestDeviceNumber || ''; if (!value) return; let count = 0; try { count = parseInt(localStorage.getItem('pruefapp-device-number-suggestions') || '0', 10) || 0; } catch (_) {} count++; if (count % 10 === 0) { const check = window.prompt('Bitte zur Kontrolle die letzten drei Stellen des vorgeschlagenen Werts eingeben: ' + value); if (check !== value.slice(-3)) { if (hint) hint.textContent = 'Vorschlag nicht übernommen – Abgleich fehlgeschlagen.'; return; } } number.value = value; number.dispatchEvent(new Event('input', {bubbles: true})); if (hint) hint.textContent = 'Vorschlag übernommen. Bitte vor dem Speichern prüfen.'; try { localStorage.setItem('pruefapp-device-number-suggestions', String(count)); } catch (_) {} }); const room = form.querySelector('[name="room_id"]'); const roomLink = form.querySelector('[data-last-room-link]'); if (!room) return; const rememberRoom = () => { if (!room.value) return; try { localStorage.setItem('pruefapp-last-room-id', room.value); } catch (_) {} }; room.addEventListener('change', rememberRoom); room.addEventListener('input', rememberRoom); if (roomLink && !room.value) { let lastRoom = ''; try { lastRoom = localStorage.getItem('pruefapp-last-room-id') || ''; } catch (_) {} const option = lastRoom ? room.querySelector(`option[value="${CSS.escape(lastRoom)}"]`) : null; if (option) { roomLink.textContent = `${option.textContent.trim()} eintragen`; roomLink.classList.remove('d-none'); roomLink.addEventListener('click', event => { event.preventDefault(); if (room.tomselect) room.tomselect.setValue(lastRoom); else { room.value = lastRoom; room.dispatchEvent(new Event('change', {bubbles: true})); } }); } } }); };
  window.addEventListener('DOMContentLoaded', initializeDeviceShortcuts, {once: true});
  const panel = document.getElementById('device-inspection-lookup');
  if (panel) {
    const numberInput = document.getElementById('inspection-device-number');
    const result = document.getElementById('inspection-device-result');
    const lookupButton = document.getElementById('inspection-device-lookup-button');
    const lookup = async () => {
      const number = numberInput.value.trim();
      if (!number) { result.textContent = ''; return; }
      result.textContent = 'Suche …';
      try {
        const response = await fetch(`<?= htmlspecialchars(url_for('geraete/suche'), ENT_QUOTES) ?>?number=${encodeURIComponent(number)}`, {headers:{Accept:'application/json'}});
        const data = await response.json();
        result.replaceChildren();
        if (data.found) { const text = document.createElement('span'); text.className = 'text-success'; text.textContent = `Vorhanden: ${data.number} · ${data.name}`; const link = document.createElement('a'); link.className = 'btn btn-sm btn-success ms-2'; link.href = data.url; link.innerHTML = '<i class="fa-solid fa-clipboard-check me-1" aria-hidden="true"></i>Prüfung anlegen'; result.append(text, link); }
        else { const text = document.createElement('span'); text.className = 'text-warning-emphasis'; text.textContent = 'Keine passende Gerätenummer gefunden.'; const link = document.createElement('a'); link.className = 'btn btn-sm btn-secondary ms-2'; link.href = `<?= htmlspecialchars(url_for('geraete'), ENT_QUOTES) ?>?new_number=${encodeURIComponent(number)}`; link.innerHTML = '<i class="fa-solid fa-plus me-1" aria-hidden="true"></i>Gerät neu anlegen'; result.append(text, link); }
      } catch (_) { result.textContent = 'Suche momentan nicht verfügbar.'; }
    };
    lookupButton.addEventListener('click', lookup);
    numberInput.addEventListener('input', () => { clearTimeout(numberInput._lookupTimer); numberInput._lookupTimer = setTimeout(lookup, 250); });
    numberInput.addEventListener('keydown', event => { if (event.key === 'Enter') { event.preventDefault(); lookup(); } });
    let scannerFocusTimer = 0;
    const focusScanner = () => {
      if (window.location.hash !== '#device-inspection-lookup') return;
      window.clearTimeout(scannerFocusTimer);
      scannerFocusTimer = window.setTimeout(() => {
        if (document.visibilityState === 'hidden') return;
        panel.scrollIntoView({block: 'start'});
        numberInput.focus({preventScroll: true});
        numberInput.select();
      }, 150);
    };
    window.addEventListener('hashchange', focusScanner);
    window.addEventListener('DOMContentLoaded', focusScanner);
    window.addEventListener('pageshow', focusScanner);
    window.addEventListener('load', focusScanner);
    document.querySelectorAll('a[href$="#device-inspection-lookup"]').forEach(link => link.addEventListener('click', event => {
      const target = new URL(link.href, window.location.href);
      if (target.pathname !== window.location.pathname) return;
      event.preventDefault();
      window.history.replaceState(null, '', '#device-inspection-lookup');
      const toggle = link.closest('.dropdown')?.querySelector('[data-bs-toggle="dropdown"]');
      if (toggle && window.bootstrap?.Dropdown) window.bootstrap.Dropdown.getOrCreateInstance(toggle).hide();
      focusScanner();
    }));
    document.addEventListener('shown.bs.collapse', focusScanner);
    focusScanner();
  }
  const newNumber = new URLSearchParams(window.location.search).get('new_number');
  if (newNumber) { const newDeviceDetails = [...document.querySelectorAll('details')].find(item => item.querySelector('summary')?.textContent.includes('Neues Gerät')); const field = newDeviceDetails?.querySelector('[name="external_number"]'); if (newDeviceDetails && field) { newDeviceDetails.open = true; field.value = newNumber; field.readOnly = true; newDeviceDetails.scrollIntoView({behavior:'smooth', block:'start'}); } }
  const group = (select, key, label) => {
    if (!select) return;
    const options = [...select.querySelectorAll(':scope > option')];
    const groups = new Map();
    options.forEach(option => {
      if (!option.value || option.value === '0') return;
      const id = Number(option.value), parent = key(id), title = label(parent);
      if (!groups.has(title)) groups.set(title, []);
      groups.get(title).push(option);
    });
    options.filter(option => !option.value || option.value === '0').forEach(option => select.append(option));
    groups.forEach((items, title) => { const optgroup = document.createElement('optgroup'); optgroup.label = title; items.forEach(option => optgroup.append(option)); select.append(optgroup); });
  };
  const siteSelect = document.querySelector('select[name="site_id"]');
  group(siteSelect, id => siteCustomer[id] || 0, id => customerLabels[id] || 'Ohne Firma');
  const buildingSelect = document.querySelector('select[name="building_id"]');
  group(buildingSelect, id => buildingSite[id] || 0, id => { const site = siteLabels[id] || 'Ohne Standort'; const customer = customerLabels[siteCustomer[id]] || 'Ohne Firma'; return customer + ' · ' + site; });
  const floorSelect = document.querySelector('select[name="floor_id"]');
  group(floorSelect, id => floorBuilding[id] || 0, id => { const building = buildingLabels[id] || 'Ohne Gebäude'; const site = siteLabels[buildingSite[id]] || 'Ohne Standort'; const customer = customerLabels[siteCustomer[buildingSite[id]]] || 'Ohne Firma'; return customer + ' · ' + site + ' · ' + building; });
})();
 </script>
<p class="small text-body-secondary"><?= (int) $total ?> Geräte · Seite <?= (int) $page ?> von <?= (int) $pages ?></p>
<?php if (!empty($zipJob)): ?><div id="zip-job-status" class="alert alert-info" data-status-url="<?= htmlspecialchars(url_for('geraete/zip/' . $zipJob . '/status'), ENT_QUOTES) ?>" data-download-url="<?= htmlspecialchars(url_for('geraete/zip/' . $zipJob . '/download'), ENT_QUOTES) ?>" data-cancel-url="<?= htmlspecialchars(url_for('geraete/zip/' . $zipJob . '/abbrechen'), ENT_QUOTES) ?>">Der Export wird vorbereitet …</div><script>(()=>{const box=document.getElementById('zip-job-status');const poll=()=>fetch(box.dataset.statusUrl,{headers:{Accept:'application/json'}}).then(r=>r.json()).then(s=>{if(s.state==='done'){box.className='alert alert-success';box.innerHTML='<strong>ZIP-Export fertig.</strong> <a class="alert-link" href="'+box.dataset.downloadUrl+'">ZIP herunterladen</a>';}else if(s.state==='error'||s.state==='cancelled'){box.className='alert alert-danger';box.textContent=s.state==='cancelled'?'Der ZIP-Export wurde abgebrochen.':'ZIP-Export fehlgeschlagen: '+(s.error||'unbekannter Fehler');}else{const progress=s.step&&s.total?' '+s.step+' von '+s.total+' PDFs verarbeitet.':'';box.textContent=(s.message||'Der Export läuft im Hintergrund.')+progress;if(s.can_cancel){const button=document.createElement('button');button.type='button';button.className='btn btn-sm btn-outline-danger ms-2';button.innerHTML='<i class="fa-solid fa-stop me-1"></i>Abbrechen';button.onclick=()=>fetch(box.dataset.cancelUrl,{method:'POST'}).then(()=>poll());box.appendChild(button);}setTimeout(poll,1500);}}).catch(()=>setTimeout(poll,3000));poll();})();</script><?php endif; ?>
<?php if (!empty($canManage) || !empty($canBulkManage)): ?><details id="device-actions-panel" class="card mb-3"><summary class="card-header fw-semibold">Auswahl &amp; Massenaktionen</summary><div class="card-body">
<?php if (!empty($canManage)): ?><form id="device-export-form" method="post" action="<?= htmlspecialchars(url_for('geraete/export'), ENT_QUOTES) ?>" class="card card-body mb-3">
  <div class="d-flex flex-wrap align-items-end gap-2">
    <div><label class="form-label mb-1" for="device-export-scope">Auswahl</label><select class="form-select form-select-sm" id="device-export-scope" name="scope" data-search-select data-placeholder="" aria-label="Auswahl"><option value="all" selected>Alle gefilterten Seiten</option><option value="selection">Markierte Geräte</option><option value="page">Ganze aktuelle Seite</option></select></div>
    <div><label class="form-label mb-1" for="device-export-report">Übersicht</label><select class="form-select form-select-sm" id="device-export-report" name="report" data-search-select data-placeholder="" aria-label="Übersicht"><option value="">Geräteliste</option><option value="rooms">Raum-Ampelreport</option><option value="daily">Tagesreport</option><option value="weekly">Wochenreport</option></select></div>
    <div class="report-period-field d-none"><label class="form-label mb-1" for="daily-date">Tag/Woche</label><input class="form-control form-control-sm" type="date" id="daily-date" name="daily_date" value="<?= htmlspecialchars((string) date('Y-m-d')) ?>"></div>
    <div class="report-period-field d-none"><label class="form-label mb-1" for="daily-examiner"><i class="fa-solid fa-user-pen me-1" aria-hidden="true"></i>Prüfer</label><select class="form-select form-select-sm" id="daily-examiner" name="daily_examiner"><option value="">Alle Prüfer</option><?php foreach (($examinerUsers ?? []) as $examinerUser): ?><option value="<?= htmlspecialchars((string) $examinerUser['value'], ENT_QUOTES) ?>" title="<?= htmlspecialchars((string) ($examinerUser['email'] ?? ''), ENT_QUOTES) ?>"><?= htmlspecialchars((string) $examinerUser['label']) ?><?php if (($examinerUser['email'] ?? '') !== '' && ($examinerUser['email'] ?? '') !== ($examinerUser['label'] ?? '')): ?> (<?= htmlspecialchars((string) $examinerUser['email']) ?>)<?php endif; ?></option><?php endforeach; ?></select></div>
    <div class="d-flex gap-2 align-items-center flex-wrap"><span class="small fw-semibold">ZIP-Inhaltsverzeichnis:</span><label class="small"><input type="checkbox" name="zip_index_csv" value="1" checked> CSV</label><label class="small"><input type="checkbox" name="zip_index_pdf" value="1" checked> PDF</label><label class="small"><input type="checkbox" name="zip_index_ods" value="1" checked> ODS</label></div>
    <button class="btn btn-sm btn-secondary" type="button" id="device-mark-page">Seite markieren</button><button class="btn btn-sm btn-secondary" type="button" id="device-unmark-page">Seite demarkieren</button><button class="btn btn-sm btn-secondary" type="button" id="device-mark-all">Alle Seiten markieren</button><button class="btn btn-sm btn-secondary" type="button" id="device-unmark-all">Alle Seiten demarkieren</button><label class="small d-flex align-items-center gap-1">Max. Seiten <input class="form-control form-control-sm" style="width:5rem" type="number" min="10" max="5000" name="bundle_max_pages" value="500"></label><button class="btn btn-sm btn-primary" name="format" value="csv">CSV</button><button class="btn btn-sm btn-primary" name="format" value="ods">ODS</button><button class="btn btn-sm btn-primary" name="format" value="xlsx">XLSX</button><button class="btn btn-sm btn-primary" name="format" value="pdf">PDF</button><button class="btn btn-sm btn-primary" name="format" value="json">JSON</button><button class="btn btn-sm btn-success" name="format" value="zip_latest">ZIP letzte PDFs</button><button class="btn btn-sm btn-success" name="format" value="zip_all">ZIP alle PDFs</button><button class="btn btn-sm btn-success" name="format" value="bundle_pdf">Sammel-PDF mit Inhaltsverzeichnis</button>
    <input type="hidden" name="filter_query" value="">
    <span class="small text-body-secondary">Die Auswahl berücksichtigt Filter und Sortierung.</span>
  </div>
</form><?php endif; ?>
<?php if (!empty($canBulkManage)): ?>
<form id="device-bulk-form" method="post" action="<?= htmlspecialchars(url_for('geraete/massenaktion'), ENT_QUOTES) ?>" class="card card-body mb-3">
  <div class="d-flex flex-wrap align-items-center gap-2">
    <strong class="me-2">Massenaktion</strong>
    <label class="small" for="device-bulk-scope">Auswahl <select class="form-select form-select-sm d-inline-block w-auto" id="device-bulk-scope" name="selection_scope" data-search-select data-placeholder="" aria-label="Massenaktions-Auswahl"><option value="selection" selected>Markierte Geräte</option><option value="all">Alle gefilterten Seiten</option><option value="page">Aktuelle Seite</option></select></label>
    <input type="hidden" name="filter_query" value="">
    <button class="btn btn-sm btn-outline-warning" name="bulk_action" value="archive">Archivieren</button>
    <button class="btn btn-sm btn-outline-danger" name="bulk_action" value="delete">Endgültig löschen</button>
    <button class="btn btn-sm btn-primary" name="bulk_action" value="billing"><i class="fa-solid fa-coins me-1" aria-hidden="true"></i>Abrechnung vorbereiten</button>
    <?php if (!empty($showArchived)): ?><a class="btn btn-sm btn-outline-secondary ms-2" href="<?= htmlspecialchars(url_for('geraete'), ENT_QUOTES) ?>">Nur aktive anzeigen</a><?php else: ?><a class="btn btn-sm btn-outline-secondary ms-2" href="<?= htmlspecialchars(url_for('geraete?show_archived=1'), ENT_QUOTES) ?>">Archivierte anzeigen</a><?php endif; ?>
    <span class="small text-body-secondary">Nur Prüfungen ab 2025 mit Status „nicht exportiert“ werden übernommen; wenn keine passt, erscheint ein Hinweis.</span>
  </div>
</form>
<?php endif; ?>
 </div></details><?php endif; ?>
<div class="vstack gap-3">
<?php foreach ($devices as $device): $deviceInspections = $inspections[(int) $device->id] ?? []; $latestInspection = $deviceInspections[0] ?? null; $nextDue = trim((string) ($latestInspection->next_due_date ?? '')); $badgeResultStatus = $latestInspection ? InspectionEvaluationService::statusForInspection($latestInspection) : ''; $inspectionPending = in_array($badgeResultStatus, [InspectionEvaluationService::IN_PROGRESS, InspectionEvaluationService::DATA_MISSING], true); $dueBadgeClass = 'text-bg-secondary'; $dueBadgeIcon = 'fa-calendar-check'; $dueBadgeLabel = 'Nächste Prüfung: nicht hinterlegt'; $dueBadgeDate = ''; if ($badgeResultStatus === InspectionEvaluationService::FAILED) { $dueBadgeClass = 'text-bg-danger'; $dueBadgeIcon = 'fa-triangle-exclamation'; $dueBadgeLabel = 'Nicht bestanden'; } elseif ($nextDue !== '') { try { $dueDate = new DateTimeImmutable($nextDue); $today = new DateTimeImmutable('today'); $daysUntilDue = (int) $today->diff($dueDate)->format('%r%a'); $dueBadgeClass = $daysUntilDue < 0 ? 'text-bg-danger' : ($badgeResultStatus !== InspectionEvaluationService::PASSED ? 'text-bg-warning' : ($daysUntilDue <= 30 ? 'text-bg-warning' : 'text-bg-success')); $dueBadgeDate = $dueDate->format('d.m.Y'); $dueBadgeLabel = ($badgeResultStatus === InspectionEvaluationService::PASSED ? 'Nächste Prüfung: ' : 'Prüfung ausstehend · nächster Termin: ') . $dueBadgeDate; } catch (Throwable) { $dueBadgeLabel = 'Nächste Prüfung: ungültiges Datum'; } } ?>
  <?php $roomLabel = $roomLabels[(int) $device->room_id] ?? ''; $roomLabel = $roomLabel !== '' ? $roomLabel : (trim((string) ($device->room_snapshot ?? '')) !== '' ? 'historischer Raum ' . (string) $device->room_snapshot : 'ohne Raum'); $inspectionStatusIcon = ''; $inspectionStatusClass = ''; $inspectionStatusLabel = ''; if ($badgeResultStatus === InspectionEvaluationService::FAILED) { $inspectionStatusIcon = 'fa-triangle-exclamation'; $inspectionStatusClass = 'text-bg-danger'; $inspectionStatusLabel = 'Letzte Prüfung nicht bestanden'; } elseif ($inspectionPending) { $inspectionStatusIcon = $badgeResultStatus === InspectionEvaluationService::DATA_MISSING ? 'fa-circle-exclamation' : 'fa-hourglass-half'; $inspectionStatusClass = $badgeResultStatus === InspectionEvaluationService::DATA_MISSING ? 'text-bg-warning text-dark' : 'text-bg-info text-dark'; $inspectionStatusLabel = $badgeResultStatus === InspectionEvaluationService::DATA_MISSING ? 'Bei der letzten Prüfung fehlen Daten' : 'Prüfung in Bearbeitung'; } ?>
  <details class="card device-card" id="geraet-<?= (int) $device->id ?>"><summary class="card-header device-card-summary d-flex align-items-center flex-wrap gap-2"><?php if (!empty($canManage)): ?><input class="form-check-input me-1" type="checkbox" name="device_ids[]" value="<?= (int) $device->id ?>"<?= !empty($canBulkManage) ? ' form="device-bulk-form"' : ' form="device-export-form"' ?> aria-label="Gerät <?= htmlspecialchars((string) ($device->external_number ?: $device->name), ENT_QUOTES) ?> auswählen" onclick="event.stopPropagation()"><?php endif; ?><span class="badge text-bg-secondary"><i class="fa-solid fa-id-card me-1" aria-hidden="true"></i><?= htmlspecialchars((string) ($device->external_number ?: 'ohne Nummer')) ?></span><strong><?= htmlspecialchars((string) $device->name) ?></strong><span class="text-body-secondary">· <?= htmlspecialchars($roomLabel) ?></span><span class="badge <?= htmlspecialchars($dueBadgeClass, ENT_QUOTES) ?> device-due-badge" title="<?= htmlspecialchars($dueBadgeLabel, ENT_QUOTES) ?>"><i class="fa-solid <?= htmlspecialchars($dueBadgeIcon, ENT_QUOTES) ?> me-1" aria-hidden="true"></i><?= htmlspecialchars($badgeResultStatus === InspectionEvaluationService::FAILED ? 'Nicht bestanden' : ($dueBadgeDate !== '' ? $dueBadgeDate : 'Prüfung offen')) ?></span><?php if ($inspectionStatusIcon !== ''): ?><span class="badge <?= $inspectionStatusClass ?> device-inspection-status" title="<?= htmlspecialchars($inspectionStatusLabel, ENT_QUOTES) ?>" aria-label="<?= htmlspecialchars($inspectionStatusLabel, ENT_QUOTES) ?>"><i class="fa-solid <?= $inspectionStatusIcon ?>" aria-hidden="true"></i></span><?php endif; ?><?php if (trim((string) $device->description) !== ''): ?><span class="w-100 small text-body-secondary"><?= htmlspecialchars((string) $device->description) ?></span><?php endif; ?></summary>
    <div class="card-body"><?php if ($canManage): ?><?php $form($device); else: ?><p><?= nl2br(htmlspecialchars((string) $device->comment)) ?></p><?php endif; ?>
      <?php if (trim((string) ($device->external_number ?? '')) !== ''): ?><p class="small text-body-secondary mb-2">Gerätenummer: <?= htmlspecialchars((string) ($device->external_number ?? '')) ?><?php if (trim((string) ($device->legacy_number ?? '')) !== ''): ?> · alte Nummer: <?= htmlspecialchars((string) $device->legacy_number) ?><?php endif; ?></p><?php endif; ?>
  <?php if ($deviceInspections): ?>
    <h2 class="h6 mt-3"><i class="fa-solid fa-clipboard-check me-2" aria-hidden="true"></i>Prüfungen</h2>
    <div class="table-responsive">
      <table class="table table-sm align-middle">
        <thead><tr><th>Prüfnummer</th><th>Datum</th><th>Prüfart</th><th>Prüfer</th><th>Nächste Prüfung</th><th>Ergebnis</th><th>Raum</th><th>Bericht</th></tr></thead>
        <tbody>
        <?php foreach ($deviceInspections as $inspection):
            $raw = json_decode((string) ($inspection->raw_json ?? ''), true) ?: [];
            $inspectionPresentation = InspectionEvaluationService::presentation((string) ($inspection->result_status ?? ''), (string) ($inspection->status ?? ''));
            $examinerLabel = display_examiner_name((string) ($inspection->examiner ?: $displayImportValue($raw['created_by'] ?? '—')));
            $reportAvailable = InspectionEvaluationService::reportPathAllowed(
                $inspectionPresentation['status'],
                (string) ($inspection->classification ?? ''),
                (string) ($inspection->report_path ?? '')
            );
        ?>
          <tr>
            <td><a href="<?= htmlspecialchars(url_for('pruefungen/' . (int) $inspection->id), ENT_QUOTES) ?>"><?= htmlspecialchars((string) ($inspection->external_number ?: '—')) ?></a></td>
            <td><?= htmlspecialchars((string) ($inspection->test_date ?: '—')) ?></td>
            <td><?= htmlspecialchars(InspectionEvaluationService::canonicalInspectionType((string) ($inspection->inspection_type ?: $displayImportValue($raw['type'] ?? '—')), (string) ($inspection->protection_class ?? ''))) ?></td>
            <td><?= htmlspecialchars($examinerLabel) ?></td>
            <td><?= htmlspecialchars((string) ($inspection->next_due_date ?: '—')) ?></td>
            <td><span class="badge text-bg-<?= htmlspecialchars($inspectionPresentation['class'], ENT_QUOTES) ?>"><i class="fa-solid <?= htmlspecialchars($inspectionPresentation['icon'], ENT_QUOTES) ?> me-1" aria-hidden="true"></i><?= htmlspecialchars($inspectionPresentation['label']) ?></span></td>
            <td><?= htmlspecialchars((string) ($inspection->room_snapshot ?: '—')) ?></td>
            <td><?php if ($reportAvailable): ?><a href="<?= htmlspecialchars($inspectionReportUrl((int) $inspection->id)) ?>" target="_blank" rel="noopener"><i class="fa-solid fa-file-pdf me-1" aria-hidden="true"></i>PDF</a><?php else: ?>—<?php endif; ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
    </div>
  </details>
<?php endforeach; ?>
</div>
<script>
(() => {
  const actions = document.getElementById('device-actions-panel');
  const newPanel = document.getElementById('device-new-panel');
  if (actions && newPanel) newPanel.before(actions);
  const form = document.getElementById('device-export-form');
  const toolbar = form?.querySelector(':scope > .d-flex');
  if (!toolbar || toolbar.dataset.grouped === '1') return;
  toolbar.dataset.grouped = '1';
  const groups = [document.createElement('div'), document.createElement('div'), document.createElement('div')];
  groups.forEach(group => group.className = 'device-export-toolbar');
  [...toolbar.children].forEach((node, index) => {
    const isMarkButton = node.matches?.('#device-mark-page, #device-unmark-page, #device-mark-all, #device-unmark-all');
    const isPageLimit = node.querySelector?.('[name="bundle_max_pages"]');
    const target = index < 4 || node.querySelector?.('[name="zip_index_csv"]') ? groups[0] : (isMarkButton || isPageLimit ? groups[1] : groups[2]);
    target.append(node);
  });
  toolbar.replaceChildren(...groups.filter(group => group.children.length));
  toolbar.querySelectorAll(':scope > .device-export-toolbar').forEach((bar, index) => {
    const buttons = [...bar.querySelectorAll(':scope > button')];
    if (buttons.length < 2) return;
    const group = document.createElement('div');
    group.className = 'btn-group'; group.setAttribute('role', 'group');
    group.setAttribute('aria-label', index === 1 ? 'Seitenauswahl' : 'Exportformate');
    buttons[0].before(group); buttons.forEach(button => group.append(button));
    if (index === 2) {
      const dropdown = document.createElement('div'); dropdown.className = 'dropdown';
      const toggle = document.createElement('button'); toggle.type = 'button'; toggle.className = 'btn btn-primary btn-sm dropdown-toggle'; toggle.setAttribute('data-bs-toggle', 'dropdown'); toggle.setAttribute('aria-expanded', 'false'); toggle.innerHTML = '<i class="fa-solid fa-file-export me-1" aria-hidden="true"></i>Exportieren';
      const menu = document.createElement('div'); menu.className = 'dropdown-menu';
      const icons = {csv: 'fa-file-csv', ods: 'fa-file-lines', xlsx: 'fa-file-excel', pdf: 'fa-file-pdf', json: 'fa-file-code', zip_latest: 'fa-file-zipper', zip_all: 'fa-file-zipper', bundle_pdf: 'fa-file-pdf'};
      [...group.children].forEach(button => { const icon = document.createElement('i'); icon.className = `fa-solid ${icons[button.value] || 'fa-file-export'} me-2`; icon.setAttribute('aria-hidden', 'true'); button.className = 'dropdown-item'; button.prepend(icon); menu.append(button); });
      dropdown.append(toggle, menu); group.replaceWith(dropdown);
    }
  });
})();
</script>
<?php if ($pages > 1): ?><?php $pageQuery = array_filter($filters ?? [], static fn($value): bool => trim((string) $value) !== ''); ?><nav aria-label="Geräteseiten" class="mt-4"><ul class="pagination justify-content-center flex-wrap"><li class="page-item<?= $page <= 1 ? ' disabled' : '' ?>"><a class="page-link" href="<?= htmlspecialchars(url_for('geraete?' . http_build_query(array_merge($pageQuery, ['page' => max(1, $page - 1)]))), ENT_QUOTES) ?>">Zurück</a></li><?php for ($number = 1; $number <= $pages; $number++): ?><?php if ($number === 1 || $number === $pages || abs($number - $page) <= 2): ?><li class="page-item<?= $number === $page ? ' active' : '' ?>"><a class="page-link" href="<?= htmlspecialchars(url_for('geraete?' . http_build_query(array_merge($pageQuery, ['page' => $number]))), ENT_QUOTES) ?>"><?= $number ?></a></li><?php elseif ($number === 2 || $number === $pages - 1): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif; ?><?php endfor; ?><li class="page-item<?= $page >= $pages ? ' disabled' : '' ?>"><a class="page-link" href="<?= htmlspecialchars(url_for('geraete?' . http_build_query(array_merge($pageQuery, ['page' => min($pages, $page + 1)]))), ENT_QUOTES) ?>">Weiter</a></li></ul></nav><?php endif; ?>
<?php if (!empty($canManage)): ?><script>
(() => {
  const boxes = [...document.querySelectorAll('input[name="device_ids[]"]')];
  const selectionParams = new URLSearchParams(window.location.search); selectionParams.delete('page');
  const selectionKey = 'pruefapp-device-selection:' + window.location.pathname + '?' + selectionParams.toString();
  let persisted = <?= json_encode($selectedDeviceIds ?? [], JSON_UNESCAPED_UNICODE) ?>.map(Number).filter(Number.isInteger); try { persisted = [...new Set([...persisted, ...JSON.parse(sessionStorage.getItem(selectionKey) || '[]').map(Number)])].filter(Number.isInteger); } catch (_) {}
  boxes.forEach(box => { if (persisted.includes(Number(box.value))) box.checked = true; });
  const syncServer = (id, checked) => fetch('<?= htmlspecialchars(url_for('geraete/auswahl'), ENT_QUOTES) ?>', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded', 'X-Requested-With':'XMLHttpRequest'}, body:new URLSearchParams({id:String(id), action:checked?'add':'remove'})}).catch(()=>{});
  const persistSelection = () => { const currentIds = boxes.map(box => Number(box.value)); persisted = [...new Set([...persisted.filter(id => !currentIds.includes(id)), ...boxes.filter(box => box.checked).map(box => Number(box.value))].filter(Number.isInteger))]; try { sessionStorage.setItem(selectionKey, JSON.stringify(persisted)); } catch (_) {} boxes.forEach(box => syncServer(Number(box.value), box.checked)); };
  const bulkForm = document.getElementById('device-bulk-form');
  bulkForm?.addEventListener('submit', event => {
    const scope = bulkForm.querySelector('[name="selection_scope"]')?.value || 'all';
    const filterInput = bulkForm.querySelector('[name="filter_query"]');
    if (filterInput) filterInput.value = window.location.search;
    if (scope === 'page') {
      boxes.filter(box => box.checked).forEach(box => { const input = document.createElement('input'); input.type = 'hidden'; input.name = 'device_ids[]'; input.value = box.value; input.dataset.generatedBulkId = '1'; bulkForm.appendChild(input); });
    } else if (scope === 'selection') {
      [...new Set([...persisted, ...boxes.filter(box => box.checked).map(box => Number(box.value))])].forEach(id => { const input = document.createElement('input'); input.type = 'hidden'; input.name = 'device_ids[]'; input.value = id; input.dataset.generatedBulkId = '1'; bulkForm.appendChild(input); });
    }
    const selected = scope === 'all' ? 'alle gefilterten Geräte' : boxes.filter(box => box.checked).length;
    if (!selected) {
      event.preventDefault();
      window.alert('Bitte mindestens ein Gerät auswählen.');
      return;
    }
    const action = event.submitter?.value || '';
    const noun = selected === 1 ? 'Gerät' : 'Geräte';
    const text = action === 'delete'
      ? selected + ' ' + noun + ' und alle zugehörigen Prüfungen endgültig löschen?'
      : selected + ' ' + noun + ' wirklich archivieren?';
    if (!window.confirm(scope === 'all' ? 'Alle gefilterten Geräte wirklich bearbeiten?' : text)) event.preventDefault();
  });
  let lastIndex = -1;
  boxes.forEach((box, index) => box.addEventListener('click', event => {
    if (event.shiftKey && lastIndex >= 0) {
      const start = Math.min(lastIndex, index);
      const end = Math.max(lastIndex, index);
      boxes.slice(start, end + 1).forEach(item => { item.checked = box.checked; });
    }
    lastIndex = index;
    persistSelection();
  }));
  document.getElementById('device-mark-page')?.addEventListener('click', () => { boxes.forEach(box => { box.checked = true; }); persistSelection(); });
  document.getElementById('device-unmark-page')?.addEventListener('click', () => { boxes.forEach(box => { box.checked = false; }); persisted = persisted.filter(id => !boxes.some(box => Number(box.value) === id)); persistSelection(); });
  document.getElementById('device-mark-all')?.addEventListener('click', () => { const scope = document.getElementById('device-export-scope'); if (scope) scope.value = 'all'; boxes.forEach(box => { box.checked = true; }); persistSelection(); });
  document.getElementById('device-unmark-all')?.addEventListener('click', () => { const scope = document.getElementById('device-export-scope'); if (scope) scope.value = 'selection'; boxes.forEach(box => { box.checked = false; }); persisted = []; try { sessionStorage.removeItem(selectionKey); } catch (_) {} fetch('<?= htmlspecialchars(url_for('geraete/auswahl'), ENT_QUOTES) ?>', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:new URLSearchParams({action:'clear'})}); });
  const exportForm = document.getElementById('device-export-form');
  const reportSelect = document.getElementById('device-export-report');
  const periodFields = [...document.querySelectorAll('.report-period-field')];
  const updateReportFields = () => { const visible = ['daily', 'weekly'].includes(reportSelect?.value || ''); periodFields.forEach(field => field.classList.toggle('d-none', !visible)); };
  reportSelect?.addEventListener('change', updateReportFields); updateReportFields();
  exportForm?.addEventListener('submit', event => {
    exportForm.querySelectorAll('[data-generated-export-id]').forEach(node => node.remove());
    const scope = exportForm.querySelector('[name="scope"]')?.value || 'selection';
    if (scope !== 'all') [...new Set(scope === 'page' ? boxes.map(box => Number(box.value)) : [...persisted, ...boxes.filter(box => box.checked).map(box => Number(box.value))])].forEach(id => {
      const input = document.createElement('input');
      input.type = 'hidden'; input.name = 'device_ids[]'; input.value = id; input.dataset.generatedExportId = '1';
      exportForm.appendChild(input);
    });
    const query = exportForm.querySelector('[name="filter_query"]');
    if (query) query.value = window.location.search;
    if (scope === 'page') boxes.forEach(box => { const input = document.createElement('input'); input.type = 'hidden'; input.name = 'page_ids[]'; input.value = box.value; input.dataset.generatedExportId = '1'; exportForm.appendChild(input); });
  });
})();
</script><?php endif; ?>
<script>
document.querySelectorAll('.device-card').forEach(card => {
  const summary = card.querySelector('summary');
  const firstDate = card.querySelector('table tbody tr td:nth-child(2)')?.textContent.trim() || '';
  if (!summary || firstDate < '2025-01-01' || summary.querySelector('[data-billing-badge]')) return;
  const badge = document.createElement('span'); badge.dataset.billingBadge = '1'; badge.className = 'badge text-bg-warning'; badge.title = 'Abrechnungsstatus der neuesten Prüfung'; badge.innerHTML = '<i class="fa-solid fa-euro-sign" aria-hidden="true"></i><span class="visually-hidden"> Abrechnung prüfen</span>'; summary.appendChild(badge);
});
</script>
<script>
document.addEventListener('click', event => {
  const link = event.target.closest('#device-page .pagination a');
  if (!link || !window.htmx) return;
  event.preventDefault();
  htmx.ajax('GET', link.href, {target: '#device-page', select: '#device-page', swap: 'outerHTML', pushUrl: true});
});
</script>
</div>
