<?php $canManageParticipants = $canManageParticipants ?? current_user_can_manage_participants(); ?>

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
    <p class="mb-0 text-muted">Änderungen werden nach dem Speichern übernommen.</p>
    <button type="button"
            id="btn-add-row"
            class="btn btn-sm btn-primary"
            hx-get="<?= htmlspecialchars(url_for('kurse/' . (int) $kurs->id . '/teilnehmer/zeilen/neu'), ENT_QUOTES) ?>"
            hx-target="#teilnehmer-rows"
            hx-swap="beforeend"
            hx-trigger="addRow">
      <i class="fa-solid fa-plus"></i> Neue Zeile
    </button>
  <?php else: ?>
    <p class="mb-0 text-muted">Teilnehmerdaten können in dieser Rolle nur eingesehen werden.</p>
  <?php endif; ?>
</div>

<div class="table-responsive">
  <table class="table table-striped table-hover table-sm align-middle" id="teilnehmer-tabelle">
    <thead>
      <tr>
        <th scope="col">Vorname</th>
        <th scope="col">Nachname</th>
        <th scope="col">Firma</th>
        <th scope="col">Geburtsdatum</th>
        <th scope="col">Geburtsort</th>
        <th scope="col">Benutzername</th>
        <th scope="col">E-Mail</th>
        <?php if ($canManageParticipants): ?>
          <th scope="col" class="text-end">Aktion</th>
        <?php endif; ?>
      </tr>
    </thead>
    <tbody id="teilnehmer-rows" hx-target="closest tr" hx-swap="outerHTML">
      <?php if (!empty($teilnehmer) && is_iterable($teilnehmer)): ?>
        <?php foreach ($teilnehmer as $tn): ?>
          <?= render_template('teilnehmer_table_row.php', [
            'kurs' => $kurs,
            'teilnehmer' => $tn,
            'canManageParticipants' => $canManageParticipants,
          ]) ?>
        <?php endforeach; ?>
      <?php else: ?>
        <tr data-empty-row="true">
          <td colspan="<?= $canManageParticipants ? '8' : '7' ?>" class="text-center text-muted py-4">
            Keine Teilnehmer vorhanden.
          </td>
        </tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>
