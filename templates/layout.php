<!DOCTYPE html>
<html lang="de" data-bs-theme="auto">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($title ?? 'Seite') ?></title>
    <script>
        (() => {
            'use strict';

            const storageKey = 'theme';
            const getStoredTheme = () => localStorage.getItem(storageKey);
            const setStoredTheme = theme => localStorage.setItem(storageKey, theme);

            const getPreferredTheme = () => {
                const storedTheme = getStoredTheme();
                if (storedTheme) {
                    return storedTheme;
                }

                return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
            };

            const setTheme = theme => {
                if (theme === 'auto') {
                    const systemPrefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                    document.documentElement.setAttribute('data-bs-theme', systemPrefersDark ? 'dark' : 'light');
                } else {
                    document.documentElement.setAttribute('data-bs-theme', theme);
                }
            };

            setTheme(getPreferredTheme());

            window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
                if (getStoredTheme() !== 'light' && getStoredTheme() !== 'dark') {
                    setTheme(getPreferredTheme());
                }
            });

            window.addEventListener('DOMContentLoaded', () => {
                const storedTheme = getStoredTheme() || 'auto';
                const activeButton = document.querySelector(`[data-bs-theme-value="${storedTheme}"]`);
                if (activeButton) {
                    activeButton.classList.add('active');
                    activeButton.setAttribute('aria-pressed', 'true');
                }

                document.querySelectorAll('[data-bs-theme-value]').forEach(button => {
                    if (button !== activeButton) {
                        button.setAttribute('aria-pressed', 'false');
                    }

                    button.addEventListener('click', () => {
                        const theme = button.getAttribute('data-bs-theme-value');
                        setStoredTheme(theme);
                        setTheme(theme);

                        document.querySelectorAll('[data-bs-theme-value].active').forEach(active => {
                            active.classList.remove('active');
                            active.setAttribute('aria-pressed', 'false');
                        });

                        button.classList.add('active');
                        button.setAttribute('aria-pressed', 'true');
                    });
                });
            });
        })();
    </script>
    <!-- Styles -->
    <link rel="stylesheet" href="<?= htmlspecialchars(url_for('node_modules/bootstrap/dist/css/bootstrap.min.css'), ENT_QUOTES) ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars(url_for('node_modules/tabulator-tables/dist/css/tabulator_bootstrap5.min.css'), ENT_QUOTES) ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars(url_for('node_modules/@fortawesome/fontawesome-free/css/all.min.css'), ENT_QUOTES) ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars(url_for('public/css/custom.css'), ENT_QUOTES) ?>">


</head>
<?php $branding = $branding ?? get_branding(); ?>
<body class="d-flex flex-column min-vh-100">
<?php include "templates/_navbar.php"; ?>
<main class="flex-grow-1">
<div class="container py-4">
    <h1><?= htmlspecialchars($title ?? ($branding['app_title'] ?? 'Seite')) ?></h1>
        <?php if (!empty($_SESSION['meldung'])): ?>
  <div class="alert alert-info"><?= htmlspecialchars($_SESSION['meldung']) ?></div>
  <?php unset($_SESSION['meldung']); ?>
<?php endif; ?>
<?php if (!empty($_SESSION['fehlermeldung'])): ?>
  <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['fehlermeldung']) ?></div>
  <?php unset($_SESSION['fehlermeldung']); ?>
<?php endif; ?>

    <?= $content ?>
</div>
</main>

<footer class="footer mt-auto py-4 border-top bg-body-tertiary">
  <div class="container">
    <div class="row align-items-center gy-3">
      <div class="col-lg">
        <div class="text-uppercase fw-semibold small text-secondary mb-1">
          Softwareprojekt der <?= htmlspecialchars($branding['project_owner'] ?? '') ?>
        </div>
        <p class="mb-0 text-body-secondary small">
          <?php if (!empty($branding['primary_client'])): ?>
            Entwickelt für <?= htmlspecialchars($branding['primary_client']) ?>
            <?php if (!empty($branding['group_reference']) && $branding['group_reference'] !== $branding['primary_client']): ?>
              – einsetzbar innerhalb der <?= htmlspecialchars($branding['group_reference']) ?>.
            <?php else: ?>
              .
            <?php endif; ?>
          <?php endif; ?>
        </p>
      </div>
      <?php if (!empty($branding['logos'])): ?>
        <div class="col-lg-auto ms-lg-auto">
          <div class="d-flex flex-wrap align-items-center justify-content-lg-end gap-3 footer-logos">
            <?php foreach ($branding['logos'] as $logo): ?>
              <?php if (!empty($logo['path'])): ?>
                <img src="<?= htmlspecialchars(url_for($logo['path']), ENT_QUOTES) ?>"
                     alt="<?= htmlspecialchars($logo['alt'] ?? '') ?>"
                     class="footer-logo img-fluid">
              <?php endif; ?>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>
    </div>
    <?php $legal = $branding['legal'] ?? []; ?>
    <?php if (!empty($legal['impressum']['url']) || !empty($legal['privacy']['url'])): ?>
      <div class="mt-3 small">
        <?php if (!empty($legal['impressum']['url'])): ?>
          <a class="link-secondary text-decoration-none me-3" href="<?= htmlspecialchars($legal['impressum']['url'], ENT_QUOTES) ?>" target="_blank" rel="noopener">
            <?= htmlspecialchars($legal['impressum']['label'] ?? 'Impressum') ?>
          </a>
        <?php endif; ?>
        <?php if (!empty($legal['privacy']['url'])): ?>
          <a class="link-secondary text-decoration-none" href="<?= htmlspecialchars($legal['privacy']['url'], ENT_QUOTES) ?>" target="_blank" rel="noopener">
            <?= htmlspecialchars($legal['privacy']['label'] ?? 'Datenschutz') ?>
          </a>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>
</footer>

<!-- Scripts -->
    <script src="<?= htmlspecialchars(url_for('node_modules/jquery/dist/jquery.min.js'), ENT_QUOTES) ?>"></script>
    <script src="<?= htmlspecialchars(url_for('node_modules/bootstrap/dist/js/bootstrap.bundle.min.js'), ENT_QUOTES) ?>"></script>
    <script src="<?= htmlspecialchars(url_for('node_modules/htmx.org/dist/htmx.min.js'), ENT_QUOTES) ?>"></script>
    <script src="<?= htmlspecialchars(url_for('node_modules/tabulator-tables/dist/js/tabulator.min.js'), ENT_QUOTES) ?>"></script>
    <script>
        (() => {
            'use strict';

            const resetButton = (button) => {
                if (!button) {
                    return;
                }

                if (button.dataset.confirmTimeoutId) {
                    clearTimeout(Number(button.dataset.confirmTimeoutId));
                    delete button.dataset.confirmTimeoutId;
                }

                delete button.dataset.doubleConfirmState;

                const defaultLabel = button.querySelector('[data-label-default]');
                const confirmLabel = button.querySelector('[data-label-confirm]');

                if (defaultLabel) {
                    defaultLabel.classList.remove('d-none');
                }

                if (confirmLabel) {
                    confirmLabel.classList.add('d-none');
                }
            };

            document.addEventListener('click', (event) => {
                const button = event.target.closest('button[data-double-confirm]');

                if (!button) {
                    document.querySelectorAll('button[data-double-confirm][data-double-confirm-state="awaiting"]').forEach(resetButton);
                    return;
                }

                if (button.dataset.doubleConfirmState === 'awaiting') {
                    resetButton(button);
                    button.dispatchEvent(new CustomEvent('confirmed', { bubbles: true }));
                    return;
                }

                event.preventDefault();
                event.stopPropagation();

                document.querySelectorAll('button[data-double-confirm][data-double-confirm-state="awaiting"]').forEach(otherButton => {
                    if (otherButton !== button) {
                        resetButton(otherButton);
                    }
                });

                button.dataset.doubleConfirmState = 'awaiting';

                const defaultLabel = button.querySelector('[data-label-default]');
                const confirmLabel = button.querySelector('[data-label-confirm]');

                if (defaultLabel) {
                    defaultLabel.classList.add('d-none');
                }

                if (confirmLabel) {
                    confirmLabel.classList.remove('d-none');
                }

                button.dataset.confirmTimeoutId = String(setTimeout(() => {
                    resetButton(button);
                }, 3000));
            });

            document.body.addEventListener('htmx:beforeSwap', (event) => {
                const detail = event.detail;

                if (!detail || !detail.xhr) {
                    return;
                }

                const status = detail.xhr.status;

                if (status >= 400 && status < 600) {
                    detail.shouldSwap = true;
                    detail.isError = false;
                }
            });
        })();
    </script>

<?php if (!empty($scripts)) echo $scripts; ?>
</body>
</html>
