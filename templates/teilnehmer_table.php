<?php $canManageParticipants = $canManageParticipants ?? current_user_has_role('admin'); ?>

<div class="d-flex flex-wrap gap-2 mb-3">
  <?php if ($canManageParticipants): ?>
    <a href="<?= htmlspecialchars(url_for('kurse/' . (int) $kurs->id . '/teilnehmer/import'), ENT_QUOTES) ?>" class="btn btn-sm btn-success">
      <i class="fa-solid fa-file-import"></i> Import
    </a>
    <a href="<?= htmlspecialchars(url_for('kurse/' . (int) $kurs->id . '/teilnehmer/export'), ENT_QUOTES) ?>" class="btn btn-sm btn-outline-primary">
      <i class="fa-solid fa-file-export"></i> Export (CSV)
    </a>
    <a href="<?= htmlspecialchars(url_for('kurse/' . (int) $kurs->id . '/teilnehmer/moodle'), ENT_QUOTES) ?>" class="btn btn-sm btn-outline-info">
      <i class="fa-solid fa-cloud-arrow-up"></i> Moodle-Import
    </a>
  <?php endif; ?>
  <a href="<?= htmlspecialchars(url_for('kurse/' . (int) $kurs->id . '/teilnehmer/druck'), ENT_QUOTES) ?>" class="btn btn-sm btn-outline-secondary" target="_blank">
    <i class="fa-solid fa-print"></i> Druckansicht
  </a>
  <a href="<?= htmlspecialchars(url_for('kurse'), ENT_QUOTES) ?>" class="btn btn-sm btn-link">Zurück zur Übersicht</a>
</div>

<?php if (!empty($kurs->moodle_course_shortname ?? '')): ?>
  <p class="mb-2 text-muted small">
    Moodle-Kurszuordnung: <code><?= htmlspecialchars($kurs->moodle_course_shortname, ENT_QUOTES) ?></code>
    <?php if (!empty($kurs->moodle_course_fullname ?? '') && $kurs->moodle_course_fullname !== $kurs->name): ?>
      – <?= htmlspecialchars($kurs->moodle_course_fullname, ENT_QUOTES) ?>
    <?php endif; ?>
  </p>
<?php endif; ?>

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
