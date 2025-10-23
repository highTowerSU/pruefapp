<?php
/** @var \RedBeanPHP\OODBBean $kurs */
/** @var \RedBeanPHP\OODBBean $teilnehmer */
/** @var bool $canManageParticipants */
?>
<tr data-teilnehmer-id="<?= (int) $teilnehmer->id ?>">
  <td><?= htmlspecialchars((string) $teilnehmer->vorname, ENT_QUOTES) ?></td>
  <td><?= htmlspecialchars((string) $teilnehmer->nachname, ENT_QUOTES) ?></td>
  <td><?= htmlspecialchars((string) ($teilnehmer->firma ?? ''), ENT_QUOTES) ?></td>
  <td><?= htmlspecialchars(format_birthdate_for_display((string) $teilnehmer->geburtsdatum), ENT_QUOTES) ?></td>
  <td><?= htmlspecialchars((string) $teilnehmer->geburtsort, ENT_QUOTES) ?></td>
  <td><?= htmlspecialchars((string) $teilnehmer->benutzername, ENT_QUOTES) ?></td>
  <td><?= htmlspecialchars((string) ($teilnehmer->email ?? ''), ENT_QUOTES) ?></td>
  <?php if ($canManageParticipants): ?>
    <td class="text-end">
      <div class="btn-group btn-group-sm" role="group">
        <button type="button"
                class="btn btn-outline-secondary"
                data-action="edit-participant"
                hx-get="<?= htmlspecialchars(url_for('kurse/' . (int) $kurs->id . '/teilnehmer/' . (int) $teilnehmer->id . '/bearbeiten'), ENT_QUOTES) ?>"
                hx-trigger="edit">
          <i class="fa-solid fa-pen"></i>
          <span class="visually-hidden">Bearbeiten</span>
        </button>
        <button type="button"
                class="btn btn-outline-danger"
                hx-delete="<?= htmlspecialchars(url_for('kurse/' . (int) $kurs->id . '/teilnehmer/' . (int) $teilnehmer->id), ENT_QUOTES) ?>"
                hx-confirm="Teilnehmer wirklich löschen?">
          <i class="fa-solid fa-trash"></i>
          <span class="visually-hidden">Löschen</span>
        </button>
      </div>
    </td>
  <?php endif; ?>
</tr>
