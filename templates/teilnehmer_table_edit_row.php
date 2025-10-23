<?php
/** @var \RedBeanPHP\OODBBean $kurs */
/** @var array $values */
/** @var array $errors */
/** @var bool $isNew */
/** @var bool $canManageParticipants */
/** @var \RedBeanPHP\OODBBean|null $teilnehmer */
$requiresBirthplace = ((int) ($kurs->feld_geburtsort_aktiv ?? 0)) === 1;
$usernameHint = $isNew
    ? 'Wird beim Speichern automatisch erzeugt.'
    : ((string) ($teilnehmer->benutzername ?? ''));
$emailPlaceholder = $isNew ? 'Wird automatisch erzeugt, falls leer.' : '';
$generalError = $errors['general'] ?? '';
?>
<tr class="editing"
    <?php if ($isNew): ?>
      hx-on::cancel="this.remove()"
    <?php else: ?>
      hx-trigger="cancel"
      hx-get="<?= htmlspecialchars(url_for('kurse/' . (int) $kurs->id . '/teilnehmer/' . (int) $teilnehmer->id . '/zeile'), ENT_QUOTES) ?>"
      hx-target="closest tr"
      hx-swap="outerHTML"
    <?php endif; ?>
>
  <td class="align-top">
    <input type="text"
           name="vorname"
           class="form-control form-control-sm<?= isset($errors['vorname']) ? ' is-invalid' : '' ?>"
           value="<?= htmlspecialchars($values['vorname'] ?? '', ENT_QUOTES) ?>"
           autocomplete="given-name"
           required>
    <?php if (isset($errors['vorname'])): ?>
      <div class="invalid-feedback d-block"><?= htmlspecialchars($errors['vorname'], ENT_QUOTES) ?></div>
    <?php endif; ?>
  </td>
  <td class="align-top">
    <input type="text"
           name="nachname"
           class="form-control form-control-sm<?= isset($errors['nachname']) ? ' is-invalid' : '' ?>"
           value="<?= htmlspecialchars($values['nachname'] ?? '', ENT_QUOTES) ?>"
           autocomplete="family-name"
           required>
    <?php if (isset($errors['nachname'])): ?>
      <div class="invalid-feedback d-block"><?= htmlspecialchars($errors['nachname'], ENT_QUOTES) ?></div>
    <?php endif; ?>
  </td>
  <td class="align-top">
    <input type="text"
           name="firma"
           class="form-control form-control-sm"
           value="<?= htmlspecialchars($values['firma'] ?? '', ENT_QUOTES) ?>"
           autocomplete="organization">
  </td>
  <td class="align-top">
    <input type="date"
           name="geburtsdatum"
           class="form-control form-control-sm<?= isset($errors['geburtsdatum']) ? ' is-invalid' : '' ?>"
           value="<?= htmlspecialchars($values['geburtsdatum'] ?? '', ENT_QUOTES) ?>"
           required>
    <?php if (isset($errors['geburtsdatum'])): ?>
      <div class="invalid-feedback d-block"><?= htmlspecialchars($errors['geburtsdatum'], ENT_QUOTES) ?></div>
    <?php endif; ?>
  </td>
  <td class="align-top">
    <input type="text"
           name="geburtsort"
           class="form-control form-control-sm<?= isset($errors['geburtsort']) ? ' is-invalid' : '' ?>"
           value="<?= htmlspecialchars($values['geburtsort'] ?? '', ENT_QUOTES) ?>"
           <?= $requiresBirthplace ? 'required' : '' ?>>
    <?php if ($requiresBirthplace): ?>
      <div class="form-text">Pflichtfeld</div>
    <?php endif; ?>
    <?php if (isset($errors['geburtsort'])): ?>
      <div class="invalid-feedback d-block"><?= htmlspecialchars($errors['geburtsort'], ENT_QUOTES) ?></div>
    <?php endif; ?>
  </td>
  <td class="align-top">
    <div class="form-control-plaintext py-0">
      <?= htmlspecialchars($usernameHint, ENT_QUOTES) ?>
    </div>
  </td>
  <td class="align-top">
    <input type="email"
           name="email"
           class="form-control form-control-sm<?= isset($errors['email']) ? ' is-invalid' : '' ?>"
           value="<?= htmlspecialchars($values['email'] ?? '', ENT_QUOTES) ?>"
           placeholder="<?= htmlspecialchars($emailPlaceholder, ENT_QUOTES) ?>"
           autocomplete="email">
    <?php if (isset($errors['email'])): ?>
      <div class="invalid-feedback d-block"><?= htmlspecialchars($errors['email'], ENT_QUOTES) ?></div>
    <?php endif; ?>
  </td>
  <td class="align-top">
    <div class="form-control-plaintext py-0">
      <?php if ($teilnehmer && isset($teilnehmer->moodle_user_id) && (int) $teilnehmer->moodle_user_id > 0): ?>
        <?= (int) $teilnehmer->moodle_user_id ?>
      <?php else: ?>
        –
      <?php endif; ?>
    </div>
  </td>
  <td class="align-top">
    <div class="form-control-plaintext py-0">
      <?php if ($teilnehmer && isset($teilnehmer->moodle_last_sync_at) && $teilnehmer->moodle_last_sync_at !== ''): ?>
        <?= htmlspecialchars(format_datetime_for_display($teilnehmer->moodle_last_sync_at), ENT_QUOTES) ?>
      <?php else: ?>
        –
      <?php endif; ?>
    </div>
  </td>
  <?php if ($canManageParticipants): ?>
    <td class="align-top text-end">
      <div class="btn-group btn-group-sm" role="group">
        <button type="button"
                class="btn btn-outline-secondary"
                data-action="cancel-edit">
          Abbrechen
        </button>
        <button type="button"
                class="btn btn-primary"
                hx-post="<?= htmlspecialchars($isNew
                    ? url_for('kurse/' . (int) $kurs->id . '/teilnehmer')
                    : url_for('kurse/' . (int) $kurs->id . '/teilnehmer/' . (int) $teilnehmer->id),
                ENT_QUOTES) ?>"
                hx-include="closest tr"
                hx-target="closest tr"
                hx-swap="outerHTML">
          Speichern
        </button>
      </div>
      <?php if ($generalError !== ''): ?>
        <div class="text-danger small mt-2"><?= htmlspecialchars($generalError, ENT_QUOTES) ?></div>
      <?php endif; ?>
    </td>
  <?php endif; ?>
</tr>
