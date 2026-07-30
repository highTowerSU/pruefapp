<?php
$form = static function ($device = null) use ($rooms, $roomLabels): void {
    $device ??= (object) ['id' => 0, 'name' => '', 'room_id' => 0, 'serial_number' => '', 'inventory_number' => '', 'description' => '', 'comment' => '', 'metadata_json' => '{}'];
    $metadataValue = trim((string) ($device->metadata_json ?? ''));
    if ($metadataValue === '{}') $metadataValue = '';
?>
  <form method="post" action="<?= htmlspecialchars(url_for('geraete'), ENT_QUOTES) ?>" class="row g-2">
    <input type="hidden" name="id" value="<?= (int) $device->id ?>">
    <div class="col-md-4"><label class="form-label">Gerätebezeichnung</label><input class="form-control" name="name" required value="<?= htmlspecialchars((string) $device->name) ?>"></div>
    <div class="col-md-4"><label class="form-label">Raum</label><select class="form-select" name="room_id" required><option value="">Raum wählen</option><?php foreach ($rooms as $room): ?><option value="<?= (int) $room->id ?>"<?= (int) $device->room_id === (int) $room->id ? ' selected' : '' ?>><?= htmlspecialchars($roomLabels[(int) $room->id] ?? (string) $room->name) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-2"><label class="form-label">Inventarnummer</label><input class="form-control" name="inventory_number" value="<?= htmlspecialchars((string) $device->inventory_number) ?>"></div>
    <div class="col-md-2"><label class="form-label">Seriennummer</label><input class="form-control" name="serial_number" value="<?= htmlspecialchars((string) $device->serial_number) ?>"></div>
    <div class="col-12"><label class="form-label">Kurzbeschreibung</label><textarea class="form-control" name="description" rows="2" maxlength="240" placeholder="Funktion, Bauart oder Einsatz des Geräts"><?= htmlspecialchars((string) $device->description) ?></textarea><div class="form-text">Wird direkt in der Geräteübersicht angezeigt, maximal 240 Zeichen.</div></div>
    <div class="col-md-6"><label class="form-label">Kommentar</label><textarea class="form-control" name="comment" placeholder="Interne Hinweise und Bemerkungen"><?= htmlspecialchars((string) $device->comment) ?></textarea></div>
    <div class="col-md-6"><label class="form-label">Metadaten (JSON-Objekt, optional)</label><textarea class="form-control font-monospace" name="metadata_json" placeholder='z. B. {"kostenstelle":"1000"}'><?= htmlspecialchars($metadataValue) ?></textarea></div>
    <div class="col-12 text-end"><button class="btn btn-primary btn-sm">Speichern</button></div>
  </form>
<?php }; ?>

<?php if ($canManage): ?><div class="card mb-4"><div class="card-header"><strong>Neues Gerät</strong></div><div class="card-body"><?php $form(); ?></div></div><?php endif; ?>
<div class="vstack gap-3">
<?php foreach ($devices as $device): ?>
  <details class="card"><summary class="card-header"><strong><?= htmlspecialchars((string) $device->name) ?></strong> · <?= htmlspecialchars($roomLabels[(int) $device->room_id] ?? 'ohne Raum') ?><?php if (trim((string) $device->description) !== ''): ?><span class="d-block small text-body-secondary mt-1"><?= htmlspecialchars((string) $device->description) ?></span><?php endif; ?></summary>
    <div class="card-body"><?php if ($canManage): $form($device); else: ?><p><?= nl2br(htmlspecialchars((string) $device->comment)) ?></p><?php endif; ?></div>
  </details>
<?php endforeach; ?>
</div>
