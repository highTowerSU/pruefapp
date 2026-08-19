<div id="sevdesk-contact-person-select">
  <?php if (!empty($error)): ?>
    <div class="alert alert-warning py-2 mb-0"><i class="fa-solid fa-triangle-exclamation me-1" aria-hidden="true"></i><?= htmlspecialchars((string) $error) ?></div>
  <?php else: ?>
    <select class="form-select" id="sevdesk_contact_person_id" name="sevdesk_contact_person_id">
      <option value="">Automatisch bei genau einem Benutzer</option>
      <?php foreach (($users ?? []) as $user): ?><option value="<?= htmlspecialchars((string) $user['id'], ENT_QUOTES) ?>"<?= (string) ($selectedId ?? '') === (string) $user['id'] ? ' selected' : '' ?>><?= htmlspecialchars((string) $user['label']) ?></option><?php endforeach; ?>
    </select>
    <div class="form-text">Die Person wird auf SevDesk als Ansprechperson des Rechnungsentwurfs hinterlegt.</div>
  <?php endif; ?>
</div>
