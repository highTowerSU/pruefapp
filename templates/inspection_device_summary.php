<?php
$deviceManufacturer = trim((string) ($device->manufacturer ?? ''));
$deviceModel = trim((string) ($device->device_model ?? ''));
$inventoryNumber = trim((string) ($device->inventory_number ?? ''));
$serialNumber = trim((string) ($device->serial_number ?? ''));
$legacyNumber = trim((string) ($device->legacy_number ?? ''));
$roomSnapshot = trim((string) ($device->room_snapshot ?? ''));
$description = trim((string) ($device->description ?? ''));
?>
<div class="border rounded-3 bg-body-tertiary p-3 mb-3">
  <div class="fw-semibold"><i class="fa-solid fa-plug me-1" aria-hidden="true"></i>Gerät: <?= htmlspecialchars((string) $device->external_number) ?> · <?= htmlspecialchars((string) $device->name) ?></div>
  <div class="d-flex flex-wrap gap-2 mt-2" aria-label="Gerätestammdaten">
    <span class="badge text-bg-<?= $deviceManufacturer !== '' ? 'primary' : 'warning' ?>">Hersteller: <?= htmlspecialchars($deviceManufacturer !== '' ? $deviceManufacturer : 'nicht hinterlegt') ?></span>
    <span class="badge text-bg-<?= $deviceModel !== '' ? 'secondary' : 'warning' ?>">Modell: <?= htmlspecialchars($deviceModel !== '' ? $deviceModel : 'nicht hinterlegt') ?></span>
    <span class="badge <?= $inventoryNumber !== '' ? 'bg-body border text-body' : 'text-bg-warning' ?>">Inventarnummer: <?= htmlspecialchars($inventoryNumber !== '' ? $inventoryNumber : 'nicht hinterlegt') ?></span>
    <span class="badge <?= $serialNumber !== '' ? 'bg-body border text-body' : 'text-bg-warning' ?>">Seriennummer: <?= htmlspecialchars($serialNumber !== '' ? $serialNumber : 'nicht hinterlegt') ?></span>
    <?php if ($legacyNumber !== ''): ?><span class="badge bg-body border text-body">Alte Nummer: <?= htmlspecialchars($legacyNumber) ?></span><?php endif; ?>
    <?php if ($roomSnapshot !== ''): ?><span class="badge bg-body border text-body">Raum laut Gerät: <?= htmlspecialchars($roomSnapshot) ?></span><?php endif; ?>
  </div>
  <?php if ($description !== ''): ?><p class="small text-body-secondary mt-2 mb-0">Kurzbeschreibung: <?= htmlspecialchars($description) ?></p><?php endif; ?>
</div>
