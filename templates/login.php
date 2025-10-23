<?php $branding = $branding ?? get_branding(); ?>
<div class="row justify-content-center py-5">
  <div class="col-lg-8 col-xl-6">
    <div class="card border-0 shadow-lg overflow-hidden">
      <div class="row g-0">
        <div class="col-md-5 d-none d-md-flex align-items-stretch bg-primary-subtle text-primary-emphasis">
          <div class="p-4 p-lg-5 w-100 d-flex flex-column justify-content-between">
            <div>
              <div class="text-uppercase small fw-semibold mb-2">Willkommen</div>
              <h1 class="h4 fw-semibold mb-3">
                <?= htmlspecialchars($branding['app_title'] ?? 'Kursverwaltung') ?>
              </h1>
              <p class="mb-0 text-body-secondary">
                <?= htmlspecialchars($branding['home_intro'] ?? 'Verwalte Schulungen, Teilnehmerlisten und Einladungen an einem Ort.') ?>
              </p>
            </div>
            <?php $legal = $branding['legal'] ?? []; ?>
            <div class="small text-body-secondary mt-4">
              <?php if (!empty($legal['impressum']['url'])): ?>
                <a class="text-decoration-none" href="<?= htmlspecialchars($legal['impressum']['url'], ENT_QUOTES) ?>" target="_blank" rel="noopener">Impressum</a>
              <?php endif; ?>
              <?php if (!empty($legal['privacy']['url'])): ?>
                <span class="mx-2">•</span>
                <a class="text-decoration-none" href="<?= htmlspecialchars($legal['privacy']['url'], ENT_QUOTES) ?>" target="_blank" rel="noopener">Datenschutz</a>
              <?php endif; ?>
            </div>
          </div>
        </div>
        <div class="col-md-7 bg-body">
          <div class="p-4 p-lg-5">
            <div class="text-center mb-4">
              <?php $logo = $branding['header_logo']['path'] ?? null; ?>
              <?php if (!empty($logo)): ?>
                <?php $logoUrl = preg_match('#^https?://#i', $logo) ? $logo : url_for($logo); ?>
                <img src="<?= htmlspecialchars($logoUrl, ENT_QUOTES) ?>"
                     class="img-fluid mb-3"
                     style="max-height: 70px;"
                     alt="<?= htmlspecialchars($branding['header_logo']['alt'] ?? ($branding['company_name'] ?? 'Logo')) ?>">
              <?php endif; ?>
              <h2 class="h4 mb-1">Anmelden</h2>
              <p class="text-body-secondary mb-0">Melde dich mit deinem Königsbräu-Konto an, um fortzufahren.</p>
            </div>

            <?php if (!empty($flashMessage)): ?>
              <div class="alert alert-danger" role="alert">
                <?= htmlspecialchars($flashMessage) ?>
              </div>
            <?php endif; ?>

            <form method="post" id="loginForm" class="d-grid gap-3">
              <input type="hidden" name="redirect" value="<?= htmlspecialchars((string)($redirectTarget ?? '/'), ENT_QUOTES) ?>">
              <button type="submit" class="btn btn-primary btn-lg w-100 d-flex align-items-center justify-content-center gap-2">
                <i class="fa-solid fa-right-to-bracket" aria-hidden="true"></i>
                <span>Login mit Königsbräu</span>
              </button>
              <p class="text-center small text-body-secondary mb-0">
                Klicke auf den Button, um zum Login weitergeleitet zu werden.
              </p>
            </form>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
