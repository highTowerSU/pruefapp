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
$customerScopeByOwner = [];
foreach ($customers as $customer) {
    $descendantId = (int) $customer->id;
    $lineage = [];
    $currentId = $descendantId;
    $seen = [];
    while ($currentId > 0 && !isset($seen[$currentId])) {
        $seen[$currentId] = true;
        $lineage[] = $currentId;
        $currentId = (int) ($customersById[$currentId]->parent_customer_id ?? 0);
    }
    foreach ($lineage as $ownerId) {
        $customerScopeByOwner[$ownerId][] = $descendantId;
    }
}

// Counts shown in the destructive cascade confirmation.  They are calculated
// here (rather than guessed in JavaScript) so the warning reflects the data
// that will actually be removed by the controller.
$cascadeCounts = static function (string $type, $item) use ($customerScopeByOwner): array {
    $in = static function (array $ids): string {
        return implode(',', array_fill(0, count($ids), '?'));
    };
    $ids = static function (string $sql, array $params): array {
        if ($params === []) return [];
        return array_map('intval', \RedBeanPHP\R::getCol($sql, $params));
    };

    $customerIds = $siteIds = $buildingIds = $floorIds = $areaIds = $roomIds = [];
    if ($type === 'customer') {
        $customerIds = array_values(array_unique(array_map('intval', $customerScopeByOwner[(int) $item->id] ?? [(int) $item->id])));
        $p = $in($customerIds);
        $siteIds = $ids("SELECT id FROM site WHERE customer_id IN ($p)", $customerIds);
    } elseif ($type === 'site') {
        $siteIds = [(int) $item->id];
    } elseif ($type === 'building') {
        $buildingIds = [(int) $item->id];
    } elseif ($type === 'floor') {
        $floorIds = [(int) $item->id];
    } elseif ($type === 'area') {
        $areaIds = [(int) $item->id];
    }

    if ($siteIds !== []) {
        $p = $in($siteIds);
        $buildingIds = $ids("SELECT id FROM building WHERE site_id IN ($p)", $siteIds);
    }
    if ($buildingIds !== []) {
        $p = $in($buildingIds);
        $floorIds = $ids("SELECT id FROM floor WHERE building_id IN ($p)", $buildingIds);
    }
    if ($floorIds !== []) {
        $p = $in($floorIds);
        $areaIds = $ids("SELECT id FROM area WHERE floor_id IN ($p)", $floorIds);
        $roomIds = $ids("SELECT id FROM room WHERE floor_id IN ($p)", $floorIds);
    }
    if ($areaIds !== []) {
        $p = $in($areaIds);
        $areaRoomIds = $ids("SELECT id FROM room WHERE area_id IN ($p)", $areaIds);
        $roomIds = array_values(array_unique(array_merge($roomIds, $areaRoomIds)));
    }
    if ($roomIds === [] && $type === 'room') $roomIds = [(int) $item->id];

    $deviceCount = 0;
    $inspectionCount = 0;
    if ($roomIds !== []) {
        $p = $in($roomIds);
        $deviceCount = (int) \RedBeanPHP\R::getCell("SELECT COUNT(*) FROM device WHERE room_id IN ($p)", $roomIds);
        $inspectionCount = (int) \RedBeanPHP\R::getCell("SELECT COUNT(*) FROM inspection i JOIN device d ON d.id = i.device_id WHERE d.room_id IN ($p)", $roomIds);
    }
    return [
        'sites' => count($siteIds), 'buildings' => count($buildingIds), 'floors' => count($floorIds),
        'areas' => count($areaIds), 'rooms' => count($roomIds), 'devices' => $deviceCount,
        'inspections' => $inspectionCount,
    ];
};

$siteCountByCustomer = [];
foreach ($sites as $site) {
    $customerId = (int) $site->customer_id;
    $siteCountByCustomer[$customerId] = ($siteCountByCustomer[$customerId] ?? 0) + 1;
}
$buildingCountBySite = [];
foreach ($buildings as $building) {
    $siteId = (int) $building->site_id;
    $buildingCountBySite[$siteId] = ($buildingCountBySite[$siteId] ?? 0) + 1;
}

$labelWithCode = static function ($bean): string {
    $name = (string) ($bean->name ?? '');
    $code = trim((string) ($bean->code ?? ''));
    return $code === '' ? $name : $code . ' · ' . $name;
};
$contextToken = static function ($bean): string {
    $code = trim((string) ($bean->code ?? ''));
    return $code !== '' ? $code : (string) ($bean->name ?? '');
};
$floorDisplayLabel = static function ($floor, $building): string {
    $identifier = trim(StructureController::floorIdentifier($floor, $building));
    $name = trim((string) ($floor->name ?? ''));

    if ($name !== '' && $name !== $identifier) {
        return $identifier !== '' ? $identifier . ' · ' . $name : $name;
    }

    return $identifier !== '' ? $identifier : 'Etage #' . (int) $floor->id;
};

$filterContext = static function (string $type, $item) use (
    $customersById, $sitesById, $buildingsById, $floorsById, $customerScopeByOwner
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
        'customer' => implode(' ', $customerScopeByOwner[$customerId] ?? [$customerId]), 'site' => $siteId,
        'building' => $buildingId, 'floor' => $floorId,
        'search' => strtolower(implode(' ', $searchParts)),
    ];
};
$filterAttributes = static function (string $type, $item) use ($filterContext): string {
    $context = $filterContext($type, $item);
    return sprintf(
        'data-type="%s" data-customer="%s" data-site="%d" data-building="%d" data-floor="%d" data-search="%s"',
        htmlspecialchars($type, ENT_QUOTES),
        htmlspecialchars($context['customer'], ENT_QUOTES), $context['site'], $context['building'], $context['floor'],
        htmlspecialchars($context['search'], ENT_QUOTES)
    );
};

$optionLabel = static function ($bean, string $type) use (
    $customersById, $sitesById, $buildingsById, $labelWithCode, $contextToken, $floorDisplayLabel
): string {
    if ($type === 'customer') return $labelWithCode($bean);
    if ($type === 'site') {
        $customer = $customersById[(int) $bean->customer_id] ?? null;
        return ($customer ? $contextToken($customer) . ' ' : '') . $labelWithCode($bean);
    }
    if ($type === 'building') {
        $site = $sitesById[(int) $bean->site_id] ?? null;
        $customer = $site ? ($customersById[(int) $site->customer_id] ?? null) : null;
        return ($customer ? $contextToken($customer) . ' ' : '')
            . ($site ? $contextToken($site) . ' ' : '')
            . $labelWithCode($bean);
    }
    if ($type === 'floor') {
        $building = $buildingsById[(int) $bean->building_id] ?? null;
        $site = $building ? ($sitesById[(int) $building->site_id] ?? null) : null;
        $customer = $site ? ($customersById[(int) $site->customer_id] ?? null) : null;
        $buildingLabel = ($customer ? $contextToken($customer) . ' ' : '')
            . ($site ? $contextToken($site) . ' ' : '');
        return $buildingLabel . $floorDisplayLabel($bean, $building);
    }
    if ($type === 'area') return $labelWithCode($bean);
    return (string) $bean->name;
};

$summaryLabel = static function (string $type, $item): string {
    return $type === 'room' && trim((string) ($item->number ?? '')) !== '' ? (string) $item->number : (string) $item->name;
};

$hierarchyBadges = static function (string $type, $item) use (
    $customers, $customersById, $sitesById, $buildingsById, $floorsById,
    $siteCountByCustomer, $buildingCountBySite
): string {
    $customer = $site = $building = null;
    if ($type === 'customer') {
        $customer = $item;
    } elseif ($type === 'site') {
        $site = $item;
        $customer = $customersById[(int) $site->customer_id] ?? null;
    } else {
        if ($type === 'building') {
            $building = $item;
        } else {
            $floor = $type === 'floor' ? $item : ($floorsById[(int) $item->floor_id] ?? null);
            $building = $buildingsById[(int) ($floor->building_id ?? 0)] ?? null;
        }
        $site = $building ? ($sitesById[(int) $building->site_id] ?? null) : null;
        $customer = $site ? ($customersById[(int) $site->customer_id] ?? null) : null;
    }

    $definitions = [
        [$customer, $type !== 'customer' && count($customers) > 1, 'fa-users', 'text-bg-primary', 'Kunde'],
        [$site, $type !== 'site' && ($siteCountByCustomer[(int) ($site->customer_id ?? 0)] ?? 0) > 1, 'fa-location-dot', 'text-bg-info', 'Standort'],
        [$building, ($buildingCountBySite[(int) ($building->site_id ?? 0)] ?? 0) > 1, 'fa-building', 'text-bg-secondary', 'Gebäude'],
    ];
    $html = '';
    foreach ($definitions as [$bean, $visible, $icon, $class, $label]) {
        if (!$visible) continue;
        $code = trim((string) ($bean->code ?? ''));
        if ($code === '') continue;
        $html .= sprintf(
            '<span class="badge %s me-1" title="%s"><i class="fa-solid %s me-1" aria-hidden="true"></i>%s</span>&nbsp;',
            $class,
            htmlspecialchars($label . ': ' . (string) $bean->name, ENT_QUOTES),
            $icon,
            htmlspecialchars($code, ENT_QUOTES)
        );
    }
    return $html;
};

$form = static function (string $type, $entity = null) use (
    $customers, $sites, $buildings, $floors, $areas, $optionLabel, $tenants, $sevdeskContactsByTenant
): void {
    $entity ??= (object) [
        'id' => 0, 'name' => '', 'description' => '', 'comment' => '', 'metadata_json' => '{}',
        'parent_customer_id' => 0, 'customer_id' => 0, 'site_id' => 0,
        'building_id' => 0, 'floor_id' => 0, 'area_id' => 0,
        'code' => '', 'sort_order' => '', 'number' => '',
        'room_code_pattern' => '', 'sevdesk_customer_id' => '', 'sevdesk_customer_number' => '', 'tenant_id' => 0,
    ];
    $config = [
        'customer' => ['route' => 'struktur/kunden', 'parent' => 'parent_customer_id', 'parents' => $customers, 'prompt' => 'Kein Unterkunde', 'parent_type' => 'customer', 'parent_label' => 'Übergeordneter Kunde'],
        'site' => ['route' => 'struktur/standorte', 'parent' => 'customer_id', 'parents' => $customers, 'prompt' => 'Kunde wählen', 'parent_type' => 'customer', 'parent_label' => 'Kunde'],
        'building' => ['route' => 'struktur/gebaeude', 'parent' => 'site_id', 'parents' => $sites, 'prompt' => 'Standort wählen', 'parent_type' => 'site', 'parent_label' => 'Standort'],
        'floor' => ['route' => 'struktur/etagen', 'parent' => 'building_id', 'parents' => $buildings, 'prompt' => 'Gebäude wählen', 'parent_type' => 'building', 'parent_label' => 'Gebäude'],
        'area' => ['route' => 'struktur/bereiche', 'parent' => 'floor_id', 'parents' => $floors, 'prompt' => 'Etage wählen', 'parent_type' => 'floor', 'parent_label' => 'Etage'],
        'room' => ['route' => 'struktur/raeume', 'parent' => 'floor_id', 'parents' => $floors, 'prompt' => 'Etage wählen', 'parent_type' => 'floor', 'parent_label' => 'Etage'],
    ][$type];
    $metadataValue = trim((string) ($entity->metadata_json ?? ''));
    if ($metadataValue === '{}') $metadataValue = '';
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
    <select class="form-select" name="<?= $config['parent'] ?>" data-search-select data-placeholder="<?= htmlspecialchars($config['prompt'], ENT_QUOTES) ?>"<?= $type === 'customer' ? '' : ' required' ?>>
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
  <?php if ($type === 'customer'): ?><div class="col-md-6"><label class="form-label">Mandant</label><select class="form-select" name="tenant_id" required data-sevdesk-tenant><option value="">Mandant wählen</option><?php foreach ($tenants as $tenant): ?><option value="<?= (int) $tenant->id ?>"<?= (int) ($entity->tenant_id ?? 0) === (int) $tenant->id ? ' selected' : '' ?>><?= htmlspecialchars((string) $tenant->name) ?></option><?php endforeach; ?></select></div><div class="col-md-6"><label class="form-label">SevDesk-Kundennummer</label><?php $hasContacts = false; foreach ($sevdeskContactsByTenant as $contacts) if ($contacts !== []) { $hasContacts = true; break; } if ($hasContacts): ?><select class="form-select" name="sevdesk_customer_id" data-sevdesk-contact><option value="">Keine Zuordnung</option><?php foreach ($sevdeskContactsByTenant as $tenantId => $contacts): foreach ($contacts as $contact): $contactId = (string) ($contact['id'] ?? ''); $contactNumber = (string) ($contact['customerNumber'] ?? $contact['customer_number'] ?? ''); $contactName = trim((string) ($contact['name'] ?? (($contact['familyname'] ?? '') . ' ' . ($contact['firstname'] ?? '')))); if ($contactId === '') continue; ?><option value="<?= htmlspecialchars($contactId, ENT_QUOTES) ?>" data-tenant-id="<?= (int) $tenantId ?>" data-customer-number="<?= htmlspecialchars($contactNumber, ENT_QUOTES) ?>"<?= (string) ($entity->sevdesk_customer_id ?? '') === $contactId ? ' selected' : '' ?>><?= htmlspecialchars(($contactNumber !== '' ? $contactNumber . ' · ' : '') . ($contactName ?: 'SevDesk-Kunde ' . $contactId)) ?></option><?php endforeach; endforeach; ?></select><input type="hidden" name="sevdesk_customer_number" value="<?= htmlspecialchars((string) ($entity->sevdesk_customer_number ?? ''), ENT_QUOTES) ?>"><div class="form-text">Nur Kontakte des ausgewählten Mandanten.</div><?php else: ?><input class="form-control" name="sevdesk_customer_number" value="<?= htmlspecialchars((string) ($entity->sevdesk_customer_number ?? $entity->sevdesk_customer_id ?? '')) ?>" placeholder="z. B. 123456"><input type="hidden" name="sevdesk_customer_id" value="<?= htmlspecialchars((string) ($entity->sevdesk_customer_id ?? '')) ?>"><div class="form-text">Für diesen Mandanten ist keine SevDesk-Kontaktliste verfügbar.</div><?php endif; ?></div><?php endif; ?>
  <?php if ($type === 'floor'): ?>
    <div class="col-md-6"><label class="form-label">Sortierreihenfolge</label><input type="number" class="form-control" name="sort_order" placeholder="U automatisch vor E" value="<?= htmlspecialchars((string) $entity->sort_order) ?>"></div>
  <?php elseif ($type === 'room'): ?>
    <div class="col-md-6"><label class="form-label">Raumnummer</label><input class="form-control" name="number" required placeholder="z. B. 07, 10 oder 24" value="<?= htmlspecialchars((string) ($entity->number ?: $entity->name)) ?>"></div>
    <div class="col-md-6"><label class="form-label">Bereich</label><select class="form-select" name="area_id" data-search-select data-placeholder="Bereich suchen"><option value="0">Kein Bereich</option><?php foreach ($areas as $area): ?><option value="<?= (int) $area->id ?>"<?= (int) $entity->area_id === (int) $area->id ? ' selected' : '' ?>><?= htmlspecialchars($optionLabel($area, 'area')) ?></option><?php endforeach; ?></select></div>
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
  <div class="col-md-6"><label class="form-label">Metadaten (JSON-Objekt, optional)</label><textarea class="form-control font-monospace" name="metadata_json" placeholder='z. B. {"kostenstelle":"1000"}'><?= htmlspecialchars($metadataValue) ?></textarea></div>
  <div class="col-12 text-end"><button class="btn btn-primary btn-sm">Speichern</button></div>
</form>
<?php if ($type === 'customer'): ?><script>document.querySelectorAll('form').forEach(function(form){const tenant=form.querySelector('[data-sevdesk-tenant]');const select=form.querySelector('[data-sevdesk-contact]');if(!tenant||!select)return;const sync=()=>{const id=tenant.value;Array.from(select.options).forEach(option=>{if(!option.dataset.tenantId)return;const visible=option.dataset.tenantId===id;option.hidden=!visible;if(!visible&&option.selected)select.value='';});const option=select.options[select.selectedIndex];const number=form.querySelector('[name="sevdesk_customer_number"]');if(number)number.value=option?.dataset.customerNumber||'';};tenant.addEventListener('change',sync);select.addEventListener('change',sync);sync();});</script><?php endif; ?>
<?php };

$section = static function (string $title, string $type, array $items) use ($form, $canManage, $filterAttributes, $summaryLabel, $hierarchyBadges, $rooms, $cascadeCounts): void {
$icons = ['customer' => 'fa-users', 'site' => 'fa-location-dot', 'building' => 'fa-building', 'floor' => 'fa-layer-group', 'area' => 'fa-vector-square', 'room' => 'fa-door-open'];
?>
<div class="card shadow-sm h-100">
  <div class="card-header d-flex justify-content-between"><h2 class="h5 mb-0"><i class="fa-solid <?= $icons[$type] ?? 'fa-sitemap' ?> text-primary me-2" aria-hidden="true"></i><?= htmlspecialchars($title) ?></h2><span class="badge text-bg-secondary" data-structure-count="<?= $type ?>"><?= count($items) ?></span></div>
  <div class="card-body">
    <?php if ($canManage): ?><details class="border rounded p-2 mb-3"><summary class="fw-semibold"><i class="fa-solid fa-plus-circle text-primary me-2" aria-hidden="true"></i>Neu anlegen</summary><div class="pt-3"><?php $form($type); ?></div></details><?php endif; ?>
    <div class="vstack gap-2">
      <?php foreach ($items as $item): ?>
            <?php $cascade = $cascadeCounts($type, $item); $cascadeSummary = sprintf('%d Standort(e), %d Gebäude, %d Etage(n), %d Bereich(e), %d Raum/Räume, %d Gerät(e), %d Prüfung(en)', $cascade['sites'], $cascade['buildings'], $cascade['floors'], $cascade['areas'], $cascade['rooms'], $cascade['devices'], $cascade['inspections']); ?>
            <details class="border rounded p-2 structure-filter-item" <?= $filterAttributes($type, $item) ?>>
          <summary>
            <?php if ($canManage): ?><input class="form-check-input me-2 structure-check" type="checkbox" form="structure-bulk-form" name="structure_ids[]" value="<?= (int) $item->id ?>" data-structure-type="<?= htmlspecialchars($type, ENT_QUOTES) ?>" onclick="event.stopPropagation()" aria-label="<?= htmlspecialchars($summaryLabel($type, $item), ENT_QUOTES) ?> auswählen"><?php endif; ?><i class="fa-solid <?= $icons[$type] ?? 'fa-sitemap' ?> text-body-secondary me-2" aria-hidden="true"></i><?= $hierarchyBadges($type, $item) ?><strong><?= htmlspecialchars($summaryLabel($type, $item)) ?></strong><?php if (!in_array($type, ['customer', 'site', 'building', 'room'], true) && !empty($item->code)): ?> <span class="badge text-bg-secondary"><?= htmlspecialchars((string) $item->code) ?></span><?php endif; ?>
            <?php if (trim((string) $item->description) !== ''): ?><span class="d-block small text-body-secondary mt-1"><?= htmlspecialchars((string) $item->description) ?></span><?php endif; ?>
          </summary>
          <div class="pt-3"><?php if ($type === 'customer'): ?><a class="btn btn-sm btn-outline-primary mb-3" href="<?= htmlspecialchars(url_for('kunden/' . (int) $item->id . '/infos'), ENT_QUOTES) ?>"><i class="fa-solid fa-circle-info me-1" aria-hidden="true"></i>Kundeninfos öffnen</a><?php endif; ?><?php if ($canManage): $form($type, $item); else: ?><p class="mb-0"><?= nl2br(htmlspecialchars((string) $item->comment)) ?></p><?php endif; ?>
            <?php if ($canManage): ?><div class="d-flex justify-content-end gap-2 mt-2"><?php $deletePath = $type === 'room' ? 'struktur/raeume/' . (int) $item->id . '/loeschen' : 'struktur/' . $type . '/' . (int) $item->id . '/loeschen'; ?><form method="post" action="<?= htmlspecialchars(url_for($deletePath), ENT_QUOTES) ?>" onsubmit="return confirm('Diesen leeren Eintrag wirklich löschen?');"><button class="btn btn-sm btn-outline-danger" type="submit">Löschen</button></form><form method="post" action="<?= htmlspecialchars(url_for($deletePath), ENT_QUOTES) ?>" onsubmit="return confirm('<?= htmlspecialchars('Auch alle Untereinträge löschen? Betroffen: ' . $cascadeSummary . '. Dieser Vorgang kann nicht rückgängig gemacht werden.', ENT_QUOTES) ?>');"><input type="hidden" name="cascade" value="1"><button class="btn btn-sm btn-danger" type="submit">Mit Unterstruktur löschen</button></form></div><?php endif; ?>
          </div>
        </details>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<?php }; ?>

<div id="structure-page" hx-boost="true" hx-target="#structure-page" hx-swap="outerHTML" hx-push-url="true">
<?= render_template('inspection_companion_inbox.php', ['items' => InspectionCompanionInboxService::itemsForOwner((int) current_user()->id)]) ?>
<div class="d-flex justify-content-between align-items-center mb-4">
  <p class="text-body-secondary mb-0">Hierarchie, Kürzel, Kommentare und frei ergänzbare JSON-Metadaten.</p>
  <a class="btn btn-outline-primary" href="<?= htmlspecialchars(url_for('geraete'), ENT_QUOTES) ?>">Geräte separat verwalten</a>
</div>
<?php if ($canManage): ?><form id="structure-bulk-form" data-action-nav="Strukturaktionen" data-action-icon="fa-sitemap" method="post" action="<?= htmlspecialchars(url_for('struktur/massenaktion'), ENT_QUOTES) ?>" class="card card-body shadow-sm mb-4"><div class="d-flex flex-wrap align-items-center gap-2"><strong>Auswahl &amp; Massenaktionen</strong><input type="hidden" name="type" value="" data-structure-bulk-type><label class="small"><input type="checkbox" name="cascade" value="1"> mit Unterstruktur löschen</label><button class="btn btn-sm btn-outline-danger" name="action" value="delete">Ausgewählte löschen</button><span class="small text-body-secondary">Es können nur Einträge desselben Typs gemeinsam gelöscht werden.</span></div></form><?php endif; ?>

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
        <select class="form-select structure-filter-select" id="structureCustomer" data-search-select data-placeholder="Kunde suchen"><option value="">Alle</option><?php foreach ($customers as $customer): ?><option value="<?= (int) $customer->id ?>"><?= htmlspecialchars($optionLabel($customer, 'customer')) ?></option><?php endforeach; ?></select>
      </div>
      <div class="col-sm-6 col-lg-2">
        <label class="form-label" for="structureSite">Standort</label>
        <select class="form-select structure-filter-select" id="structureSite" data-search-select data-placeholder="Standort suchen"><option value="">Alle</option><?php foreach ($sites as $site): ?><option value="<?= (int) $site->id ?>" data-customer="<?= htmlspecialchars(implode(' ', $customerScopeByOwner[(int) $site->customer_id] ?? [(int) $site->customer_id]), ENT_QUOTES) ?>"><?= htmlspecialchars($optionLabel($site, 'site')) ?></option><?php endforeach; ?></select>
      </div>
      <div class="col-sm-6 col-lg-2">
        <label class="form-label" for="structureBuilding">Gebäude</label>
        <select class="form-select structure-filter-select" id="structureBuilding" data-search-select data-placeholder="Gebäude suchen"><option value="">Alle</option><?php foreach ($buildings as $building): ?><option value="<?= (int) $building->id ?>" data-site="<?= (int) $building->site_id ?>"><?= htmlspecialchars($optionLabel($building, 'building')) ?></option><?php endforeach; ?></select>
      </div>
      <div class="col-sm-6 col-lg-2">
        <label class="form-label" for="structureFloor">Etage</label>
        <select class="form-select structure-filter-select" id="structureFloor" data-search-select data-placeholder="Etage suchen"><option value="">Alle</option><?php foreach ($floors as $floor): ?><option value="<?= (int) $floor->id ?>" data-building="<?= (int) $floor->building_id ?>"><?= htmlspecialchars($optionLabel($floor, 'floor')) ?></option><?php endforeach; ?></select>
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
  <div class="card-header d-flex justify-content-between"><h2 class="h5 mb-0"><i class="fa-solid fa-layer-group text-primary me-2" aria-hidden="true"></i>Etagen nach Gebäude</h2><span class="badge text-bg-secondary" data-structure-count="floor"><?= count($floors) ?></span></div>
  <div class="card-body">
    <?php if ($canManage): ?><details class="border rounded p-2 mb-4"><summary class="fw-semibold"><i class="fa-solid fa-plus-circle text-primary me-2" aria-hidden="true"></i>Neue Etage anlegen</summary><div class="pt-3"><?php $form('floor'); ?></div></details><?php endif; ?>
    <?php foreach ($buildings as $building): ?>
      <section class="mb-4 structure-filter-group" <?= $filterAttributes('building', $building) ?>>
        <div class="border-bottom pb-2 mb-2">
          <h3 class="h5 mb-1"><?= $hierarchyBadges('building', $building) ?><span><?= htmlspecialchars((string) $building->name) ?></span></h3>
          <?php if (trim((string) $building->description) !== ''): ?><p class="small text-body-secondary mb-0"><?= htmlspecialchars((string) $building->description) ?></p><?php endif; ?>
        </div>
        <div class="row g-3">
        <?php foreach ($floors as $floor): if ((int) $floor->building_id !== (int) $building->id) continue; ?>
          <?php $floorLabel = $floorDisplayLabel($floor, $building); ?>
          <?php $cascade = $cascadeCounts('floor', $floor); $cascadeSummary = sprintf('%d Bereich(e), %d Raum/Räume, %d Gerät(e), %d Prüfung(en)', $cascade['areas'], $cascade['rooms'], $cascade['devices'], $cascade['inspections']); ?>
          <div class="col-md-6 col-xl-4 structure-filter-item" <?= $filterAttributes('floor', $floor) ?>><details class="border rounded p-2"><summary><?php if ($canManage): ?><input class="form-check-input me-2 structure-check" type="checkbox" form="structure-bulk-form" name="structure_ids[]" value="<?= (int) $floor->id ?>" data-structure-type="floor" onclick="event.stopPropagation()" aria-label="Etage auswählen"><?php endif; ?><?= $hierarchyBadges('floor', $floor) ?><strong><?= htmlspecialchars($floorLabel) ?></strong><?php if (trim((string) $floor->description) !== ''): ?><span class="d-block small text-body-secondary mt-1"><?= htmlspecialchars((string) $floor->description) ?></span><?php endif; ?></summary><div class="pt-3"><?php if ($canManage) $form('floor', $floor); ?><?php if ($canManage): ?><div class="d-flex justify-content-end gap-2 mt-2"><form method="post" action="<?= htmlspecialchars(url_for('struktur/etagen/' . (int) $floor->id . '/loeschen'), ENT_QUOTES) ?>" onsubmit="return confirm('<?= htmlspecialchars('Etage wirklich löschen? Betroffen: ' . $cascadeSummary . '. Dieser Vorgang kann nicht rückgängig gemacht werden.', ENT_QUOTES) ?>');"><input type="hidden" name="cascade" value="1"><button class="btn btn-sm btn-danger" type="submit">Etage mit Unterstruktur löschen</button></form></div><?php endif; ?></div></details></div>
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
      <div class="card-header d-flex justify-content-between"><h2 class="h5 mb-0"><i class="fa-solid fa-door-open text-primary me-2" aria-hidden="true"></i>Räume nach Etage</h2><span class="badge text-bg-secondary" data-structure-count="room"><?= count($rooms) ?></span></div>
      <div class="card-body">
        <?php if ($canManage): ?><details class="border rounded p-2 mb-4"><summary class="fw-semibold"><i class="fa-solid fa-plus-circle text-primary me-2" aria-hidden="true"></i>Neuen Raum anlegen</summary><div class="pt-3"><?php $form('room'); ?></div></details><?php endif; ?>
        <?php foreach ($floors as $floor): ?>
          <?php $building = $buildingsById[(int) $floor->building_id] ?? null; ?>
          <section class="structure-filter-group" <?= $filterAttributes('floor', $floor) ?>>
          <div class="mt-4 border-bottom pb-2"><h3 class="h6 mb-1"><?= $hierarchyBadges('floor', $floor) ?><span><?= htmlspecialchars($floorDisplayLabel($floor, $building)) ?></span></h3><?php if (trim((string) $floor->description) !== ''): ?><p class="small text-body-secondary mb-0"><?= htmlspecialchars((string) $floor->description) ?></p><?php endif; ?></div>
          <div class="vstack gap-2">
          <?php foreach ($rooms as $room): if ((int) $room->floor_id !== (int) $floor->id) continue; $area = $areasById[(int) $room->area_id] ?? null; $roomDeviceCount = (int) \RedBeanPHP\R::count('device', 'room_id = ?', [(int) $room->id]); $roomInspectionCount = (int) \RedBeanPHP\R::getCell('SELECT COUNT(*) FROM inspection i JOIN device d ON d.id = i.device_id WHERE d.room_id = ?', [(int) $room->id]); ?>
            <details class="border rounded p-2 structure-filter-item" <?= $filterAttributes('room', $room) ?>><summary><?php if ($canManage): ?><input class="form-check-input me-2 structure-check" type="checkbox" form="structure-bulk-form" name="structure_ids[]" value="<?= (int) $room->id ?>" data-structure-type="room" onclick="event.stopPropagation()" aria-label="Raum auswählen"><?php endif; ?><strong><?= htmlspecialchars(StructureController::roomIdentifier($room, $floor, $area)) ?></strong> · <?= htmlspecialchars((string) $room->name) ?><?php if (trim((string) $room->description) !== ''): ?><span class="d-block small text-body-secondary mt-1"><?= htmlspecialchars((string) $room->description) ?></span><?php endif; ?></summary><div class="pt-3"><?php if ($canManage) $form('room', $room); ?><?= RoomMediaController::panel((int) $room->id) ?><?php if ($canManage): ?><div class="d-flex justify-content-end gap-2 mt-2"><form method="post" action="<?= htmlspecialchars(url_for('struktur/raeume/' . (int) $room->id . '/loeschen'), ENT_QUOTES) ?>" onsubmit="return confirm('<?= htmlspecialchars('Raum ' . StructureController::roomIdentifier($room, $floor, $area) . ' sowie ' . $roomDeviceCount . ' Gerät(e) und ' . $roomInspectionCount . ' Prüfung(en) löschen?', ENT_QUOTES) ?>');"><button class="btn btn-sm btn-outline-danger">Raum löschen</button></form></div><?php endif; ?><?php if ($canManage && $roomDeviceCount > 0): ?><form method="post" action="<?= htmlspecialchars(url_for('struktur/raeume/' . (int) $room->id . '/geraete-verschieben'), ENT_QUOTES) ?>" class="border rounded p-2 mt-3" onsubmit="return confirm('Alle Geräte aus diesem Raum in den ausgewählten Zielraum verschieben?');"><label class="form-label">Geräte verschieben</label><select class="form-select form-select-sm mb-2" name="target_room_id" required data-search-select data-placeholder="Zielraum suchen"><option value="">Zielraum wählen</option><?php foreach ($rooms as $targetRoom): if ((int) $targetRoom->id === (int) $room->id) continue; $targetFloor = $floorsById[(int) $targetRoom->floor_id] ?? null; $targetBuilding = $targetFloor ? ($buildingsById[(int) $targetFloor->building_id] ?? null) : null; $targetArea = $areasById[(int) ($targetRoom->area_id ?? 0)] ?? null; $targetIdentifier = $targetFloor && $targetBuilding ? StructureController::roomIdentifier($targetRoom, $targetFloor, $targetArea) : (string) $targetRoom->name; $targetLabel = $targetIdentifier === (string) $targetRoom->name ? $targetIdentifier : $targetIdentifier . ' · ' . (string) $targetRoom->name; ?><option value="<?= (int) $targetRoom->id ?>"><?= htmlspecialchars($targetLabel) ?></option><?php endforeach; ?></select><button class="btn btn-sm btn-outline-primary">Alle Geräte verschieben</button></form><?php endif; ?></div></details>
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

<style>
.structure-form{--bs-gutter-y:1rem}.structure-form .form-label{font-weight:600}.structure-form .form-control,.structure-form .form-select,.structure-form .ts-control{min-height:44px}.structure-form textarea.form-control{min-height:88px}.structure-filter-item>summary,.structure-filter-group>div>h3{cursor:pointer}.structure-filter-item>summary:focus-visible{outline:3px solid var(--bs-primary);outline-offset:3px}.structure-filter-item .d-flex.justify-content-end{flex-wrap:wrap}.structure-filter-item .d-flex.justify-content-end form{margin:0}.structure-filter-item .btn,.structure-filter-item .form-select{min-height:42px}
@media(max-width:991.98px){.structure-form .row>[class*="col-md-"]{width:50%}.structure-form .row>.col-12{width:100%}}
@media(max-width:767.98px){.structure-filter-item,.structure-filter-group section{border-radius:.65rem}.structure-form{--bs-gutter-y:1.25rem}.structure-form .row>[class*="col-md-"]{width:100%}.structure-form .form-control,.structure-form .form-select,.structure-form .ts-control{min-height:48px;font-size:1rem}.structure-form .btn{min-height:48px}.structure-filter-item .d-flex.justify-content-end form,.structure-filter-item .d-flex.justify-content-end .btn{width:100%}.structure-filter-item .border.rounded.p-2.mt-3{padding:1rem!important}.structure-filter-item .border.rounded.p-2.mt-3 .btn{width:100%}.card-header{padding:1rem}.row.g-4{--bs-gutter-y:1.5rem}.structure-filter-group .vstack{gap:.75rem!important}.structure-filter-item>summary{padding:.35rem}.structure-filter-item>summary .badge{margin-bottom:.25rem}.table-responsive{overflow-x:auto}}
@media(max-width:575.98px){.structure-form .form-label{margin-bottom:.4rem}.structure-form .form-text{font-size:.8rem}.structure-filter-item>summary{line-height:1.45}.structure-filter-item .small{font-size:.82rem}.structure-filter-item .d-flex.justify-content-end{gap:.5rem!important}.structure-filter-item .btn{font-size:1rem}.card-body{padding:1rem}}
</style>

  <style>
    #structure-page summary,
    #structure-page .structure-check,
    #structure-bulk-form { user-select: none; }
  </style>
  <script>
(() => {
  'use strict';
  document.querySelectorAll('form[action*="/struktur/"]').forEach(form => { form.classList.add('structure-form'); form.classList.remove('g-2'); form.classList.add('g-3'); });
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
  const customerMatches = (value, customer) => !customer
    || (value || '').split(/\s+/).includes(customer);
  const setValue = (control, value) => {
    if (control.tomselect) {
      control.tomselect.setValue(value, true);
    } else {
      control.value = value;
    }
  };
  const setOptionHidden = (control, option, hidden) => {
    option.hidden = hidden;
    option.disabled = hidden;
    const select = control.tomselect;
    const selectOption = select?.options[option.value];
    if (selectOption) {
      select.updateOption(option.value, { ...selectOption, disabled: hidden });
    }
  };

  const matches = item => {
    const search = controls.search.value.trim().toLocaleLowerCase('de');
    return (!search || (item.dataset.search || '').includes(search))
      && customerMatches(item.dataset.customer, controls.customer.value)
      && (!controls.site.value || item.dataset.site === controls.site.value)
      && (!controls.building.value || item.dataset.building === controls.building.value)
      && (!controls.floor.value || item.dataset.floor === controls.floor.value);
  };

  const updateDependentOptions = () => {
    const customer = controls.customer.value;
    [...controls.site.options].forEach(option => {
      if (!option.value) return;
      setOptionHidden(controls.site, option, !customerMatches(option.dataset.customer, customer));
    });
    if (controls.site.selectedOptions[0]?.hidden) setValue(controls.site, '');

    const site = controls.site.value;
    [...controls.building.options].forEach(option => {
      if (!option.value) return;
      const siteOption = controls.site.querySelector(`option[value="${option.dataset.site}"]`);
      const wrongCustomer = customer && !customerMatches(siteOption?.dataset.customer, customer);
      setOptionHidden(controls.building, option, Boolean((site && option.dataset.site !== site) || wrongCustomer));
    });
    if (controls.building.selectedOptions[0]?.hidden) setValue(controls.building, '');

    const building = controls.building.value;
    [...controls.floor.options].forEach(option => {
      if (!option.value) return;
      const buildingOption = controls.building.querySelector(`option[value="${option.dataset.building}"]`);
      setOptionHidden(controls.floor, option, Boolean(
        (building && option.dataset.building !== building)
        || (site && buildingOption?.dataset.site !== site)
        || (customer && !customerMatches(controls.site.querySelector(`option[value="${buildingOption?.dataset.site}"]`)?.dataset.customer, customer))
      ));
    });
    if (controls.floor.selectedOptions[0]?.hidden) setValue(controls.floor, '');
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
    Object.values(controls).forEach(control => setValue(control, ''));
    applyFilters();
    controls.search.focus();
  });
  applyFilters();
})();
</script>
<?php if ($canManage): ?><script>
(() => {
  const boxes = [...document.querySelectorAll('.structure-check')];
  let last = -1;
  boxes.forEach((box, index) => box.addEventListener('click', event => {
    if (event.shiftKey && last >= 0) {
      const checked = box.checked;
      const start = Math.min(last, index), end = Math.max(last, index);
      boxes.slice(start, end + 1).forEach(item => { item.checked = checked; });
      window.getSelection?.()?.removeAllRanges();
    }
    last = index;
  }));
})();
document.getElementById('structure-bulk-form')?.addEventListener('submit', function(event) {
  const selected = [...document.querySelectorAll('.structure-check:checked')];
  const types = [...new Set(selected.map(input => input.dataset.structureType))];
  if (!types.length) { event.preventDefault(); alert('Bitte mindestens einen Struktur-Eintrag auswählen.'); return; }
  if (types.length !== 1) { event.preventDefault(); alert('Bitte nur Einträge desselben Strukturtyps gemeinsam auswählen.'); return; }
  this.querySelector('[data-structure-bulk-type]').value = types[0];
  const cascade = this.querySelector('[name="cascade"]')?.checked;
  if (!confirm(selected.length + ' Eintrag(e)' + (cascade ? ' mit Unterstruktur' : '') + ' wirklich löschen?')) event.preventDefault();
});
</script><?php endif; ?>
</div>
