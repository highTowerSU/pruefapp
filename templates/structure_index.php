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

$optionLabel = static function ($bean, string $type) use ($buildingsById): string {
    if ($type === 'floor') {
        $building = $buildingsById[(int) $bean->building_id] ?? null;
        return ($building ? $building->name . ' · ' : '') . StructureController::floorIdentifier($bean, $building);
    }
    return (string) $bean->name;
};

$form = static function (string $type, $entity = null) use (
    $customers, $sites, $buildings, $floors, $areas, $optionLabel
): void {
    $entity ??= (object) [
        'id' => 0, 'name' => '', 'comment' => '', 'metadata_json' => '{}',
        'parent_customer_id' => 0, 'customer_id' => 0, 'site_id' => 0,
        'building_id' => 0, 'floor_id' => 0, 'area_id' => 0,
        'code' => '', 'sort_order' => '', 'number' => '',
        'room_code_pattern' => '',
    ];
    $config = [
        'customer' => ['route' => 'struktur/kunden', 'parent' => 'parent_customer_id', 'parents' => $customers, 'prompt' => 'Kein Unterkunde', 'parent_type' => 'customer'],
        'site' => ['route' => 'struktur/standorte', 'parent' => 'customer_id', 'parents' => $customers, 'prompt' => 'Kunde wählen', 'parent_type' => 'customer'],
        'building' => ['route' => 'struktur/gebaeude', 'parent' => 'site_id', 'parents' => $sites, 'prompt' => 'Standort wählen', 'parent_type' => 'site'],
        'floor' => ['route' => 'struktur/etagen', 'parent' => 'building_id', 'parents' => $buildings, 'prompt' => 'Gebäude wählen', 'parent_type' => 'building'],
        'area' => ['route' => 'struktur/bereiche', 'parent' => 'floor_id', 'parents' => $floors, 'prompt' => 'Etage wählen', 'parent_type' => 'floor'],
        'room' => ['route' => 'struktur/raeume', 'parent' => 'floor_id', 'parents' => $floors, 'prompt' => 'Etage wählen', 'parent_type' => 'floor'],
    ][$type];
?>
<form method="post" action="<?= htmlspecialchars(url_for($config['route']), ENT_QUOTES) ?>" class="row g-2">
  <input type="hidden" name="id" value="<?= (int) $entity->id ?>">
  <div class="col-md-6"><input class="form-control" name="name" required placeholder="Name" value="<?= htmlspecialchars((string) $entity->name) ?>"></div>
  <div class="col-md-6">
    <select class="form-select" name="<?= $config['parent'] ?>"<?= $type === 'customer' ? '' : ' required' ?>>
      <option value="0"><?= $config['prompt'] ?></option>
      <?php foreach ($config['parents'] as $parent): if ((int) $parent->id === (int) $entity->id && $type === 'customer') continue; ?>
        <option value="<?= (int) $parent->id ?>"<?= (int) $entity->{$config['parent']} === (int) $parent->id ? ' selected' : '' ?>><?= htmlspecialchars($optionLabel($parent, $config['parent_type'])) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <?php if (in_array($type, ['building', 'floor', 'area'], true)): ?>
    <div class="col-md-6"><input class="form-control text-uppercase" name="code" required placeholder="<?= $type === 'building' ? 'Kürzel, z. B. AB' : ($type === 'floor' ? 'Kürzel: U, E, 0, 1 …' : 'Bereich: E, F …') ?>" value="<?= htmlspecialchars((string) $entity->code) ?>"></div>
  <?php endif; ?>
  <?php if ($type === 'floor'): ?>
    <div class="col-md-6"><input type="number" class="form-control" name="sort_order" placeholder="Sortierung (U automatisch vor E)" value="<?= htmlspecialchars((string) $entity->sort_order) ?>"></div>
  <?php elseif ($type === 'room'): ?>
    <div class="col-md-6"><input class="form-control" name="number" required placeholder="Raumnummer, z. B. 07, 10 oder 24" value="<?= htmlspecialchars((string) ($entity->number ?: $entity->name)) ?>"></div>
    <div class="col-md-6"><select class="form-select" name="area_id"><option value="0">Kein Bereich</option><?php foreach ($areas as $area): ?><option value="<?= (int) $area->id ?>"<?= (int) $entity->area_id === (int) $area->id ? ' selected' : '' ?>><?= htmlspecialchars((string) $area->code . ' · ' . (string) $area->name) ?></option><?php endforeach; ?></select></div>
  <?php endif; ?>
  <?php if ($type === 'customer' || $type === 'floor'): ?>
    <div class="col-12">
      <input class="form-control font-monospace" name="room_code_pattern" placeholder="<?= $type === 'customer' ? 'Raumkennung: auto oder z. B. {building}{floor}{room}' : 'Optional: Muster des Kunden überschreiben' ?>" value="<?= htmlspecialchars((string) $entity->room_code_pattern) ?>">
      <div class="form-text"><code>auto</code> erzeugt z. B. <code>1.24</code>, mit Bereich <code>E10</code> und im Untergeschoss <code>NU07</code>. Muster wie <code>{building}{floor}{room}</code> erzeugen auch <code>K181</code>.</div>
    </div>
  <?php endif; ?>
  <div class="col-md-6"><textarea class="form-control" name="comment" placeholder="Kommentar"><?= htmlspecialchars((string) $entity->comment) ?></textarea></div>
  <div class="col-md-6"><textarea class="form-control font-monospace" name="metadata_json" placeholder='{"schlüssel":"wert"}'><?= htmlspecialchars((string) ($entity->metadata_json ?: '{}')) ?></textarea></div>
  <div class="col-12 text-end"><button class="btn btn-primary btn-sm">Speichern</button></div>
</form>
<?php };

$section = static function (string $title, string $type, array $items) use ($form, $canManage): void {
?>
<div class="card shadow-sm h-100">
  <div class="card-header"><h2 class="h5 mb-0"><?= htmlspecialchars($title) ?></h2></div>
  <div class="card-body">
    <?php if ($canManage): ?><div class="border-bottom pb-3 mb-3"><?php $form($type); ?></div><?php endif; ?>
    <div class="vstack gap-2">
      <?php foreach ($items as $item): ?>
        <details class="border rounded p-2">
          <summary><strong><?= htmlspecialchars((string) $item->name) ?></strong><?php if (!empty($item->code)): ?> <span class="badge text-bg-secondary"><?= htmlspecialchars((string) $item->code) ?></span><?php endif; ?></summary>
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

<div class="row g-4 mb-4">
  <div class="col-xl-4"><?php $section('Kunden', 'customer', $customers); ?></div>
  <div class="col-xl-4"><?php $section('Standorte', 'site', $sites); ?></div>
  <div class="col-xl-4"><?php $section('Gebäude', 'building', $buildings); ?></div>
</div>

<div class="card shadow-sm mb-4">
  <div class="card-header"><h2 class="h5 mb-0">Etagen nach Gebäude</h2></div>
  <div class="card-body">
    <?php if ($canManage): ?><div class="border-bottom pb-3 mb-4"><?php $form('floor'); ?></div><?php endif; ?>
    <?php foreach ($buildings as $building): ?>
      <section class="mb-4">
        <h3 class="h5 border-bottom pb-2"><?= htmlspecialchars((string) $building->name) ?> <span class="text-body-secondary">(<?= htmlspecialchars((string) $building->code) ?>)</span></h3>
        <div class="row g-3">
        <?php foreach ($floors as $floor): if ((int) $floor->building_id !== (int) $building->id) continue; ?>
          <div class="col-md-6 col-xl-4"><details class="border rounded p-2"><summary><strong><?= htmlspecialchars(StructureController::floorIdentifier($floor, $building)) ?></strong> · <?= htmlspecialchars((string) $floor->name) ?></summary><div class="pt-3"><?php if ($canManage) $form('floor', $floor); ?></div></details></div>
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
      <div class="card-header"><h2 class="h5 mb-0">Räume nach Etage</h2></div>
      <div class="card-body">
        <?php if ($canManage): ?><div class="border-bottom pb-3 mb-4"><?php $form('room'); ?></div><?php endif; ?>
        <?php foreach ($floors as $floor): ?>
          <?php $building = $buildingsById[(int) $floor->building_id] ?? null; ?>
          <h3 class="h6 mt-4"><?= htmlspecialchars($building ? (string) $building->name : '') ?> · <?= htmlspecialchars(StructureController::floorIdentifier($floor, $building)) ?></h3>
          <div class="vstack gap-2">
          <?php foreach ($rooms as $room): if ((int) $room->floor_id !== (int) $floor->id) continue; $area = $areasById[(int) $room->area_id] ?? null; ?>
            <details class="border rounded p-2"><summary><strong><?= htmlspecialchars(StructureController::roomIdentifier($room, $floor, $area)) ?></strong> · <?= htmlspecialchars((string) $room->name) ?></summary><div class="pt-3"><?php if ($canManage) $form('room', $room); ?></div></details>
          <?php endforeach; ?>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>
