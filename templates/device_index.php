<?php
$form = static function ($device = null) use ($rooms, $roomLabels): void {
    $device ??= (object) ['id' => 0, 'name' => '', 'room_id' => 0, 'serial_number' => '', 'inventory_number' => '', 'comment' => '', 'metadata_json' => '{}'];
?>
  <form method="post" action="<?= htmlspecialchars(url_for('geraete'), ENT_QUOTES) ?>" class="row g-2">
    <input type="hidden" name="id" value="<?= (int) $device->id ?>">
    <div class="col-md-4"><input class="form-control" name="name" required placeholder="Gerätebezeichnung" value="<?= htmlspecialchars((string) $device->name) ?>"></div>
    <div class="col-md-4"><select class="form-select" name="room_id" required><option value="">Raum wählen</option><?php foreach ($rooms as $room): ?><option value="<?= (int) $room->id ?>"<?= (int) $device->room_id === (int) $room->id ? ' selected' : '' ?>><?= htmlspecialchars($roomLabels[(int) $room->id] ?? (string) $room->name) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-2"><input class="form-control" name="inventory_number" placeholder="Inventarnr." value="<?= htmlspecialchars((string) $device->inventory_number) ?>"></div>
    <div class="col-md-2"><input class="form-control" name="serial_number" placeholder="Seriennr." value="<?= htmlspecialchars((string) $device->serial_number) ?>"></div>
    <div class="col-md-6"><textarea class="form-control" name="comment" placeholder="Kommentar"><?= htmlspecialchars((string) $device->comment) ?></textarea></div>
    <div class="col-md-6"><textarea class="form-control font-monospace" name="metadata_json" placeholder='{"schlüssel":"wert"}'><?= htmlspecialchars((string) ($device->metadata_json ?: '{}')) ?></textarea></div>
    <div class="col-12 text-end"><button class="btn btn-primary btn-sm">Speichern</button></div>
  </form>
<?php }; ?>

<?php if ($canManage): ?><div class="card mb-4"><div class="card-header"><strong>Neues Gerät</strong></div><div class="card-body"><?php $form(); ?></div></div><?php endif; ?>
<div class="vstack gap-3">
<?php foreach ($devices as $device): ?>
  <details class="card"><summary class="card-header"><strong><?= htmlspecialchars((string) $device->name) ?></strong> · <?= htmlspecialchars($roomLabels[(int) $device->room_id] ?? 'ohne Raum') ?></summary>
    <div class="card-body"><?php if ($canManage): $form($device); else: ?><p><?= nl2br(htmlspecialchars((string) $device->comment)) ?></p><?php endif; ?></div>
  </details>
<?php endforeach; ?>
</div>
