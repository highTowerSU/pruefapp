<tr>
    <td><?= htmlspecialchars($user->vorname) ?></td>
    <td><?= htmlspecialchars($user->nachname) ?></td>
    <td><?= htmlspecialchars($user->geburtsdatum) ?></td>
    <td><?= htmlspecialchars($user->geburtsort) ?></td>
    <td><?= htmlspecialchars($user->benutzername) ?></td>
    <td><?= htmlspecialchars($user->passwort) ?></td>
    <td><?= htmlspecialchars($user->email) ?></td>
	<td>
        <button type="button"
        class="btn btn-sm btn-danger btn-popover-confirm"
        data-teilnehmer-id="<?= $t->id ?>"
        data-bs-toggle="popover"
        data-bs-trigger="focus"
        data-bs-content="Wirklich löschen?"
        title="Bestätigung erforderlich">
  <i class="fa-solid fa-trash"></i>
</button>

    </td>
</tr>
