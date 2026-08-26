<?php
/** @var array<int, array<string, mixed>> $companies */
/** @var array{total:int, withLogo:int} $stats */
/** @var array<string, mixed>|null $defaultCompany */
?>

<div class="row g-4 mb-4 align-items-stretch">
  <div class="col-12 col-xl-8">
    <div class="card shadow-sm border-0 h-100">
      <div class="card-body">
        <div class="d-flex flex-wrap justify-content-between gap-3 align-items-start">
          <div>
            <h2 class="h5 mb-2">Mandanten & Branding</h2>
            <p class="text-body-secondary mb-0">
              Hinterlege Markenprofile samt Logos, um die Prüfauftragsverwaltung optisch an eure Organisation anzupassen.
            </p>
          </div>
          <a class="btn btn-primary" href="<?= htmlspecialchars(url_for('mandanten/neu'), ENT_QUOTES) ?>">
            <i class="fa-solid fa-plus me-2" aria-hidden="true"></i>Neuer Mandant
          </a>
        </div>

        <div class="d-flex flex-wrap gap-4 mt-4">
          <div class="stat-tile">
            <div class="stat-icon bg-primary-subtle text-primary">
              <i class="fa-solid fa-building" aria-hidden="true"></i>
            </div>
            <div>
              <div class="stat-value">
                <?= htmlspecialchars((string) $stats['total']) ?>
              </div>
              <div class="stat-label">Hinterlegte Mandanten</div>
            </div>
          </div>
          <div class="stat-tile">
            <div class="stat-icon bg-info-subtle text-info">
              <i class="fa-solid fa-image" aria-hidden="true"></i>
            </div>
            <div>
              <div class="stat-value">
                <?= htmlspecialchars((string) $stats['withLogo']) ?>
              </div>
              <div class="stat-label">Mit Header-Logo</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-12 col-xl-4">
    <div class="card shadow-sm border-0 h-100">
      <div class="card-body">
        <h2 class="h6 text-uppercase text-secondary fw-semibold mb-3">Fallback-Mandant</h2>
        <?php if ($defaultCompany !== null): ?>
          <div class="d-flex flex-column gap-3">
            <div class="d-flex align-items-center gap-3">
              <div class="default-company-icon bg-primary text-white">
                <i class="fa-solid fa-star" aria-hidden="true"></i>
              </div>
              <div>
                <div class="fw-semibold"><?= htmlspecialchars($defaultCompany['name']) ?></div>
                <div class="small text-body-secondary">Für interne oder nicht zugeordnete Hosts</div>
              </div>
            </div>
            <?php if (!empty($defaultCompany['header_logo_url'])): ?>
              <div class="default-company-logo border rounded p-3 bg-body-tertiary">
                <span class="text-body-secondary small d-block mb-2">Header-Logo</span>
                <img src="<?= htmlspecialchars($defaultCompany['header_logo_url'], ENT_QUOTES) ?>"
                     alt="<?= htmlspecialchars($defaultCompany['header_logo_alt'] ?? $defaultCompany['name']) ?>"
                     class="img-fluid"
                     style="max-height: 56px; width: auto;">
              </div>
            <?php else: ?>
              <div class="alert alert-warning mb-0" role="status">
                Für den Standardmandanten ist noch kein Logo hinterlegt.
              </div>
            <?php endif; ?>
          </div>
        <?php else: ?>
          <p class="text-body-secondary mb-0">
            CENEOS ist als Fallback vorgesehen, aber noch nicht als Mandant hinterlegt.
          </p>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<div class="card shadow-sm border-0">
  <div class="card-header bg-body-tertiary border-0">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
      <div>
        <h2 class="h5 mb-1">Übersicht aller Mandanten</h2>
        <p class="text-body-secondary mb-0">Öffentliche Hosts wählen das jeweilige Branding automatisch. CENEOS ist der Fallback.</p>
      </div>
      <a class="btn btn-outline-primary" href="<?= htmlspecialchars(url_for('mandanten/neu'), ENT_QUOTES) ?>">
        <i class="fa-solid fa-plus me-2" aria-hidden="true"></i>Neuer Mandant
      </a>
    </div>
  </div>
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead class="table-light">
        <tr>
          <th scope="col">Name &amp; Kunde</th>
          <th scope="col" class="text-nowrap">Kurznamen</th>
          <th scope="col" class="text-nowrap">Öffentliche Adresse</th>
          <th scope="col" class="text-nowrap">Header-Logo</th>
          <th scope="col" class="text-nowrap">Status</th>
          <th scope="col" class="text-end text-nowrap">Aktionen</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($companies === []): ?>
          <tr>
            <td colspan="6" class="text-center py-5 text-body-secondary">
              <i class="fa-regular fa-building mb-3 d-block fs-2" aria-hidden="true"></i>
              Es sind noch keine Mandanten hinterlegt. Lege über den &bdquo;Neuer Mandant&ldquo;-Button dein erstes Branding an.
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($companies as $company): ?>
            <tr>
              <td>
                <div class="fw-semibold d-flex align-items-center gap-2">
                  <?= htmlspecialchars($company['name']) ?>
                  <?php if (($company['slug'] ?? '') === 'ceneos'): ?>
                    <span class="badge text-bg-primary">Fallback</span>
                  <?php endif; ?>
                </div>
              </td>
              <td>
                <span class="badge bg-body-secondary text-body-emphasis fw-semibold">
                  <?= htmlspecialchars($company['slug']) ?>
                </span>
              </td>
              <td>
                <?php $publicUrl = (string) ($company['public_url'] ?? ''); ?>
                <?php if ($publicUrl !== ''): ?>
                  <a href="<?= htmlspecialchars($publicUrl, ENT_QUOTES) ?>" class="link-offset-2 small" target="_blank" rel="noopener">
                    <?= htmlspecialchars(preg_replace('#^https?://#', '', $publicUrl) ?: $publicUrl) ?>
                    <i class="fa-solid fa-arrow-up-right-from-square ms-1" aria-hidden="true"></i>
                  </a>
                <?php else: ?>
                  <span class="text-body-secondary small">Kein öffentlicher Host</span>
                <?php endif; ?>
              </td>
              <td>
                <?php if (!empty($company['header_logo_path'])): ?>
                  <img src="<?= htmlspecialchars($company['header_logo_url'], ENT_QUOTES) ?>"
                       alt="<?= htmlspecialchars($company['header_logo_alt'] ?? '') ?>"
                       class="img-fluid"
                       style="max-height: 40px; width: auto;">
                <?php else: ?>
                  <span class="text-body-secondary small">Kein Logo hinterlegt</span>
                <?php endif; ?>
              </td>
              <td>
                <?php if (($company['slug'] ?? '') === 'ceneos'): ?>
                  <span class="badge rounded-pill text-bg-primary">Fallback</span>
                <?php elseif (!empty($company['public_url'])): ?>
                  <span class="badge rounded-pill text-bg-success">Host aktiv</span>
                <?php else: ?>
                  <span class="badge rounded-pill text-bg-secondary">Ohne Host</span>
                <?php endif; ?>
              </td>
              <td class="text-end">
                <div class="btn-group" role="group">
                  <a class="btn btn-outline-secondary btn-sm"
                     href="<?= htmlspecialchars(url_for('mandanten/' . $company['id'] . '/bearbeiten'), ENT_QUOTES) ?>">
                    <i class="fa-solid fa-pen-to-square me-1" aria-hidden="true"></i>
                    Bearbeiten
                  </a>
                  <form class="d-inline" method="post"
                        action="<?= htmlspecialchars(url_for('mandanten/' . $company['id'] . '/loeschen'), ENT_QUOTES) ?>"
                        onsubmit="return confirm('Soll dieser Mandant wirklich gelöscht werden?');">
                    <button type="submit" class="btn btn-outline-danger btn-sm"<?= ($company['slug'] ?? '') === 'ceneos' ? ' disabled' : '' ?>>
                      <i class="fa-solid fa-trash-can me-1" aria-hidden="true"></i>
                      Löschen
                    </button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
