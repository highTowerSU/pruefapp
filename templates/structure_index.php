<?php
/** @var array<int, RedBeanPHP\OODBBean> $customers */
/** @var array<int, RedBeanPHP\OODBBean> $sites */
/** @var array<int, RedBeanPHP\OODBBean> $buildings */
/** @var array<int, RedBeanPHP\OODBBean> $floors */
/** @var array<int, RedBeanPHP\OODBBean> $rooms */
/** @var array<int, RedBeanPHP\OODBBean> $devices */
/** @var bool $canManage */

$customersById = [];
foreach ($customers as $customer) {
    $customersById[(int) $customer->id] = $customer;
}
?>

<div class="row g-4">
  <div class="col-lg-6">
    <div class="card shadow-sm h-100">
      <div class="card-header"><h2 class="h5 mb-0">Kunden</h2></div>
      <div class="card-body">
        <?php if ($canManage): ?>
          <form method="post" action="<?= htmlspecialchars(url_for('struktur/kunden'), ENT_QUOTES) ?>" class="row g-2 mb-3">
            <div class="col-12">
              <input type="text" name="name" class="form-control" placeholder="Kundenname" required>
            </div>
            <div class="col-12">
              <select name="parent_customer_id" class="form-select">
                <option value="0">Kein Unterkunde</option>
                <?php foreach ($customers as $customer): ?>
                  <option value="<?= (int) $customer->id ?>"><?= htmlspecialchars((string) $customer->name, ENT_QUOTES) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-12 text-end"><button type="submit" class="btn btn-primary btn-sm">Speichern</button></div>
          </form>
        <?php endif; ?>

        <ul class="list-group list-group-flush">
          <?php foreach ($customers as $customer): ?>
            <?php $parentId = (int) ($customer->parent_customer_id ?? 0); ?>
            <li class="list-group-item d-flex justify-content-between">
              <span><?= htmlspecialchars((string) $customer->name, ENT_QUOTES) ?></span>
              <small class="text-body-secondary">
                <?= $parentId > 0 && isset($customersById[$parentId]) ? 'Unterkunde von ' . htmlspecialchars((string) $customersById[$parentId]->name, ENT_QUOTES) : 'Hauptkunde' ?>
              </small>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
    </div>
  </div>

  <div class="col-lg-6">
    <div class="card shadow-sm h-100">
      <div class="card-header"><h2 class="h5 mb-0">Standorte</h2></div>
      <div class="card-body">
        <?php if ($canManage): ?>
          <form method="post" action="<?= htmlspecialchars(url_for('struktur/standorte'), ENT_QUOTES) ?>" class="row g-2 mb-3">
            <div class="col-7"><input type="text" name="name" class="form-control" placeholder="Standortname" required></div>
            <div class="col-5">
              <select name="customer_id" class="form-select" required>
                <option value="">Kunde wählen</option>
                <?php foreach ($customers as $customer): ?><option value="<?= (int) $customer->id ?>"><?= htmlspecialchars((string) $customer->name, ENT_QUOTES) ?></option><?php endforeach; ?>
              </select>
            </div>
            <div class="col-12 text-end"><button type="submit" class="btn btn-primary btn-sm">Speichern</button></div>
          </form>
        <?php endif; ?>

        <ul class="list-group list-group-flush"><?php foreach ($sites as $site): ?><li class="list-group-item"><?= htmlspecialchars((string) $site->name, ENT_QUOTES) ?></li><?php endforeach; ?></ul>
      </div>
    </div>
  </div>

  <div class="col-12">
    <div class="card shadow-sm">
      <div class="card-header"><h2 class="h5 mb-0">Gebäude, Etagen, Räume und Geräte</h2></div>
      <div class="card-body">
        <?php if ($canManage): ?>
          <div class="row g-3 mb-4">
            <div class="col-md-6 col-xl-3">
              <form method="post" action="<?= htmlspecialchars(url_for('struktur/gebaeude'), ENT_QUOTES) ?>" class="vstack gap-2">
                <strong>Gebäude</strong>
                <input type="text" name="name" class="form-control" placeholder="Gebäudename" required>
                <select name="site_id" class="form-select" required>
                  <option value="">Standort wählen</option>
                  <?php foreach ($sites as $site): ?><option value="<?= (int) $site->id ?>"><?= htmlspecialchars((string) $site->name, ENT_QUOTES) ?></option><?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-outline-primary btn-sm">Hinzufügen</button>
              </form>
            </div>
            <div class="col-md-6 col-xl-3">
              <form method="post" action="<?= htmlspecialchars(url_for('struktur/etagen'), ENT_QUOTES) ?>" class="vstack gap-2">
                <strong>Etage</strong>
                <input type="text" name="name" class="form-control" placeholder="z. B. EG" required>
                <select name="building_id" class="form-select" required>
                  <option value="">Gebäude wählen</option>
                  <?php foreach ($buildings as $building): ?><option value="<?= (int) $building->id ?>"><?= htmlspecialchars((string) $building->name, ENT_QUOTES) ?></option><?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-outline-primary btn-sm">Hinzufügen</button>
              </form>
            </div>
            <div class="col-md-6 col-xl-3">
              <form method="post" action="<?= htmlspecialchars(url_for('struktur/raeume'), ENT_QUOTES) ?>" class="vstack gap-2">
                <strong>Raum</strong>
                <input type="text" name="name" class="form-control" placeholder="z. B. 0.12" required>
                <select name="floor_id" class="form-select" required>
                  <option value="">Etage wählen</option>
                  <?php foreach ($floors as $floor): ?><option value="<?= (int) $floor->id ?>"><?= htmlspecialchars((string) $floor->name, ENT_QUOTES) ?></option><?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-outline-primary btn-sm">Hinzufügen</button>
              </form>
            </div>
            <div class="col-md-6 col-xl-3">
              <form method="post" action="<?= htmlspecialchars(url_for('struktur/geraete'), ENT_QUOTES) ?>" class="vstack gap-2">
                <strong>Gerät</strong>
                <input type="text" name="name" class="form-control" placeholder="Gerätebezeichnung" required>
                <select name="room_id" class="form-select" required>
                  <option value="">Raum wählen</option>
                  <?php foreach ($rooms as $room): ?><option value="<?= (int) $room->id ?>"><?= htmlspecialchars((string) $room->name, ENT_QUOTES) ?></option><?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-outline-primary btn-sm">Hinzufügen</button>
              </form>
            </div>
          </div>
        <?php endif; ?>

        <div class="row g-3">
          <div class="col-md-3"><h3 class="h6">Gebäude (<?= count($buildings) ?>)</h3><ul><?php foreach ($buildings as $building): ?><li><?= htmlspecialchars((string) $building->name, ENT_QUOTES) ?></li><?php endforeach; ?></ul></div>
          <div class="col-md-3"><h3 class="h6">Etagen (<?= count($floors) ?>)</h3><ul><?php foreach ($floors as $floor): ?><li><?= htmlspecialchars((string) $floor->name, ENT_QUOTES) ?></li><?php endforeach; ?></ul></div>
          <div class="col-md-3"><h3 class="h6">Räume (<?= count($rooms) ?>)</h3><ul><?php foreach ($rooms as $room): ?><li><?= htmlspecialchars((string) $room->name, ENT_QUOTES) ?></li><?php endforeach; ?></ul></div>
          <div class="col-md-3"><h3 class="h6">Geräte (<?= count($devices) ?>)</h3><ul><?php foreach ($devices as $device): ?><li><?= htmlspecialchars((string) $device->name, ENT_QUOTES) ?></li><?php endforeach; ?></ul></div>
        </div>
      </div>
    </div>
  </div>
</div>
