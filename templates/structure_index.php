<?php
/** @var bool $canManage */

$byId = static function (array $beans): array {
    $result = [];
    foreach ($beans as $bean) $result[(int) $bean->id] = $bean;
    return $result;
};
$customersById = $byId($customers);
$sitesById = $byId($sites);
$buildingsById = $byId($buildings);
$floorsById = $byId($floors);
$areasById = $byId($areas);

$labelWithCode = static function ($bean): string {
    $name = (string) ($bean->name ?? '');
    $code = trim((string) ($bean->code ?? ''));
    return $code === '' ? $name : $name . ' (' . $code . ')';
};
$contextToken = static function ($bean): string {
    $code = trim((string) ($bean->code ?? ''));
    return $code !== '' ? $code : (string) ($bean->name ?? '');
};

$filterContext = static function (string $type, $item) use (
    $customersById, $sitesById, $buildingsById, $floorsById
): array {
    $customerId = $siteId = $buildingId = $floorId = 0;
    if ($type === 'customer') {
        $customerId = (int) $item->id;
    } elseif ($type === 'site') {
        $siteId = (int) $item->id;
        $customerId = (int) $item->customer_id;
    } else {
        if ($type === 'building') {
            $building = $item;
        } else {
            $floor = $type === 'floor' ? $item : ($floorsById[(int) $item->floor_id] ?? null);
            $floorId = (int) ($floor->id ?? 0);
            $building = $buildingsById[(int) ($floor->building_id ?? 0)] ?? null;
        }
        $buildingId = (int) ($building->id ?? 0);
        $siteId = (int) ($building->site_id ?? 0);
        $customerId = (int) ($sitesById[$siteId]->customer_id ?? 0);
    }
    $searchParts = [
        (string) ($item->name ?? ''), (string) ($item->code ?? ''),
        (string) ($item->number ?? ''), (string) ($item->description ?? ''), (string) ($item->comment ?? ''),
        (string) ($customersById[$customerId]->name ?? ''),
        (string) ($customersById[$customerId]->code ?? ''),
        (string) ($sitesById[$siteId]->name ?? ''),
        (string) ($sitesById[$siteId]->code ?? ''),
        (string) ($buildingsById[$buildingId]->name ?? ''),
        (string) ($buildingsById[$buildingId]->code ?? ''),
        (string) ($floorsById[$floorId]->name ?? ''),
    ];
    return [
        'customer' => $customerId, 'site' => $siteId,
        'building' => $buildingId, 'floor' => $floorId,
        'search' => strtolower(implode(' ', $searchParts)),
    ];
};
$filterAttributes = static function (string $type, $item) use ($filterContext): string {
    $context = $filterContext($type, $item);
    return sprintf(
        'data-type="%s" data-customer="%d" data-site="%d" data-building="%d" data-floor="%d" data-search="%s"',
        htmlspecialchars($type, ENT_QUOTES),
        $context['customer'], $context['site'], $context['building'], $context['floor'],
        htmlspecialchars($context['search'], ENT_QUOTES)
    );
};

$optionLabel = static function ($bean, string $type) use (
    $customersById, $sitesById, $buildingsById, $labelWithCode, $contextToken
): string {
    if ($type === 'customer') return $labelWithCode($bean);
    if ($type === 'site') {
        $customer = $customersById[(int) $bean->customer_id] ?? null;
        return ($customer ? $contextToken($customer) . ' · ' : '') . $labelWithCode($bean);
    }
    if ($type === 'building') {
        $site = $sitesById[(int) $bean->site_id] ?? null;
        $customer = $site ? ($customersById[(int) $site->customer_id] ?? null) : null;
        return ($customer ? $contextToken($customer) . ' · ' : '')
            . ($site ? $contextToken($site) . ' · ' : '')
            . $labelWithCode($bean);
    }
    if ($type === 'floor') {
        $building = $buildingsById[(int) $bean->building_id] ?? null;
        $site = $building ? ($sitesById[(int) $building->site_id] ?? null) : null;
        $customer = $site ? ($customersById[(int) $site->customer_id] ?? null) : null;
        $buildingLabel = ($customer ? $contextToken($customer) . ' · ' : '')
            . ($site ? $contextToken($site) . ' · ' : '');
        return $buildingLabel . StructureController::floorIdentifier($bean, $building);
    }
    if ($type === 'area') return $labelWithCode($bean);
    return (string) $bean->name;
};

$form = static function (string $type, $entity = null) use (
    $customers, $sites, $buildings, $floors, $areas, $optionLabel
): void {
    $entity ??= (object) [
        'id' => 0, 'name' => '', 'description' => '', 'comment' => '', 'metadata_json' => '{}',
        'parent_customer_id' => 0, 'customer_id' => 0, 'site_id' => 0,
        'building_id' => 0, 'floor_id' => 0, 'area_id' => 0,
        'code' => '', 'sort_order' => '', 'number' => '',
        'room_code_pattern' => '',
    ];
    $config = [
        'customer' => ['route' => 'struktur/kunden', 'parent' => 'parent_customer_id', 'parents' => $customers, 'prompt' => 'Kein Unterkunde', 'parent_type' => 'customer', 'parent_label' => 'Übergeordneter Kunde'],
        'site' => ['route' => 'struktur/standorte', 'parent' => 'customer_id', 'parents' => $customers, 'prompt' => 'Kunde wählen', 'parent_type' => 'customer', 'parent_label' => 'Kunde'],
        'building' => ['route' => 'struktur/gebaeude', 'parent' => 'site_id', 'parents' => $sites, 'prompt' => 'Standort wählen', 'parent_type' => 'site', 'parent_label' => 'Standort'],
        'floor' => ['route' => 'struktur/etagen', 'parent' => 'building_id', 'parents' => $buildings, 'prompt' => 'Gebäude wählen', 'parent_type' => 'building', 'parent_label' => 'Gebäude'],
        'area' => ['route' => 'struktur/bereiche', 'parent' => 'floor_id', 'parents' => $floors, 'prompt' => 'Etage wählen', 'parent_type' => 'floor', 'parent_label' => 'Etage'],
        'room' => ['route' => 'struktur/raeume', 'parent' => 'floor_id', 'parents' => $floors, 'prompt' => 'Etage wählen', 'parent_type' => 'floor', 'parent_label' => 'Etage'],
    ][$type];
?>
<form method="post" action="<?= htmlspecialchars(url_for($config['route']), ENT_QUOTES) ?>" class="row g-2">
  <input type="hidden" name="id" value="<?= (int) $entity->id ?>">
  <?php if ($type !== 'floor'): ?>
    <div class="col-md-6"><label class="form-label">Name</label><input class="form-control" name="name" required value="<?= htmlspecialchars((string) $entity->name) ?>"></div>
  <?php else: ?>
    <div class="col-md-6"><label class="form-label">Etagenname</label><input class="form-control" value="<?= htmlspecialchars((string) $entity->name) ?>" placeholder="Wird aus Gebäude- und Etagenkürzel gebildet" disabled><div class="form-text">Beispiel: Gebäude <code>AB</code> und Etage <code>0</code> ergeben <code>AB0</code>.</div></div>
  <?php endif; ?>
  <div class="col-md-6">
    <label class="form-label"><?= htmlspecialchars($config['parent_label']) ?></label>
    <select class="form-select" name="<?= $config['parent'] ?>"<?= $type === 'customer' ? '' : ' required' ?>>
      <option value="0"><?= $config['prompt'] ?></option>
      <?php foreach ($config['parents'] as $parent): if ((int) $parent->id === (int) $entity->id && $type === 'customer') continue; ?>
        <option value="<?= (int) $parent->id ?>"<?= (int) $entity->{$config['parent']} === (int) $parent->id ? ' selected' : '' ?>><?= htmlspecialchars($optionLabel($parent, $config['parent_type'])) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <?php if (in_array($type, ['customer', 'site', 'building', 'floor', 'area'], true)): ?>
    <?php $codeLabel = ['customer' => 'Kundenkürzel', 'site' => 'Standortkürzel', 'building' => 'Gebäudekürzel', 'floor' => 'Etagenkürzel', 'area' => 'Bereichskürzel'][$type]; ?>
    <div class="col-md-6"><label class="form-label"><?= $codeLabel ?></label><input class="form-control text-uppercase" name="code"<?= in_array($type, ['building', 'floor', 'area'], true) ? ' required' : '' ?> placeholder="<?= $type === 'building' ? 'z. B. AB' : ($type === 'floor' ? 'U, E, 0, 1 …' : ($type === 'area' ? 'E, F …' : 'optional')) ?>" value="<?= htmlspecialchars((string) $entity->code) ?>"></div>
  <?php endif; ?>
  <?php if ($type === 'floor'): ?>
    <div class="col-md-6"><label class="form-label">Sortierreihenfolge</label><input type="number" class="form-control" name="sort_order" placeholder="U automatisch vor E" value="<?= htmlspecialchars((string) $entity->sort_order) ?>"></div>
  <?php elseif ($type === 'room'): ?>
    <div class="col-md-6"><label class="form-label">Raumnummer</label><input class="form-control" name="number" required placeholder="z. B. 07, 10 oder 24" value="<?= htmlspecialchars((string) ($entity->number ?: $entity->name)) ?>"></div>
    <div class="col-md-6"><label class="form-label">Bereich</label><select class="form-select" name="area_id"><option value="0">Kein Bereich</option><?php foreach ($areas as $area): ?><option value="<?= (int) $area->id ?>"<?= (int) $entity->area_id === (int) $area->id ? ' selected' : '' ?>><?= htmlspecialchars($optionLabel($area, 'area')) ?></option><?php endforeach; ?></select></div>
  <?php endif; ?>
  <?php if ($type === 'customer' || $type === 'floor'): ?>
    <div class="col-12">
      <label class="form-label">Muster für Raumkennungen</label>
      <input class="form-control font-monospace" name="room_code_pattern" placeholder="<?= $type === 'customer' ? 'Raumkennung: auto oder z. B. {building}{floor}{room}' : 'Optional: Muster des Kunden überschreiben' ?>" value="<?= htmlspecialchars((string) $entity->room_code_pattern) ?>">
      <div class="form-text"><code>auto</code> erzeugt z. B. <code>1.24</code>, mit Bereich <code>E10</code> und im Untergeschoss <code>NU07</code>. Muster wie <code>{building}{floor}{room}</code> erzeugen auch <code>K181</code>.</div>
    </div>
  <?php endif; ?>
  <div class="col-12"><label class="form-label">Kurzbeschreibung</label><textarea class="form-control" name="description" rows="2" maxlength="240" placeholder="Kurze fachliche Beschreibung des Eintrags"><?= htmlspecialchars((string) $entity->description) ?></textarea><div class="form-text">Wird direkt in der Strukturübersicht angezeigt, maximal 240 Zeichen.</div></div>
  <div class="col-md-6"><label class="form-label">Kommentar</label><textarea class="form-control" name="comment" placeholder="Interne Hinweise und Bemerkungen"><?= htmlspecialchars((string) $entity->comment) ?></textarea></div>
  <div class="col-md-6"><label class="form-label">Metadaten (JSON-Objekt, optional)</label><textarea class="form-control font-monospace" name="metadata_json" placeholder='z. B. {"kostenstelle":"1000"}'><?= htmlspecialchars((string) ($entity->metadata_json ?: '{}')) ?></textarea></div>
  <div class="col-12 text-end"><button class="btn btn-primary btn-sm">Speichern</button></div>
</form>
<?php };

$section = static function (string $title, string $type, array $items) use ($form, $canManage, $filterAttributes): void {
?>
<div class="card shadow-sm h-100">
  <div class="card-header d-flex justify-content-between"><h2 class="h5 mb-0"><?= htmlspecialchars($title) ?></h2><span class="badge text-bg-secondary" data-structure-count="<?= $type ?>"><?= count($items) ?></span></div>
  <div class="card-body">
    <?php if ($canManage): ?><details class="border rounded p-2 mb-3"><summary class="fw-semibold">Neu anlegen</summary><div class="pt-3"><?php $form($type); ?></div></details><?php endif; ?>
    <div class="vstack gap-2">
      <?php foreach ($items as $item): ?>
        <details class="border rounded p-2 structure-filter-item" <?= $filterAttributes($type, $item) ?>>
          <summary>
            <strong><?= htmlspecialchars((string) $item->name) ?></strong><?php if (!empty($item->code)): ?> <span class="badge text-bg-secondary"><?= htmlspecialchars((string) $item->code) ?></span><?php endif; ?>
            <?php if (trim((string) $item->description) !== ''): ?><span class="d-block small text-body-secondary mt-1"><?= htmlspecialchars((string) $item->description) ?></span><?php endif; ?>
          </summary>
          <div class="pt-3"><?php if ($canManage): $form($type, $item); else: ?><p class="mb-0"><?= nl2br(htmlspecialchars((string) $item->comment)) ?></p><?php endif; ?></div>
        </details>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<?php }; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
  <p class="text-body-secondary mb-0">Hierarchie, Kürzel, Kommentare und frei ergänzbare JSON-Metadaten.</p>
  <a class="btn btn-outline-primary" href="<?= htmlspecialchars(url_for('geraete'), ENT_QUOTES) ?>">Geräte separat verwalten</a>
</div>

<div class="card shadow-sm mb-4">
  <div class="card-body">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
      <h2 class="h5 mb-0"><i class="fa-solid fa-filter me-2" aria-hidden="true"></i>Struktur filtern</h2>
      <button type="button" class="btn btn-sm btn-outline-secondary" id="structureFilterReset">Filter zurücksetzen</button>
    </div>
    <div class="row g-3">
      <div class="col-lg-4">
        <label class="form-label" for="structureSearch">Suche</label>
        <input type="search" class="form-control" id="structureSearch" placeholder="Name, Kürzel, Raumnummer oder Kommentar">
      </div>
      <div class="col-sm-6 col-lg-2">
        <label class="form-label" for="structureCustomer">Kunde</label>
        <select class="form-select" id="structureCustomer"><option value="">Alle</option><?php foreach ($customers as $customer): ?><option value="<?= (int) $customer->id ?>"><?= htmlspecialchars($optionLabel($customer, 'customer')) ?></option><?php endforeach; ?></select>
      </div>
      <div class="col-sm-6 col-lg-2">
        <label class="form-label" for="structureSite">Standort</label>
        <select class="form-select" id="structureSite"><option value="">Alle</option><?php foreach ($sites as $site): ?><option value="<?= (int) $site->id ?>" data-customer="<?= (int) $site->customer_id ?>"><?= htmlspecialchars($optionLabel($site, 'site')) ?></option><?php endforeach; ?></select>
      </div>
      <div class="col-sm-6 col-lg-2">
        <label class="form-label" for="structureBuilding">Gebäude</label>
        <select class="form-select" id="structureBuilding"><option value="">Alle</option><?php foreach ($buildings as $building): ?><option value="<?= (int) $building->id ?>" data-site="<?= (int) $building->site_id ?>"><?= htmlspecialchars($optionLabel($building, 'building')) ?></option><?php endforeach; ?></select>
      </div>
      <div class="col-sm-6 col-lg-2">
        <label class="form-label" for="structureFloor">Etage</label>
        <select class="form-select" id="structureFloor"><option value="">Alle</option><?php foreach ($floors as $floor): ?><option value="<?= (int) $floor->id ?>" data-building="<?= (int) $floor->building_id ?>"><?= htmlspecialchars($optionLabel($floor, 'floor')) ?></option><?php endforeach; ?></select>
      </div>
    </div>
    <div class="small text-body-secondary mt-3"><span id="structureResultCount"></span></div>
  </div>
</div>

<div class="row g-3 mb-4">
  <?php foreach ([
    ['Kunden', count($customers), 'fa-users'],
    ['Standorte', count($sites), 'fa-location-dot'],
    ['Gebäude', count($buildings), 'fa-building'],
    ['Etagen', count($floors), 'fa-layer-group'],
    ['Bereiche', count($areas), 'fa-vector-square'],
    ['Räume', count($rooms), 'fa-door-open'],
  ] as [$label, $count, $icon]): ?>
    <div class="col-6 col-md-4 col-xl-2"><div class="border rounded p-3 h-100 bg-body-tertiary"><i class="fa-solid <?= $icon ?> text-primary me-2"></i><strong><?= $count ?></strong><div class="small text-body-secondary"><?= $label ?></div></div></div>
  <?php endforeach; ?>
</div>

<div class="row g-4 mb-4">
  <div class="col-xl-4"><?php $section('Kunden', 'customer', $customers); ?></div>
  <div class="col-xl-4"><?php $section('Standorte', 'site', $sites); ?></div>
  <div class="col-xl-4"><?php $section('Gebäude', 'building', $buildings); ?></div>
</div>

<div class="card shadow-sm mb-4">
  <div class="card-header d-flex justify-content-between"><h2 class="h5 mb-0">Etagen nach Gebäude</h2><span class="badge text-bg-secondary" data-structure-count="floor"><?= count($floors) ?></span></div>
  <div class="card-body">
    <?php if ($canManage): ?><details class="border rounded p-2 mb-4"><summary class="fw-semibold">Neue Etage anlegen</summary><div class="pt-3"><?php $form('floor'); ?></div></details><?php endif; ?>
    <?php foreach ($buildings as $building): ?>
      <section class="mb-4 structure-filter-group" <?= $filterAttributes('building', $building) ?>>
        <div class="border-bottom pb-2 mb-2">
          <h3 class="h5 mb-1"><?= htmlspecialchars($optionLabel($building, 'building')) ?></h3>
          <?php if (trim((string) $building->description) !== ''): ?><p class="small text-body-secondary mb-0"><?= htmlspecialchars((string) $building->description) ?></p><?php endif; ?>
        </div>
        <div class="row g-3">
        <?php foreach ($floors as $floor): if ((int) $floor->building_id !== (int) $building->id) continue; ?>
          <?php $floorIdentifier = StructureController::floorIdentifier($floor, $building); ?>
          <div class="col-md-6 col-xl-4 structure-filter-item" <?= $filterAttributes('floor', $floor) ?>><details class="border rounded p-2"><summary><strong><?= htmlspecialchars($floorIdentifier) ?></strong><?php if ((string) $floor->name !== $floorIdentifier): ?> · <?= htmlspecialchars((string) $floor->name) ?><?php endif; ?><?php if (trim((string) $floor->description) !== ''): ?><span class="d-block small text-body-secondary mt-1"><?= htmlspecialchars((string) $floor->description) ?></span><?php endif; ?></summary><div class="pt-3"><?php if ($canManage) $form('floor', $floor); ?></div></details></div>
        <?php endforeach; ?>
        </div>
      </section>
    <?php endforeach; ?>
  </div>
</div>

<div class="row g-4">
  <div class="col-xl-5"><?php $section('Bereiche', 'area', $areas); ?></div>
  <div class="col-xl-7">
    <div class="card shadow-sm">
      <div class="card-header d-flex justify-content-between"><h2 class="h5 mb-0">Räume nach Etage</h2><span class="badge text-bg-secondary" data-structure-count="room"><?= count($rooms) ?></span></div>
      <div class="card-body">
        <?php if ($canManage): ?><details class="border rounded p-2 mb-4"><summary class="fw-semibold">Neuen Raum anlegen</summary><div class="pt-3"><?php $form('room'); ?></div></details><?php endif; ?>
        <?php foreach ($floors as $floor): ?>
          <?php $building = $buildingsById[(int) $floor->building_id] ?? null; ?>
          <section class="structure-filter-group" <?= $filterAttributes('floor', $floor) ?>>
          <div class="mt-4 border-bottom pb-2"><h3 class="h6 mb-1"><?= htmlspecialchars($optionLabel($floor, 'floor')) ?></h3><?php if (trim((string) $floor->description) !== ''): ?><p class="small text-body-secondary mb-0"><?= htmlspecialchars((string) $floor->description) ?></p><?php endif; ?></div>
          <div class="vstack gap-2">
          <?php foreach ($rooms as $room): if ((int) $room->floor_id !== (int) $floor->id) continue; $area = $areasById[(int) $room->area_id] ?? null; ?>
            <details class="border rounded p-2 structure-filter-item" <?= $filterAttributes('room', $room) ?>><summary><strong><?= htmlspecialchars(StructureController::roomIdentifier($room, $floor, $area)) ?></strong> · <?= htmlspecialchars((string) $room->name) ?><?php if (trim((string) $room->description) !== ''): ?><span class="d-block small text-body-secondary mt-1"><?= htmlspecialchars((string) $room->description) ?></span><?php endif; ?></summary><div class="pt-3"><?php if ($canManage) $form('room', $room); ?></div></details>
          <?php endforeach; ?>
          </div>
          </section>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>

<div class="alert alert-info mt-4 d-none" id="structureNoResults">
  Für diese Filterkombination wurden keine Struktureinträge gefunden.
</div>

<script>
(() => {
  'use strict';
  const controls = {
    search: document.getElementById('structureSearch'),
    customer: document.getElementById('structureCustomer'),
    site: document.getElementById('structureSite'),
    building: document.getElementById('structureBuilding'),
    floor: document.getElementById('structureFloor')
  };
  const items = [...document.querySelectorAll('.structure-filter-item')];
  const groups = [...document.querySelectorAll('.structure-filter-group')];
  const resultCount = document.getElementById('structureResultCount');
  const noResults = document.getElementById('structureNoResults');

  const matches = item => {
    const search = controls.search.value.trim().toLocaleLowerCase('de');
    return (!search || (item.dataset.search || '').includes(search))
      && (!controls.customer.value || item.dataset.customer === controls.customer.value)
      && (!controls.site.value || item.dataset.site === controls.site.value)
      && (!controls.building.value || item.dataset.building === controls.building.value)
      && (!controls.floor.value || item.dataset.floor === controls.floor.value);
  };

  const updateDependentOptions = () => {
    const customer = controls.customer.value;
    [...controls.site.options].forEach(option => {
      if (!option.value) return;
      option.hidden = Boolean(customer && option.dataset.customer !== customer);
    });
    if (controls.site.selectedOptions[0]?.hidden) controls.site.value = '';

    const site = controls.site.value;
    [...controls.building.options].forEach(option => {
      if (!option.value) return;
      const siteOption = controls.site.querySelector(`option[value="${option.dataset.site}"]`);
      const wrongCustomer = customer && siteOption?.dataset.customer !== customer;
      option.hidden = Boolean((site && option.dataset.site !== site) || wrongCustomer);
    });
    if (controls.building.selectedOptions[0]?.hidden) controls.building.value = '';

    const building = controls.building.value;
    [...controls.floor.options].forEach(option => {
      if (!option.value) return;
      const buildingOption = controls.building.querySelector(`option[value="${option.dataset.building}"]`);
      option.hidden = Boolean(
        (building && option.dataset.building !== building)
        || (site && buildingOption?.dataset.site !== site)
        || (customer && controls.site.querySelector(`option[value="${buildingOption?.dataset.site}"]`)?.dataset.customer !== customer)
      );
    });
    if (controls.floor.selectedOptions[0]?.hidden) controls.floor.value = '';
  };

  const applyFilters = () => {
    updateDependentOptions();
    items.forEach(item => item.classList.toggle('d-none', !matches(item)));
    groups.forEach(group => {
      const hasVisibleChild = [...group.querySelectorAll('.structure-filter-item')]
        .some(item => !item.classList.contains('d-none'));
      group.classList.toggle('d-none', !hasVisibleChild);
    });

    const visible = items.filter(item => !item.classList.contains('d-none'));
    document.querySelectorAll('[data-structure-count]').forEach(counter => {
      counter.textContent = visible.filter(item => item.dataset.type === counter.dataset.structureCount).length;
    });
    resultCount.textContent = `${visible.length} von ${items.length} Einträgen sichtbar`;
    noResults.classList.toggle('d-none', visible.length !== 0);
  };

  Object.values(controls).forEach(control => control.addEventListener(
    control === controls.search ? 'input' : 'change',
    applyFilters
  ));
  document.getElementById('structureFilterReset').addEventListener('click', () => {
    Object.values(controls).forEach(control => control.value = '');
    applyFilters();
    controls.search.focus();
  });
  applyFilters();
})();
</script>
