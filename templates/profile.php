<?php
/** @var \RedBeanPHP\OODBBean $user */
/** @var string $signature */
/** @var array<int, array<string, string>> $followups */
/** @var array<int, array<string, string>> $certificates */
 $canEdit = $canEdit ?? true; $profileUrl = $profileUrl ?? url_for('profil'); $certificates = $certificates ?? [];
 $qualifications = $qualifications ?? []; $qualificationRequirements = $qualificationRequirements ?? []; $inspectionTypes = $inspectionTypes ?? []; $inspectionPermissions = $inspectionPermissions ?? []; $activeCompanionSessions = $activeCompanionSessions ?? []; $profileCompanionTokens = $profileCompanionTokens ?? []; $canConfirmQualifications = (bool) ($canConfirmQualifications ?? false);
 $displayPreference = $displayPreference ?? ['theme' => 'auto', 'contrast' => 'standard', 'font_scale' => 'standard', 'motion' => 'system'];
?>
<header class="page-header mb-4">
  <h1 class="mb-1"><i class="fa-solid fa-user-pen me-2" aria-hidden="true"></i><?= !empty($adminView) ? 'Benutzerprofil' : 'Mein Profil' ?></h1>
  <p class="mb-0 text-body-secondary">Persönliche Angaben für die Prüf-Dokumentation<?= !empty($adminView) ? ' · ' . htmlspecialchars((string) ($user->name ?? $user->email ?? '')) : '' ?></p>
</header>

<?php if (empty($adminView)): ?>
<section class="card shadow-sm mb-4" id="display-preferences" data-action-nav="Darstellung" data-action-icon="fa-universal-access">
  <div class="card-header fw-semibold"><i class="fa-solid fa-universal-access me-2" aria-hidden="true"></i>Darstellung &amp; Barrierefreiheit</div>
  <div class="card-body">
    <p class="small text-body-secondary">Diese Einstellungen gelten für dein Benutzerkonto auf allen angemeldeten Geräten. Browser-Zoom bleibt jederzeit zusätzlich möglich.</p>
    <?php if ($canEdit): ?><form method="post" action="<?= htmlspecialchars($profileUrl, ENT_QUOTES) ?>" class="row g-3" data-display-preferences-form><input type="hidden" name="action" value="save_display_preferences">
      <div class="col-12 col-md-6"><label class="form-label" for="display-theme">Farbschema</label><select class="form-select" id="display-theme" name="theme"><option value="auto"<?= $displayPreference['theme'] === 'auto' ? ' selected' : '' ?>>Automatisch</option><option value="light"<?= $displayPreference['theme'] === 'light' ? ' selected' : '' ?>>Hell</option><option value="dark"<?= $displayPreference['theme'] === 'dark' ? ' selected' : '' ?>>Dunkel</option></select></div>
      <div class="col-12 col-md-6"><label class="form-label" for="display-contrast">Kontrast</label><select class="form-select" id="display-contrast" name="contrast"><option value="standard"<?= $displayPreference['contrast'] === 'standard' ? ' selected' : '' ?>>Standard</option><option value="system"<?= $displayPreference['contrast'] === 'system' ? ' selected' : '' ?>>Betriebssystem-Hochkontrast</option><option value="yellow_black"<?= $displayPreference['contrast'] === 'yellow_black' ? ' selected' : '' ?>>Schwarz / Gelb</option><option value="green_black"<?= $displayPreference['contrast'] === 'green_black' ? ' selected' : '' ?>>Schwarz / Grün</option></select></div>
      <div class="col-12 col-md-6"><label class="form-label" for="display-font-scale">Leseschrift</label><select class="form-select" id="display-font-scale" name="font_scale"><option value="standard"<?= $displayPreference['font_scale'] === 'standard' ? ' selected' : '' ?>>Standardgröße</option><option value="large"<?= $displayPreference['font_scale'] === 'large' ? ' selected' : '' ?>>Größere Schrift</option></select></div>
      <div class="col-12 col-md-6"><label class="form-label" for="display-motion">Bewegung</label><select class="form-select" id="display-motion" name="motion"><option value="system"<?= $displayPreference['motion'] === 'system' ? ' selected' : '' ?>>Betriebssystemvorgabe</option><option value="reduce"<?= $displayPreference['motion'] === 'reduce' ? ' selected' : '' ?>>Bewegung reduzieren</option></select></div>
      <div class="col-12"><button class="btn btn-primary" type="submit"><i class="fa-solid fa-floppy-disk me-1" aria-hidden="true"></i>Darstellung speichern</button></div>
    </form><?php endif; ?>
  </div>
</section>
<section class="card shadow-sm mb-4" id="companion-sessions" data-action-nav="Companion-Geräte" data-action-icon="fa-mobile-screen-button">
  <div class="card-header fw-semibold d-flex justify-content-between align-items-center gap-2"><span><i class="fa-solid fa-mobile-screen-button me-2" aria-hidden="true"></i>Companion-Geräte</span><span class="badge text-bg-info"><?= count($activeCompanionSessions) ?></span></div>
  <div class="card-body">
    <p class="small text-body-secondary mb-3">Kopple dein Smartphone einmal für den Arbeitstag. Danach kann es bei Geräten und Prüfungen als Kamera- und Barcode-Companion genutzt werden; aktive Verbindungen kannst du hier beenden.</p>
    <?php if ($canEdit): ?><form method="post" action="<?= htmlspecialchars($profileUrl, ENT_QUOTES) ?>" class="mb-3"><input type="hidden" name="action" value="create_companion_workspace"><button class="btn btn-primary" type="submit"><i class="fa-solid fa-qrcode me-1" aria-hidden="true"></i>Weiteres Smartphone koppeln</button></form><?php endif; ?>
    <?php if ($activeCompanionSessions === []): ?><div class="text-body-secondary small"><i class="fa-solid fa-circle-info me-1" aria-hidden="true"></i>Keine aktiven Companion-Verbindungen.</div><?php else: ?><div class="row g-3"><?php foreach ($activeCompanionSessions as $companion): $token = (string) ($profileCompanionTokens[(int) $companion['id']] ?? ''); ?><div class="col-12 col-md-6 col-xl-4"><article class="border rounded-3 p-3 h-100"><div class="d-flex justify-content-between gap-2"><strong><i class="fa-solid fa-mobile-screen-button me-1 text-success" aria-hidden="true"></i><?= htmlspecialchars((string) ($companion['inspection_number'] ?: $companion['device_number'] ?: 'Allgemeiner Prüfplatz')) ?></strong><form method="post" action="<?= htmlspecialchars($profileUrl, ENT_QUOTES) ?>"><input type="hidden" name="action" value="disconnect_companion"><input type="hidden" name="companion_session_id" value="<?= (int) $companion['id'] ?>"><button class="btn btn-sm btn-danger" type="submit" title="Verbindung beenden"><i class="fa-solid fa-link-slash" aria-hidden="true"></i><span class="visually-hidden">Beenden</span></button></form></div><div class="small text-body-secondary mb-2"><?= ($companion['state'] ?? '') === 'connected' ? 'verbunden' : 'bereit zum Koppeln' ?> · gültig bis <?= htmlspecialchars((string) $companion['expires_at']) ?></div><?php if ($token !== ''): $pairUrl = absolute_url_for('companion/' . $token); ?><div class="d-flex align-items-center gap-2"><div class="bg-white border rounded p-1"><img src="<?= htmlspecialchars(url_for('companion/' . $token . '/qr'), ENT_QUOTES) ?>" width="104" height="104" alt="QR-Code für dieses Smartphone"></div><a class="btn btn-sm btn-secondary" href="<?= htmlspecialchars($pairUrl, ENT_QUOTES) ?>" target="_blank" rel="noopener"><i class="fa-solid fa-arrow-up-right-from-square me-1" aria-hidden="true"></i>Link öffnen</a></div><?php else: ?><div class="small text-body-secondary"><i class="fa-solid fa-shield-halved me-1" aria-hidden="true"></i>Dieser Link wurde in einer anderen Browser-Sitzung erstellt. Zum Anzeigen eines neuen QR-Codes bitte ein weiteres Smartphone koppeln.</div><?php endif; ?></article></div><?php endforeach; ?></div><?php endif; ?>
  </div>
</section>
<?php endif; ?>

<div class="row g-4">
  <div class="col-12 col-xl-7">
    <section class="card shadow-sm">
      <div class="card-header fw-semibold"><i class="fa-solid fa-pen-fancy me-2" aria-hidden="true"></i>Unterschrift für Prüfberichte</div>
      <div class="card-body">
        <p class="text-body-secondary">Die Unterschrift ist Voraussetzung zum Durchführen und Abschließen eigener Prüfungen. Nach dem Speichern werden fertige Prüfungen ohne Bericht automatisch im Hintergrund erzeugt.</p>
        <?php if ($signature !== ''): ?>
          <div class="border rounded-2 bg-white p-3 mb-3 d-inline-block">
            <img src="<?= htmlspecialchars($signature, ENT_QUOTES) ?>" alt="Gespeicherte Unterschrift" style="max-width:20rem;max-height:8rem">
          </div>
        <?php else: ?>
          <div class="alert alert-secondary"><i class="fa-solid fa-pen-fancy me-2" aria-hidden="true"></i>Noch keine Unterschrift hinterlegt.</div>
        <?php endif; ?>
        <?php if ($canEdit): ?><form id="profile-signature-panel" data-action-nav="Unterschrift" data-action-icon="fa-signature" method="post" action="<?= htmlspecialchars($profileUrl, ENT_QUOTES) ?>" enctype="multipart/form-data" class="vstack gap-3">
          <div>
            <label class="form-label" for="signature-pad"><i class="fa-solid fa-pen-fancy me-1" aria-hidden="true"></i>&nbsp;Unterschrift mit Füller zeichnen</label>
            <div class="signature-pad border rounded-3 bg-white p-2">
              <canvas id="signature-pad" width="720" height="220" aria-label="Unterschrift zeichnen" tabindex="0"></canvas>
            </div>
            <input type="hidden" name="signature_drawing" id="signature-drawing">
            <div class="d-flex gap-2 align-items-center flex-wrap mt-2"><button class="btn btn-sm btn-outline-secondary" type="button" id="signature-clear"><i class="fa-solid fa-eraser me-1" aria-hidden="true"></i>Zeichnung löschen</button><span class="form-text m-0">Mit Maus, Stift oder Finger unterschreiben.</span></div>
          </div>
          <div class="position-relative text-center text-body-secondary"><span class="bg-body px-2">oder</span><hr class="position-absolute top-50 start-0 end-0 m-0 z-n1"></div>
          <div>
            <label for="report-signature" class="form-label"><i class="fa-solid fa-image me-1" aria-hidden="true"></i>&nbsp;Bilddatei</label>
            <input class="form-control" id="report-signature" name="report_signature" type="file" accept="image/png,image/jpeg">
            <div class="form-text">Alternativ PNG oder JPEG, maximal 2 MB und 4000 × 2000 Pixel.</div>
          </div>
          <div class="d-flex gap-2 flex-wrap">
            <button class="btn btn-primary" type="submit" name="action" value="upload_signature"><i class="fa-solid fa-floppy-disk me-1" aria-hidden="true"></i>Unterschrift übernehmen</button>
            <?php if ($signature !== ''): ?>
              <button class="btn btn-danger" type="submit" name="action" value="delete_signature" formnovalidate onclick="return confirm('Gespeicherte Unterschrift wirklich zurücksetzen?')"><i class="fa-solid fa-arrow-rotate-left me-1" aria-hidden="true"></i>Unterschrift zurücksetzen</button>
            <?php endif; ?>
          </div>
        </form><?php endif; ?>
      </div>
    </section>
  </div>
  <div class="col-12 col-xl-5">
    <section class="card shadow-sm">
      <div class="card-header fw-semibold"><i class="fa-solid fa-id-card me-2" aria-hidden="true"></i>Kontodaten</div>
      <div class="card-body">
        <dl class="row mb-0">
          <dt class="col-sm-4">Name</dt><dd class="col-sm-8"><?= htmlspecialchars((string) ($user->name ?? '')) ?></dd>
          <dt class="col-sm-4">E-Mail</dt><dd class="col-sm-8 text-break"><?= htmlspecialchars((string) ($user->email ?? '')) ?></dd>
          <dt class="col-sm-4">Rolle</dt><dd class="col-sm-8"><span class="badge text-bg-secondary"><?= htmlspecialchars(role_label((string) ($user->role ?? 'user'))) ?></span></dd>
        </dl>
      </div>
    </section>
    <section class="card shadow-sm mt-4">
      <div class="card-header fw-semibold"><i class="fa-solid fa-user-shield me-2" aria-hidden="true"></i>Prüfberechtigungen</div>
      <div class="list-group list-group-flush">
        <?php foreach ($inspectionTypes as $inspectionType): $code = (string) $inspectionType['code']; $permission = $inspectionPermissions[$code] ?? ['allowed' => false, 'message' => 'Prüfberechtigung wird geprüft.']; ?>
          <div class="list-group-item"><div class="d-flex justify-content-between gap-2"><strong><i class="fa-solid <?= htmlspecialchars((string) ($inspectionType['icon'] ?? 'fa-clipboard-check'), ENT_QUOTES) ?> me-1" aria-hidden="true"></i><?= htmlspecialchars((string) $inspectionType['name']) ?></strong><span class="badge <?= !empty($permission['allowed']) ? (!empty($permission['grace']) ? 'text-bg-warning text-dark' : 'text-bg-success') : 'text-bg-danger' ?>"><?= !empty($permission['allowed']) ? (!empty($permission['grace']) ? 'Kulanzfrist' : 'erlaubt') : 'noch nicht erlaubt' ?></span></div><?php if (empty($permission['allowed']) || !empty($permission['warnings'])): ?><div class="small <?= !empty($permission['warnings']) ? 'text-warning' : 'text-body-secondary' ?> mt-1"><?= htmlspecialchars((string) ($permission['message'] ?: implode(' ', (array) ($permission['warnings'] ?? [])))) ?></div><?php endif; ?></div>
        <?php endforeach; ?>
      </div>
    </section>
  </div>
  <?php if (false): ?><div class="col-12 col-xl-7">
    <section class="card shadow-sm">
      <div class="card-header fw-semibold"><i class="fa-solid fa-graduation-cap me-2" aria-hidden="true"></i>Unterweisungen</div>
      <div class="card-body">
        <p class="text-body-secondary">Dokumentiere hier die Erstunterweisung und alle späteren Folgeunterweisungen. Für eine Prüfberechtigung ist zusätzlich ein PDF-Nachweis erforderlich, der der jeweiligen Prüfart zugeordnet wird.</p>
        <?php if ($canEdit): ?><form id="profile-instruction-panel" data-action-nav="Unterweisungen" data-action-icon="fa-graduation-cap" method="post" action="<?= htmlspecialchars($profileUrl, ENT_QUOTES) ?>" class="vstack gap-3">
          <input type="hidden" name="action" value="save_instruction">
          <div>
            <label class="form-label" for="instruction-initial-date"><i class="fa-solid fa-calendar-check me-1" aria-hidden="true"></i>&nbsp;Erstunterweisung am</label>
            <input class="form-control" id="instruction-initial-date" name="instruction_initial_date" type="date" value="<?= htmlspecialchars((string) ($user->instruction_initial_date ?? ''), ENT_QUOTES) ?>">
          </div>
          <div>
            <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
              <label class="form-label mb-0"><i class="fa-solid fa-calendar-days me-1" aria-hidden="true"></i>&nbsp;Folgeunterweisungen</label>
              <button class="btn btn-sm btn-outline-primary" type="button" id="add-followup"><i class="fa-solid fa-plus me-1" aria-hidden="true"></i>Weitere hinzufügen</button>
            </div>
            <div id="followup-list" class="vstack gap-2">
              <?php $followupRows = $followups !== [] ? $followups : [['date' => '', 'topic' => '']]; ?>
              <?php foreach ($followupRows as $followup): ?>
                <div class="row g-2 align-items-end followup-row">
                  <div class="col-12 col-md-4"><label class="form-label small">Datum</label><input class="form-control" name="followup_date[]" type="date" value="<?= htmlspecialchars((string) ($followup['date'] ?? ''), ENT_QUOTES) ?>"></div>
                  <div class="col"><label class="form-label small">Thema / Hinweis</label><input class="form-control" name="followup_topic[]" type="text" maxlength="240" value="<?= htmlspecialchars((string) ($followup['topic'] ?? ''), ENT_QUOTES) ?>" placeholder="z. B. jährliche Wiederholungsunterweisung"></div>
                  <div class="col-auto"><button class="btn btn-outline-danger remove-followup" type="button" title="Eintrag entfernen"><i class="fa-solid fa-trash" aria-hidden="true"></i><span class="visually-hidden">Eintrag entfernen</span></button></div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
          <div>
            <label class="form-label" for="instruction-notes"><i class="fa-solid fa-note-sticky me-1" aria-hidden="true"></i>&nbsp;Notizen zu den Unterweisungen</label>
            <textarea class="form-control" id="instruction-notes" name="instruction_notes" rows="3" maxlength="1000" placeholder="Interne Hinweise, Unterweiser/in oder Nachweisablage"><?= htmlspecialchars((string) ($user->instruction_notes ?? '')) ?></textarea>
          </div>
          <div><button class="btn btn-primary" type="submit"><i class="fa-solid fa-floppy-disk me-1" aria-hidden="true"></i>Unterweisungen speichern</button></div>
        </form><?php else: ?>
          <dl class="row mb-0"><dt class="col-sm-4">Erstunterweisung</dt><dd class="col-sm-8"><?= htmlspecialchars((string) ($user->instruction_initial_date ?? '—')) ?></dd><dt class="col-sm-4">Folgeunterweisungen</dt><dd class="col-sm-8"><?= count($followups) ?></dd><dt class="col-sm-4">Notizen</dt><dd class="col-sm-8 text-break"><?= nl2br(htmlspecialchars((string) ($user->instruction_notes ?? '—'))) ?></dd></dl>
        <?php endif; ?>
      </div>
    </section>
  </div>
  </div><?php endif; ?>
  <div class="col-12">
    <section class="card shadow-sm" id="qualification-card" data-action-nav="Befähigungen &amp; Nachweise" data-action-icon="fa-certificate">
      <div class="card-header fw-semibold d-flex justify-content-between align-items-center gap-2" id="qualifications"><span><i class="fa-solid fa-certificate me-2" aria-hidden="true"></i>Befähigungen &amp; Nachweise</span><span class="small text-body-secondary">Nachweise und Folgeunterweisungen</span></div>
      <div class="card-body">
        <p class="small text-body-secondary">Lege zuerst eine Befähigung an. Der erste PDF-Nachweis gehört zu dieser Befähigung; spätere Unterweisungen werden darunter als eigene Liste ergänzt.</p>
        <?php if ($certificates === []): ?><p class="text-body-secondary mb-3">Noch keine Befähigung angelegt.</p><?php else: ?>
          <div class="list-group mb-3">
            <?php foreach ($certificates as $certificate): ?><div class="list-group-item"><div class="d-flex justify-content-between align-items-start gap-2"><div><a href="<?= htmlspecialchars($adminView ? url_for('admin/nutzer/' . (int) $user->id . '/profil/nachweis/' . rawurlencode((string) $certificate['id'])) : url_for('profil/nachweis/' . rawurlencode((string) $certificate['id'])), ENT_QUOTES) ?>" target="_blank" rel="noopener"><i class="fa-solid fa-file-pdf me-1 text-danger" aria-hidden="true"></i><?= htmlspecialchars((string) ($certificate['title'] ?: $certificate['name'])) ?></a><div class="small text-body-secondary"><?= htmlspecialchars((string) ($certificate['kind'] ?? '')) ?> · <?= htmlspecialchars((string) ($certificate['date'] ?? '')) ?></div><div class="mt-1"><?php foreach ((array) ($certificate['inspection_type_codes'] ?? []) as $typeCode): foreach ($inspectionTypes as $inspectionType): if ((string) $inspectionType['code'] !== (string) $typeCode) continue; ?><span class="badge text-bg-secondary me-1"><i class="fa-solid <?= htmlspecialchars((string) $inspectionType['icon'], ENT_QUOTES) ?> me-1" aria-hidden="true"></i><?= htmlspecialchars((string) $inspectionType['name']) ?></span><?php endforeach; endforeach; ?></div><div class="mt-2"><?php if (!empty($certificate['qualification_expired'])): ?><span class="badge text-bg-danger">Nachweis abgelaufen</span><?php elseif (($certificate['qualification_status'] ?? 'pending') === 'confirmed'): ?><span class="badge text-bg-success"><i class="fa-solid fa-circle-check me-1" aria-hidden="true"></i>Von Admin geprüft</span><?php else: ?><span class="badge text-bg-warning text-dark"><i class="fa-solid fa-hourglass-half me-1" aria-hidden="true"></i>Prüfung durch Admin offen</span><?php endif; ?></div></div><div class="d-flex gap-1"><?php if ($canEdit && current_user_has_role('admin') && ($certificate['qualification_status'] ?? 'pending') !== 'confirmed'): ?><form method="post" action="<?= htmlspecialchars($profileUrl, ENT_QUOTES) ?>"><input type="hidden" name="action" value="confirm_certificate"><input type="hidden" name="certificate_id" value="<?= htmlspecialchars((string) $certificate['id'], ENT_QUOTES) ?>"><button class="btn btn-sm btn-success" title="Unterweisung prüfen"><i class="fa-solid fa-check me-1" aria-hidden="true"></i>Prüfen</button></form><?php endif; ?><?php if ($canEdit): ?><form method="post" action="<?= htmlspecialchars($profileUrl, ENT_QUOTES) ?>"><input type="hidden" name="action" value="delete_certificate"><input type="hidden" name="certificate_id" value="<?= htmlspecialchars((string) $certificate['id'], ENT_QUOTES) ?>"><button class="btn btn-sm btn-danger" title="Nachweis entfernen" onclick="return confirm('Nachweis wirklich entfernen?')"><i class="fa-solid fa-trash" aria-hidden="true"></i><span class="visually-hidden">Nachweis entfernen</span></button></form><?php endif; ?></div></div><?php if (!empty($certificate['followups'])): ?><div class="border-top mt-2 pt-2"><div class="small fw-semibold mb-1"><i class="fa-solid fa-calendar-check me-1" aria-hidden="true"></i>Folgeunterweisungen</div><?php foreach ($certificate['followups'] as $followupIndex => $followup): ?><div class="small d-flex gap-2"><a href="<?= htmlspecialchars(($adminView ? url_for('admin/nutzer/' . (int) $user->id . '/profil/nachweis/' . rawurlencode((string) $certificate['id'])) : url_for('profil/nachweis/' . rawurlencode((string) $certificate['id']))) . '?followup=' . (int) $followupIndex, ENT_QUOTES) ?>" target="_blank"><i class="fa-solid fa-file-pdf text-danger me-1" aria-hidden="true"></i><?= htmlspecialchars((string) ($followup['title'] ?: $followup['name'] ?: 'Folgeunterweisung')) ?></a> · <?= htmlspecialchars((string) ($followup['date'] ?? '')) ?></div><?php endforeach; ?></div><?php endif; ?><?php if ($canEdit): ?><form method="post" action="<?= htmlspecialchars($profileUrl, ENT_QUOTES) ?>" enctype="multipart/form-data" class="row g-2 mt-2"><input type="hidden" name="action" value="upload_followup"><input type="hidden" name="certificate_id" value="<?= htmlspecialchars((string) $certificate['id'], ENT_QUOTES) ?>"><div class="col-12 col-md-3"><label class="form-label small">Folgeunterweisung am</label><input class="form-control form-control-sm" type="date" name="followup_date" required></div><div class="col-12 col-md-3"><label class="form-label small">PDF</label><input class="form-control form-control-sm" type="file" name="followup_certificate" accept="application/pdf" required></div><div class="col-12 col-md-4"><label class="form-label small">Notiz</label><input class="form-control form-control-sm" name="followup_title" maxlength="240" placeholder="Thema / Hinweis"></div><div class="col-12 col-md-2 d-flex align-items-end"><button class="btn btn-sm btn-secondary w-100" type="submit"><i class="fa-solid fa-plus me-1" aria-hidden="true"></i>Hinzufügen</button></div></form><?php endif; ?></div><?php endforeach; ?>
          </div>
        <?php endif; ?>
        <?php if ($canEdit): ?><form method="post" action="<?= htmlspecialchars($profileUrl, ENT_QUOTES) ?>" enctype="multipart/form-data" class="vstack gap-2"><input type="hidden" name="action" value="upload_certificate"><label class="form-label mb-0" for="instruction-certificate"><i class="fa-solid fa-upload me-1" aria-hidden="true"></i>Erster PDF-Nachweis</label><input class="form-control" id="instruction-certificate" name="instruction_certificate" type="file" accept="application/pdf" required><div class="row g-2"><div class="col-12 col-md-4"><label class="form-label small" for="certificate-date">Ausgestellt am</label><input class="form-control" id="certificate-date" name="certificate_date" type="date" required></div><div class="col-12 col-md-8"><label class="form-label small" for="certificate-title">Name der Befähigung</label><input class="form-control" id="certificate-title" name="certificate_title" maxlength="240" placeholder="z. B. Elektroprüfer/in oder Leiterprüfbeauftragte/r" required></div></div><input type="hidden" name="certificate_kind" value="Befähigungsnachweis"><fieldset><legend class="form-label small mb-1"><i class="fa-solid fa-clipboard-check me-1" aria-hidden="true"></i>Konkrete Nachweisart und Prüfberechtigung</legend><div class="row g-2"><?php foreach ($qualificationRequirements as $requirement): ?><div class="col-12 col-md-6"><label class="form-check border rounded px-2 py-2 h-100"><input class="form-check-input" type="checkbox" name="certificate_requirement_codes[]" value="<?= htmlspecialchars((string) $requirement['code'], ENT_QUOTES) ?>"><span class="form-check-label"><strong><?= htmlspecialchars((string) ($requirement['name'] ?? $requirement['code'])) ?></strong><span class="d-block small text-body-secondary"><?= htmlspecialchars((string) ($requirement['inspection_type_name'] ?? $requirement['inspection_type_code'])) ?></span></span></label></div><?php endforeach; ?></div><div class="form-text">Nur die ausgewählten Nachweisarten werden als Befähigung angelegt. Ein PDF kann mehreren Prüfarten zugeordnet werden.</div></fieldset><div class="form-text">PDF, maximal 10 MB. Nach dem Upload prüft ein Admin den Nachweis.</div><button class="btn btn-primary" type="submit"><i class="fa-solid fa-floppy-disk me-1" aria-hidden="true"></i>Befähigung anlegen</button></form><?php endif; ?>
      </div>
    </section>
  </div>
</div>

<div class="card mt-4" id="derived-inspection-permissions" data-action-nav="Abgeleitete Prüfberechtigungen" data-action-icon="fa-user-shield">
  <div class="card-header fw-semibold"><i class="fa-solid fa-certificate me-2" aria-hidden="true"></i>Abgeleitete Prüfberechtigungen</div>
  <div class="card-body">
    <p class="small text-body-secondary">Diese Einträge werden aus den zugeordneten Unterweisungsnachweisen abgeleitet. Alte Einzel-Nachweise bleiben zur Kompatibilität sichtbar.</p>
    <?php if ($qualifications === []): ?><p class="text-body-secondary">Noch keine Befähigungsnachweise hinterlegt.</p><?php else: ?><div class="table-responsive"><table class="table table-sm align-middle"><thead><tr><th>Nachweis</th><th>Ausgestellt</th><th>Gültig bis</th><th>Status</th></tr></thead><tbody><?php foreach ($qualifications as $qualification): $valid = empty($qualification['expires_at']) || $qualification['expires_at'] >= date('Y-m-d'); $proofUrl = $adminView ? url_for('admin/nutzer/' . (int) $user->id . '/profil/befaehigung/' . (int) $qualification['id']) : url_for('profil/befaehigung/' . (int) $qualification['id']); ?><tr><td><strong><?= htmlspecialchars((string) ($qualification['requirement_name'] ?: $qualification['requirement_code'])) ?></strong><div class="small text-body-secondary"><?= htmlspecialchars((string) ($qualification['inspection_type_name'] ?: '')) ?></div><?php if (!empty($qualification['proof_path'])): ?><a class="small" href="<?= htmlspecialchars($proofUrl, ENT_QUOTES) ?>" target="_blank" rel="noopener"><i class="fa-solid fa-file-pdf me-1 text-danger" aria-hidden="true"></i>PDF-Nachweis</a><?php endif; ?></td><td><?= htmlspecialchars((string) ($qualification['issued_at'] ?: '—')) ?></td><td><?= htmlspecialchars((string) ($qualification['expires_at'] ?: 'unbegrenzt')) ?></td><td><?php if (!$valid): ?><span class="badge text-bg-danger">Abgelaufen</span><?php elseif (!empty($qualification['confirmed_at'])): ?><span class="badge text-bg-success">Bestätigt</span><?php else: ?><span class="badge text-bg-warning text-dark">Bestätigung offen</span><?php endif; ?><?php if ($canConfirmQualifications && empty($qualification['confirmed_at'])): ?><form method="post" action="<?= htmlspecialchars($profileUrl, ENT_QUOTES) ?>" class="d-inline ms-2"><input type="hidden" name="action" value="confirm_qualification"><input type="hidden" name="qualification_id" value="<?= (int) $qualification['id'] ?>"><button class="btn btn-sm btn-outline-success">Bestätigen</button></form><?php endif; ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
    <p class="small text-body-secondary border-top pt-3 mb-0"><i class="fa-solid fa-link me-1" aria-hidden="true"></i>Prüfberechtigungen werden ausschließlich aus den oben hochgeladenen und zugeordneten Nachweisen abgeleitet. Eine manuelle Doppelpflege ist nicht erforderlich.</p>
  </div>
</div>
<?php $followupCodeMap = []; $certificateStateMap = []; foreach ($certificates as $certificate): $codes = []; foreach ((array) ($certificate['qualification_ids'] ?? []) as $qualificationId): foreach ($qualifications as $qualification): if ((int) ($qualification['id'] ?? 0) === (int) $qualificationId && (string) ($qualification['requirement_code'] ?? '') !== '') $codes[] = (string) $qualification['requirement_code']; endforeach; endforeach; $certificateId = (string) ($certificate['id'] ?? ''); $followupCodeMap[$certificateId] = array_values(array_unique($codes)); $certificateStateMap[$certificateId] = ['expired' => !empty($certificate['qualification_expired']), 'grace' => !empty($certificate['qualification_grace']), 'confirmed' => ($certificate['qualification_status'] ?? '') === 'confirmed', 'expires_at' => (string) ($certificate['qualification_expires_at'] ?? ''), 'confirmed_by' => (string) ($certificate['confirmed_by_name'] ?? ''), 'confirmed_at' => (string) ($certificate['confirmed_at'] ?? '')]; endforeach; ?>
<script type="application/json" id="qualification-followup-code-map"><?= htmlspecialchars(json_encode($followupCodeMap, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_NOQUOTES) ?></script>
<script type="application/json" id="qualification-state-map"><?= htmlspecialchars(json_encode($certificateStateMap, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_NOQUOTES) ?></script>
<style>.signature-pad{touch-action:none;background:linear-gradient(135deg,#fff 0%,#fbfbff 100%);box-shadow:inset 0 0 1.5rem rgba(53,71,184,.06)}.signature-pad canvas{display:block;width:100%;height:220px;cursor:crosshair;touch-action:none}.qualification-followup-form,.list-group-item form:has([name="followup_date"]){display:grid;grid-template-columns:minmax(10rem,1fr) minmax(11rem,1.1fr) minmax(12rem,1.4fr) minmax(8rem,.8fr);gap:.75rem;align-items:end}.qualification-followup-form .form-label,.list-group-item form:has([name="followup_date"]) .form-label{display:block;white-space:nowrap}.qualification-followup-form .form-control,.list-group-item form:has([name="followup_date"]) .form-control{min-width:0}.qualification-followup-form .qualification-followup-action,.list-group-item form:has([name="followup_date"]) [class*="col-"]:last-child{display:flex;align-items:end}.qualification-followup-form .qualification-followup-action .btn,.list-group-item form:has([name="followup_date"]) [class*="col-"]:last-child .btn{width:100%}.list-group-item form:has([name="followup_date"])>[class*="col-"]{width:auto;padding-inline:0}@media(max-width:900px){.qualification-followup-form,.list-group-item form:has([name="followup_date"]){grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:575.98px){.qualification-followup-form,.list-group-item form:has([name="followup_date"]){grid-template-columns:1fr}.qualification-followup-form .form-label,.list-group-item form:has([name="followup_date"]) .form-label{white-space:normal}}</style>
<style>.list-group-item form:has([name="followup_date"])>div[class*="col-"]:first-of-type .form-label{font-size:0}.list-group-item form:has([name="followup_date"])>div[class*="col-"]:first-of-type .form-label:after{content:'Datum';font-size:.875rem}</style>
<style>
#qualification-card .qualification-item{transition:background-color .15s ease}
#qualification-card .qualification-item:hover{background-color:var(--bs-tertiary-bg)}
#qualification-card .qualification-item .qualification-extra{display:none}
#qualification-card .qualification-item.is-expanded .qualification-extra{display:block}
#qualification-card .qualification-item .qualification-followup-form-collapsed{display:none!important}
#qualification-card .qualification-item .qualification-followup-form-visible{display:grid!important}
#qualification-card .qualification-item .qualification-toggle{white-space:nowrap;align-self:flex-start;padding:.25rem .45rem}
#qualification-card .qualification-item .d-flex.gap-1{align-items:flex-start}
#qualification-card .qualification-item fieldset[data-followup-scope]{grid-column:1 / -1;margin:0;padding:.5rem .75rem;border:1px solid var(--bs-border-color);border-radius:.375rem}
#qualification-card .qualification-create summary{cursor:pointer;list-style:none}
#qualification-card .qualification-create summary::-webkit-details-marker{display:none}
</style>
<script>
(() => {
  const card = document.getElementById('qualification-card');
  if (!card) return;
  let followupCodeMap = {};
  try { followupCodeMap = JSON.parse(document.getElementById('qualification-followup-code-map')?.textContent || '{}'); } catch (_) {}
  let certificateStateMap = {};
  try { certificateStateMap = JSON.parse(document.getElementById('qualification-state-map')?.textContent || '{}'); } catch (_) {}
  const labels = {electrical_basic: 'Elektroprüfung', ladder_basic: 'Leitern & Tritte'};
  card.querySelectorAll('form input[name="certificate_id"]').forEach((formInput) => {
    const form = formInput.closest('form');
    const codes = followupCodeMap[formInput.value] || [];
    if (!form || !form.querySelector('input[name="action"][value="upload_followup"]') || !codes.length || form.querySelector('[data-followup-scope]')) return;
    const scope = document.createElement('fieldset');
    scope.dataset.followupScope = '1'; scope.className = 'col-12';
    scope.innerHTML = '<legend class="form-label small mb-1">Gilt diese Folgeunterweisung für</legend>' + codes.map((code) => '<label class="form-check form-check-inline small"><input class="form-check-input" type="checkbox" name="followup_requirement_codes[]" value="' + code.replace(/"/g, '&quot;') + '" checked><span class="form-check-label">' + (labels[code] || code) + '</span></label>').join('');
    form.insertBefore(scope, form.querySelector('button[type="submit"]')?.closest('[class*="col-"]') || null);
  });
  card.querySelectorAll('.list-group > .list-group-item').forEach((item) => {
    item.classList.add('qualification-item');
    const header = item.querySelector(':scope > .d-flex');
    const info = header?.firstElementChild;
    if (!header || !info) return;
    const certificateId = item.querySelector('input[name="certificate_id"]')?.value || '';
    const state = certificateStateMap[certificateId] || {};
    const typeBadges = info.querySelectorAll('.mt-1 .badge');
    const stateClass = state.expired ? 'text-bg-danger' : (state.grace ? 'text-bg-warning text-dark' : 'text-bg-success');
    const displayDate = value => value ? value.split('-').reverse().join('.') : '—';
    const stateTitle = state.expired ? 'Befähigung nicht mehr gültig' : (state.grace ? 'Gültig in der Kulanzfrist' + (state.expires_at ? '; regulär abgelaufen am ' + displayDate(state.expires_at) : '') : 'Befähigung gültig');
    const stateMessage = state.expired
      ? 'Die Befähigung ist nicht mehr gültig' + (state.expires_at ? ' (Ablauf: ' + displayDate(state.expires_at) + ')' : '') + '. Bitte eine Folgeunterweisung hinterlegen.'
      : (state.grace ? 'Die reguläre Gültigkeit endete am ' + displayDate(state.expires_at) + '. Die Kulanzfrist läuft noch; bitte die Folgeunterweisung zeitnah aktualisieren.' : 'Die Befähigung ist aktuell gültig' + (state.expires_at ? ' bis ' + displayDate(state.expires_at) : '') + '.');
    typeBadges.forEach((badge) => {
      badge.className = 'badge me-1 ' + stateClass; badge.style.cursor = 'pointer'; badge.tabIndex = 0; badge.setAttribute('role', 'button');
      badge.dataset.bsToggle = 'popover'; badge.dataset.bsTrigger = 'click focus'; badge.dataset.bsPlacement = 'bottom'; badge.dataset.bsContent = stateMessage; badge.dataset.bsTitle = badge.textContent.trim();
      window.bootstrap?.Popover?.getOrCreateInstance(badge, {container: 'body'});
    });
    const confirmationBadge = info.querySelector('.mt-2 .badge');
    if (confirmationBadge && state.confirmed && !state.expired && !state.grace) {
      confirmationBadge.innerHTML = '<i class="fa-solid fa-circle-check me-1" aria-hidden="true"></i>Geprüft';
      confirmationBadge.title = 'Geprüft von ' + (state.confirmed_by || 'Administration') + (state.confirmed_at ? ' am ' + state.confirmed_at.slice(0, 10).split('-').reverse().join('.') : '');
    }
    const extras = [info.querySelector('.mt-2'), item.querySelector(':scope > .border-top'), item.querySelector(':scope > form')].filter(Boolean);
    extras.forEach((element) => element.classList.add('qualification-extra'));
    const followupForm = item.querySelector(':scope > form');
    if (followupForm) {
      followupForm.classList.add('qualification-followup-form-collapsed');
      const followupButton = document.createElement('button');
      followupButton.type = 'button'; followupButton.className = 'btn btn-sm btn-primary qualification-extra mt-2';
      followupButton.innerHTML = '<i class="fa-solid fa-plus me-1" aria-hidden="true"></i>Folgeunterweisung hinzufügen';
      followupButton.addEventListener('click', () => {
        const visible = followupForm.classList.toggle('qualification-followup-form-visible');
        followupForm.classList.toggle('qualification-followup-form-collapsed', !visible);
        followupButton.innerHTML = visible ? '<i class="fa-solid fa-chevron-up me-1" aria-hidden="true"></i>Formular ausblenden' : '<i class="fa-solid fa-plus me-1" aria-hidden="true"></i>Folgeunterweisung hinzufügen';
      });
      item.insertBefore(followupButton, followupForm);
    }
    const actions = header.querySelector(':scope > .d-flex.gap-1') || header;
    const toggle = document.createElement('button');
    toggle.type = 'button'; toggle.className = 'btn btn-sm btn-secondary qualification-toggle';
    toggle.innerHTML = '<i class="fa-solid fa-chevron-down me-1" aria-hidden="true"></i>Details';
    toggle.addEventListener('click', () => {
      const expanded = item.classList.toggle('is-expanded');
      toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
      toggle.innerHTML = expanded ? '<i class="fa-solid fa-chevron-up me-1" aria-hidden="true"></i>Weniger' : '<i class="fa-solid fa-chevron-down me-1" aria-hidden="true"></i>Details';
    });
    actions.prepend(toggle);
  });
  const createForm = card.querySelector('form input[name="action"][value="upload_certificate"]')?.closest('form');
  if (createForm && !createForm.closest('details')) {
    const details = document.createElement('details');
    details.className = 'qualification-create mt-3';
    details.innerHTML = '<summary class="btn btn-primary"><i class="fa-solid fa-plus me-1" aria-hidden="true"></i>Neue Befähigung anlegen</summary>';
    createForm.classList.add('border', 'rounded-3', 'p-3', 'mt-3');
    details.appendChild(createForm);
    card.querySelector('.card-body').appendChild(details);
  }
})();
</script>
<script>
(() => {
  const canvas = document.getElementById('signature-pad');
  const field = document.getElementById('signature-drawing');
  const form = canvas?.closest('form');
  const clear = document.getElementById('signature-clear');
  if (!canvas || !field || !form) return;
  const context = canvas.getContext('2d');
  let drawing = false; let hasInk = false; let previous = null; let midpoint = null;
  const scale = () => {
    const ratio = window.devicePixelRatio || 1; const width = Math.max(320, Math.round(canvas.clientWidth * ratio)); const height = Math.round(220 * ratio);
    if (canvas.width === width && canvas.height === height) return;
    const copy = document.createElement('canvas'); copy.width = canvas.width; copy.height = canvas.height; copy.getContext('2d').drawImage(canvas, 0, 0);
    canvas.width = width; canvas.height = height; context.lineCap = 'round'; context.lineJoin = 'round'; context.lineWidth = 3.2 * ratio; context.strokeStyle = '#3547b8'; context.globalAlpha = .94; context.drawImage(copy, 0, 0, width, height);
  };
  const point = event => { const rect = canvas.getBoundingClientRect(); const ratio = canvas.width / rect.width; return {x:(event.clientX - rect.left) * ratio, y:(event.clientY - rect.top) * ratio}; };
  const start = event => { event.preventDefault(); scale(); drawing = true; previous = point(event); midpoint = previous; hasInk = true; canvas.setPointerCapture?.(event.pointerId); };
  const draw = event => { if (!drawing) return; event.preventDefault(); const next = point(event); const nextMidpoint = {x:(previous.x + next.x) / 2, y:(previous.y + next.y) / 2}; context.beginPath(); context.moveTo(midpoint.x, midpoint.y); context.quadraticCurveTo(previous.x, previous.y, nextMidpoint.x, nextMidpoint.y); context.stroke(); previous = next; midpoint = nextMidpoint; };
  const end = event => { if (!drawing) return; drawing = false; previous = null; midpoint = null; canvas.releasePointerCapture?.(event.pointerId); };
  canvas.addEventListener('pointerdown', start); canvas.addEventListener('pointermove', draw); canvas.addEventListener('pointerup', end); canvas.addEventListener('pointercancel', end);
  clear?.addEventListener('click', () => { context.clearRect(0, 0, canvas.width, canvas.height); hasInk = false; field.value = ''; });
  form.addEventListener('submit', () => { field.value = hasInk ? canvas.toDataURL('image/png') : ''; });
  window.addEventListener('resize', scale); scale();
})();
</script>
<script>
(() => {
  const list = document.getElementById('followup-list');
  const add = document.getElementById('add-followup');
  if (!list || !add) return;
  const bindRemove = (button) => button.addEventListener('click', () => {
    const rows = list.querySelectorAll('.followup-row');
    if (rows.length > 1) button.closest('.followup-row')?.remove();
    else button.closest('.followup-row')?.querySelectorAll('input').forEach((input) => { input.value = ''; });
  });
  list.querySelectorAll('.remove-followup').forEach(bindRemove);
  add.addEventListener('click', () => {
    const row = document.createElement('div');
    row.className = 'row g-2 align-items-end followup-row';
    row.innerHTML = '<div class="col-12 col-md-4"><label class="form-label small">Datum</label><input class="form-control" name="followup_date[]" type="date"></div><div class="col"><label class="form-label small">Thema / Hinweis</label><input class="form-control" name="followup_topic[]" type="text" maxlength="240" placeholder="z. B. jährliche Wiederholungsunterweisung"></div><div class="col-auto"><button class="btn btn-outline-danger remove-followup" type="button" title="Eintrag entfernen"><i class="fa-solid fa-trash" aria-hidden="true"></i><span class="visually-hidden">Eintrag entfernen</span></button></div>';
    list.appendChild(row);
    bindRemove(row.querySelector('.remove-followup'));
  });
})();
</script>
