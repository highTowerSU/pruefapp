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

            const themeOrder = ['light', 'dark', 'auto'];
            const themeLabels = {
                light: 'Hell',
                dark: 'Dunkel',
                auto: 'Automatisch'
            };
            const themeIcons = {
                light: 'fa-sun',
                dark: 'fa-moon',
                auto: 'fa-circle-half-stroke'
            };

            const updateThemeUI = (theme) => {
                document.querySelectorAll('[data-bs-theme-value]').forEach(button => {
                    const value = button.getAttribute('data-bs-theme-value');
                    const isActive = value === theme;
                    button.classList.toggle('active', isActive);
                    button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
                });

                const cycleButton = document.getElementById('themeCycleButton');
                if (!cycleButton) {
                    return;
                }

                const iconElement = cycleButton.querySelector('[data-theme-icon]');
                const iconClass = themeIcons[theme] ?? themeIcons.auto;
                if (iconElement) {
                    iconElement.className = `fas ${iconClass}`;
                }

                const label = themeLabels[theme] ?? theme;
                const description = `Theme umschalten (aktuell: ${label})`;
                cycleButton.dataset.currentTheme = theme;
                cycleButton.setAttribute('aria-label', description);
                cycleButton.setAttribute('title', description);
            };

            const applyTheme = (theme) => {
                setStoredTheme(theme);
                setTheme(theme);
                updateThemeUI(theme);
            };

            setTheme(getPreferredTheme());

            window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
                if (getStoredTheme() !== 'light' && getStoredTheme() !== 'dark') {
                    setTheme(getPreferredTheme());
                }
            });

            window.addEventListener('DOMContentLoaded', () => {
                const initialTheme = getStoredTheme() || 'auto';
                updateThemeUI(initialTheme);

                document.querySelectorAll('[data-bs-theme-value]').forEach(button => {
                    button.addEventListener('click', () => {
                        const theme = button.getAttribute('data-bs-theme-value');
                        applyTheme(theme);
                    });
                });

                const cycleButton = document.getElementById('themeCycleButton');
                if (cycleButton) {
                    cycleButton.addEventListener('click', () => {
                        const storedTheme = getStoredTheme() || cycleButton.dataset.currentTheme || 'auto';
                        const currentIndex = themeOrder.indexOf(storedTheme);
                        const nextTheme = themeOrder[(currentIndex + 1) % themeOrder.length] || themeOrder[0];
                        applyTheme(nextTheme);
                    });
                }
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
    <header class="page-header mb-4">
      <h1 class="mb-1"><?= htmlspecialchars($title ?? ($branding['app_title'] ?? 'Seite')) ?></h1>
      <?php if (!empty($branding['company_name'])): ?>
        <p class="mb-0 text-body-secondary">für <?= htmlspecialchars($branding['company_name']) ?></p>
      <?php endif; ?>
    </header>
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

<footer class="footer mt-auto py-4 border-top bg-body-tertiary noprint">
  <div class="container">
    <div class="row align-items-center gy-3">
      <div class="col-lg">
        <div class="text-uppercase fw-semibold small text-secondary mb-1">
          Softwareprojekt der CENEOS GmbH
        </div>
      </div>
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
