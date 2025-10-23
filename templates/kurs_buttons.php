<?php $canManageCourses = current_user_can_manage_courses(); ?>
<div class="d-flex flex-wrap gap-2">
  <?php if ($canManageCourses): ?>
    <a href="<?= htmlspecialchars(url_for('kurse/' . (int) $kurs->id . '/teilnehmer/import'), ENT_QUOTES) ?>" class="btn btn-sm btn-success">
      <i class="fa-solid fa-file-import"></i> Import
    </a>
  <?php endif; ?>
  <a href="<?= htmlspecialchars(url_for('kurse/' . (int) $kurs->id . '/teilnehmer'), ENT_QUOTES) ?>" class="btn btn-sm btn-primary">
    <i class="fa-solid fa-users"></i> Teilnehmer
  </a>
  <?php if ($canManageCourses): ?>
    <a href="<?= htmlspecialchars(url_for('kurse/' . (int) $kurs->id . '/einstellungen'), ENT_QUOTES) ?>" class="btn btn-sm btn-secondary">
      <i class="fa-solid fa-gear"></i> Einstellungen
    </a>
    <a href="<?= htmlspecialchars(url_for('kurse/' . (int) $kurs->id . '/link'), ENT_QUOTES) ?>" class="btn btn-sm btn-info">
      <i class="fa-solid fa-link"></i> Link
    </a>
    <button type="button"
            class="btn btn-sm btn-danger"
            data-double-confirm
            hx-delete="<?= htmlspecialchars(url_for('kurse/' . $kurs->id), ENT_QUOTES) ?>"
            hx-target="#kurs-tabelle"
            hx-swap="outerHTML"
            hx-trigger="confirmed">
      <span data-label-default>
        <i class="fa-solid fa-trash"></i> Löschen
      </span>
      <span data-label-confirm class="d-none">
        <i class="fa-solid fa-circle-exclamation"></i> Nochmal klicken
      </span>
    </button>
  <?php endif; ?>
</div>
