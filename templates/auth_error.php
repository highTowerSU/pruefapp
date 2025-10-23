<?php
$retryUrl = $retryUrl ?? url_for();
$supportContact = trim((string) ($supportContact ?? ''));
?>
<section class="row justify-content-center">
  <div class="col-lg-8 col-xl-6">
    <div class="card shadow-sm">
      <div class="card-body py-5">
        <h2 class="h4 mb-3">Anmeldung derzeit nicht möglich</h2>
        <p class="mb-3">
          Die Verbindung zum Identitätsanbieter konnte nicht hergestellt werden.
          Bitte versuche es in einigen Minuten erneut.
        </p>
        <?php if ($supportContact !== ''): ?>
          <p class="mb-4">
            Sollte das Problem weiterhin bestehen, wende dich bitte an
            <?= htmlspecialchars($supportContact) ?>.
          </p>
        <?php else: ?>
          <p class="mb-4">
            Sollte das Problem weiterhin bestehen, kontaktiere bitte den Support.
          </p>
        <?php endif; ?>
        <div class="d-flex flex-wrap gap-2">
          <a class="btn btn-primary" href="<?= htmlspecialchars($retryUrl, ENT_QUOTES) ?>">Erneut versuchen</a>
          <a class="btn btn-outline-secondary" href="<?= htmlspecialchars(url_for('logout.php'), ENT_QUOTES) ?>">Abbrechen</a>
        </div>
      </div>
    </div>
  </div>
</section>
