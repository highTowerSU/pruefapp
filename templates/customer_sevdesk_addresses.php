<?php

declare(strict_types=1);

$format = static function (array $address): string {
    return implode(' · ', array_filter([
        trim(implode(' ', array_filter([(string) ($address['name'] ?? ''), (string) ($address['name2'] ?? '')]))),
        trim((string) ($address['street'] ?? '')),
        trim(trim((string) ($address['zip'] ?? '')) . ' ' . trim((string) ($address['city'] ?? ''))),
        trim((string) ($address['country']['name'] ?? $address['countryName'] ?? '')),
    ]));
};
?>
<?php if ($error !== ''): ?>
  <div class="alert alert-warning py-2 mb-0">SevDesk-Adressen konnten nicht geladen werden: <?= htmlspecialchars($error) ?></div>
<?php else: ?>
  <div class="row g-2">
    <div class="col-md-7">
      <label class="form-label" for="sevdesk-address-<?= (int) $customer->id ?>">Adresse aus SevDesk übernehmen</label>
      <select class="form-select" id="sevdesk-address-<?= (int) $customer->id ?>" data-sevdesk-address-choice>
        <option value="">Bitte wählen</option>
        <?php foreach ($addresses as $address): $id = trim((string) ($address['id'] ?? '')); if ($id === '') continue; ?>
          <option value="<?= htmlspecialchars($id, ENT_QUOTES) ?>" data-name="<?= htmlspecialchars((string) ($address['name'] ?? ''), ENT_QUOTES) ?>" data-name2="<?= htmlspecialchars((string) ($address['name2'] ?? ''), ENT_QUOTES) ?>" data-street="<?= htmlspecialchars((string) ($address['street'] ?? ''), ENT_QUOTES) ?>" data-zip="<?= htmlspecialchars((string) ($address['zip'] ?? ''), ENT_QUOTES) ?>" data-city="<?= htmlspecialchars((string) ($address['city'] ?? ''), ENT_QUOTES) ?>" data-country="<?= htmlspecialchars((string) ($address['country']['name'] ?? $address['countryName'] ?? 'Deutschland'), ENT_QUOTES) ?>"<?= (string) ($customer->sevdesk_contact_address_id ?? '') === $id ? ' selected' : '' ?>><?= htmlspecialchars($format($address) ?: 'SevDesk-Adresse ' . $id) ?></option>
        <?php endforeach; ?>
      </select>
      <div class="form-text">Übernimmt die vollständige Rechnungsanschrift.</div>
    </div>
    <div class="col-md-5">
      <label class="form-label" for="sevdesk-person-<?= (int) $customer->id ?>">Ansprechpartner aus SevDesk</label>
      <select class="form-select" id="sevdesk-person-<?= (int) $customer->id ?>" data-sevdesk-person-choice>
        <option value="">Keinen auswählen</option>
        <?php foreach ($persons as $person): $id = trim((string) ($person['id'] ?? '')); if ($id === '') continue; $name = trim((string) ($person['name'] ?? implode(' ', array_filter([(string) ($person['surename'] ?? $person['firstname'] ?? ''), (string) ($person['familyname'] ?? '')])))); ?>
          <option value="<?= htmlspecialchars($id, ENT_QUOTES) ?>" data-name="<?= htmlspecialchars($name, ENT_QUOTES) ?>"<?= (string) ($customer->sevdesk_contact_person_id ?? '') === $id ? ' selected' : '' ?>><?= htmlspecialchars($name ?: 'SevDesk-Ansprechpartner ' . $id) ?></option>
        <?php endforeach; ?>
      </select>
      <div class="form-text">Übernimmt nur die Ansprechpartnerzeile.</div>
    </div>
  </div>
  <?php if ($addresses === [] && $persons === []): ?><div class="small text-body-secondary mt-2">Für diesen SevDesk-Kontakt wurden keine zusätzlichen Adressen oder Ansprechpartner gefunden.</div><?php endif; ?>
<?php endif; ?>
