<?php
$deviceManufacturer = trim((string) ($device->manufacturer ?? ''));
$deviceModel = trim((string) ($device->device_model ?? ''));
$inventoryNumber = trim((string) ($device->inventory_number ?? ''));
$serialNumber = trim((string) ($device->serial_number ?? ''));
?>
<div class="border rounded-3 bg-body-tertiary p-3 mb-3">
  <div class="fw-semibold"><i class="fa-solid fa-plug me-1" aria-hidden="true"></i>Gerät: <?= htmlspecialchars((string) $device->external_number) ?> · <?= htmlspecialchars((string) $device->name) ?></div>
  <div class="d-flex flex-wrap gap-2 mt-2" aria-label="Gerätestammdaten">
    <span class="badge text-bg-<?= $deviceManufacturer !== '' ? 'primary' : 'warning' ?>">Hersteller: <?= htmlspecialchars($deviceManufacturer !== '' ? $deviceManufacturer : 'nicht hinterlegt') ?></span>
    <span class="badge text-bg-<?= $deviceModel !== '' ? 'secondary' : 'warning' ?>">Modell: <?= htmlspecialchars($deviceModel !== '' ? $deviceModel : 'nicht hinterlegt') ?></span>
    <?php if ($inventoryNumber !== ''): ?><span class="badge bg-body border text-body">Inventarnummer: <?= htmlspecialchars($inventoryNumber) ?></span><?php endif; ?>
    <?php if ($serialNumber !== ''): ?><span class="badge bg-body border text-body">Seriennummer: <?= htmlspecialchars($serialNumber) ?></span><?php endif; ?>
  </div>
</div>
