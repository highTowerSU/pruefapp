<?php
/** @var \RedBeanPHP\OODBBean $user */
/** @var string $signature */
?>
<header class="page-header mb-4">
  <h1 class="mb-1"><i class="fa-solid fa-user-pen me-2" aria-hidden="true"></i>Mein Profil</h1>
  <p class="mb-0 text-body-secondary">Persönliche Angaben für die Prüf-Dokumentation</p>
</header>

<div class="row g-4">
  <div class="col-12 col-xl-7">
    <section class="card shadow-sm">
      <div class="card-header fw-semibold"><i class="fa-solid fa-signature me-2" aria-hidden="true"></i>Unterschrift für Prüfberichte</div>
      <div class="card-body">
        <p class="text-body-secondary">Die Unterschrift wird in neu erzeugte Prüfberichte übernommen, wenn du als Prüfer/in eingetragen bist. Am besten eignet sich eine freigestellte PNG-Datei.</p>
        <?php if ($signature !== ''): ?>
          <div class="border rounded-2 bg-white p-3 mb-3 d-inline-block">
            <img src="<?= htmlspecialchars($signature, ENT_QUOTES) ?>" alt="Gespeicherte Unterschrift" style="max-width:20rem;max-height:8rem">
          </div>
        <?php else: ?>
          <div class="alert alert-secondary"><i class="fa-solid fa-pen-nib me-2" aria-hidden="true"></i>Noch keine Unterschrift hinterlegt.</div>
        <?php endif; ?>
        <form method="post" enctype="multipart/form-data" class="vstack gap-3">
          <div>
            <label for="report-signature" class="form-label"><i class="fa-solid fa-image me-1" aria-hidden="true"></i>&nbsp;Bilddatei</label>
            <input class="form-control" id="report-signature" name="report_signature" type="file" accept="image/png,image/jpeg" required>
            <div class="form-text">PNG oder JPEG, maximal 2 MB und 4000 × 2000 Pixel.</div>
          </div>
          <div class="d-flex gap-2 flex-wrap">
            <button class="btn btn-primary" type="submit" name="action" value="upload_signature"><i class="fa-solid fa-floppy-disk me-1" aria-hidden="true"></i>Unterschrift speichern</button>
            <?php if ($signature !== ''): ?>
              <button class="btn btn-danger" type="submit" name="action" value="delete_signature" formnovalidate onclick="return confirm('Gespeicherte Unterschrift wirklich entfernen?')"><i class="fa-solid fa-trash me-1" aria-hidden="true"></i>Entfernen</button>
            <?php endif; ?>
          </div>
        </form>
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
  </div>
</div>
