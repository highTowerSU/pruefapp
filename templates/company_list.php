<?php /** @var array[] $companies */ ?>
<div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
  <div>
    <p class="text-body-secondary mb-0">
      Verwalte hier die Marken- und Firmeninformationen, die innerhalb der Kursverwaltung angezeigt werden.
    </p>
  </div>
  <a class="btn btn-primary" href="<?= htmlspecialchars(url_for('firmen/neu'), ENT_QUOTES) ?>">
    <i class="fa-solid fa-plus me-2" aria-hidden="true"></i>Neue Firma
  </a>
</div>

<div class="card shadow-sm border-0">
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead class="table-light">
        <tr>
          <th scope="col">Name</th>
          <th scope="col">Kurznamen</th>
          <th scope="col">Header-Logo</th>
          <th scope="col">Status</th>
          <th scope="col" class="text-end">Aktionen</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($companies === []): ?>
          <tr>
            <td colspan="5" class="text-center py-4 text-body-secondary">
              Es sind noch keine Firmen hinterlegt.
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($companies as $company): ?>
            <tr>
              <td>
                <div class="fw-semibold"><?= htmlspecialchars($company['name']) ?></div>
                <div class="small text-body-secondary">
                  <?= htmlspecialchars($company['primary_client'] ?: branding_project_owner()) ?>
                </div>
              </td>
              <td>
                <code><?= htmlspecialchars($company['slug']) ?></code>
              </td>
              <td>
                <?php if (!empty($company['header_logo_path'])): ?>
                  <img src="<?= htmlspecialchars($company['header_logo_url'], ENT_QUOTES) ?>"
                       alt="<?= htmlspecialchars($company['header_logo_alt'] ?? '') ?>"
                       class="img-fluid" style="max-height: 40px; width: auto;">
                <?php else: ?>
                  <span class="text-body-secondary small">Kein Logo hinterlegt</span>
                <?php endif; ?>
              </td>
              <td>
                <?php if (!empty($company['is_default'])): ?>
                  <span class="badge text-bg-primary">Standard</span>
                <?php else: ?>
                  <span class="badge text-bg-secondary">Optional</span>
                <?php endif; ?>
              </td>
              <td class="text-end">
                <div class="btn-group" role="group">
                  <a class="btn btn-outline-secondary btn-sm"
                     href="<?= htmlspecialchars(url_for('firmen/' . $company['id'] . '/bearbeiten'), ENT_QUOTES) ?>">
                    Bearbeiten
                  </a>
                  <form class="d-inline" method="post"
                        action="<?= htmlspecialchars(url_for('firmen/' . $company['id'] . '/standard'), ENT_QUOTES) ?>">
                    <button type="submit" class="btn btn-outline-primary btn-sm"<?= !empty($company['is_default']) ? ' disabled' : '' ?>>
                      Als Standard
                    </button>
                  </form>
                  <form class="d-inline" method="post"
                        action="<?= htmlspecialchars(url_for('firmen/' . $company['id'] . '/loeschen'), ENT_QUOTES) ?>"
                        onsubmit="return confirm('Soll diese Firma wirklich gelöscht werden?');">
                    <button type="submit" class="btn btn-outline-danger btn-sm"<?= !empty($company['is_default']) ? ' disabled' : '' ?>>
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
