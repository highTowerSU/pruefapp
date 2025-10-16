<?php $canManageParticipants = $canManageParticipants ?? current_user_has_role('admin'); ?>

<div class="d-flex flex-wrap gap-2 mb-3">
  <?php if ($canManageParticipants): ?>
    <a href="<?= htmlspecialchars(url_for('kurse/' . (int) $kurs->id . '/teilnehmer/import'), ENT_QUOTES) ?>" class="btn btn-sm btn-success">
      <i class="fa-solid fa-file-import"></i> Import
    </a>
    <a href="<?= htmlspecialchars(url_for('kurse/' . (int) $kurs->id . '/teilnehmer/export'), ENT_QUOTES) ?>" class="btn btn-sm btn-outline-primary">
      <i class="fa-solid fa-file-export"></i> Export (CSV)
    </a>
  <?php endif; ?>
  <a href="<?= htmlspecialchars(url_for('kurse/' . (int) $kurs->id . '/teilnehmer/druck'), ENT_QUOTES) ?>" class="btn btn-sm btn-outline-secondary" target="_blank">
    <i class="fa-solid fa-print"></i> Druckansicht
  </a>
  <a href="<?= htmlspecialchars(url_for('kurse'), ENT_QUOTES) ?>" class="btn btn-sm btn-link">Zurück zur Übersicht</a>
</div>

<div class="d-flex justify-content-between align-items-center mb-2">
  <?php if ($canManageParticipants): ?>
    <p class="mb-0 text-muted">Einträge werden beim Bearbeiten automatisch gespeichert.</p>
    <button type="button" id="btn-add-row" class="btn btn-sm btn-primary">
      <i class="fa-solid fa-plus"></i> Neue Zeile
    </button>
  <?php else: ?>
    <p class="mb-0 text-muted">Teilnehmerdaten können in dieser Rolle nur eingesehen werden.</p>
  <?php endif; ?>
</div>

<div id="teilnehmer-tabelle" data-kurs-id="<?= (int) $kurs->id ?>" data-can-manage="<?= $canManageParticipants ? '1' : '0' ?>"></div>
