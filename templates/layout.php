<?php
$branding = $branding ?? get_branding();
$brandColors = $branding['nav_colors'] ?? [];
$brandBackground = $brandColors['background'] ?? '#0D6EFD';
$brandText = $brandColors['text'] ?? '#FFFFFF';
$themeColors = $branding['theme_colors'] ?? [];
$primaryColor = $themeColors['primary'] ?? '#0D6EFD';
$primaryTextColor = $themeColors['primary_text'] ?? '#FFFFFF';
$lightColor = $themeColors['light'] ?? '#F8F9FA';
$darkColor = $themeColors['dark'] ?? '#212529';
$primaryHex = ltrim($primaryColor, '#');
$primaryRgb = strlen($primaryHex) === 6
    ? implode(', ', [hexdec(substr($primaryHex, 0, 2)), hexdec(substr($primaryHex, 2, 2)), hexdec(substr($primaryHex, 4, 2))])
    : '13, 110, 253';
?>
<!DOCTYPE html>
<html lang="de" data-bs-theme="auto">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($title ?? 'Seite') ?></title>
    <link rel="icon" type="image/svg+xml" href="<?= htmlspecialchars(url_for('public/favicon.svg'), ENT_QUOTES) ?>">
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
    <link rel="stylesheet" href="<?= htmlspecialchars(url_for('node_modules/tom-select/dist/css/tom-select.bootstrap5.min.css'), ENT_QUOTES) ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars(url_for('node_modules/tabulator-tables/dist/css/tabulator_bootstrap5.min.css'), ENT_QUOTES) ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars(url_for('node_modules/@fortawesome/fontawesome-free/css/all.min.css'), ENT_QUOTES) ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars(url_for('public/css/custom.css'), ENT_QUOTES) ?>">
    <style>
        :root {
            --app-brand-bg: <?= htmlspecialchars($brandBackground, ENT_QUOTES) ?>;
            --app-brand-color: <?= htmlspecialchars($brandText, ENT_QUOTES) ?>;
            --app-primary: <?= htmlspecialchars($primaryColor, ENT_QUOTES) ?>;
            --app-primary-text: <?= htmlspecialchars($primaryTextColor, ENT_QUOTES) ?>;
            --app-theme-light: <?= htmlspecialchars($lightColor, ENT_QUOTES) ?>;
            --app-theme-dark: <?= htmlspecialchars($darkColor, ENT_QUOTES) ?>;
            --bs-primary: var(--app-primary);
            --bs-primary-rgb: <?= htmlspecialchars($primaryRgb, ENT_QUOTES) ?>;
            --bs-primary-contrast: var(--app-primary-text);
        }
        .text-bg-primary {
            color: var(--app-primary-text) !important;
        }
        .brand-panel {
            background: var(--app-brand-bg);
            color: var(--app-brand-color);
        }
        .brand-panel .text-body-secondary,
        .brand-panel a {
            color: inherit !important;
            opacity: .82;
        }
        .btn-primary {
            --bs-btn-bg: var(--app-primary);
            --bs-btn-border-color: var(--app-primary);
            --bs-btn-color: var(--app-primary-text);
            --bs-btn-hover-bg: color-mix(in srgb, var(--app-primary), black 12%);
            --bs-btn-hover-border-color: color-mix(in srgb, var(--app-primary), black 12%);
            --bs-btn-hover-color: var(--app-primary-text);
            --bs-btn-active-bg: color-mix(in srgb, var(--app-primary), black 18%);
            --bs-btn-active-border-color: color-mix(in srgb, var(--app-primary), black 18%);
            --bs-btn-active-color: var(--app-primary-text);
        }
        [data-bs-theme="light"] .brand-panel { background: var(--app-theme-light); color: var(--bs-body-color); }
        [data-bs-theme="dark"] .brand-panel { background: var(--app-theme-dark); color: var(--bs-body-color); }
        .brand-logo-for-dark { display: none; }
        [data-bs-theme="dark"] .brand-logo-for-light { display: none; }
        [data-bs-theme="dark"] .brand-logo-for-dark { display: inline-block; }
    </style>
</head>
<body class="d-flex flex-column min-vh-100">
<?php include "templates/_navbar.php"; ?>
<main class="flex-grow-1">
<div class="container py-4">
    <header class="page-header mb-4 noprint">
      <h1 class="mb-1"><?= htmlspecialchars($title ?? ($branding['app_title'] ?? 'Seite')) ?></h1>
      <?php if (($showCompanySubtitle ?? true) && !empty($branding['company_name'])): ?>
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

<?php $versionDisplay = app_version_display_data(); ?>
<footer class="footer mt-auto py-4 border-top bg-body-tertiary noprint">
  <div class="container">
    <div class="row align-items-center gy-3">
      <div class="col-lg">
        <div class="text-uppercase fw-semibold small text-secondary mb-1">
          Softwareprojekt der CENEOS GmbH
        </div>
      </div>
      <?php if (!empty($versionDisplay['version'])): ?>
        <div class="col-lg-auto ms-lg-auto text-lg-end">
          <span class="small text-body-secondary">
            Version <?= htmlspecialchars($versionDisplay['version']) ?>
            <?php if (!empty($versionDisplay['commit'])): ?>
              <span class="text-body-tertiary mx-1">·</span>
              <span class="font-monospace">#<?= htmlspecialchars($versionDisplay['commit']) ?></span>
            <?php endif; ?>
            <?php if (!empty($versionDisplay['build_date_human']) && !empty($versionDisplay['build_date_iso'])): ?>
              <span class="text-body-tertiary mx-1">·</span>
              <time datetime="<?= htmlspecialchars($versionDisplay['build_date_iso'], ENT_QUOTES) ?>">
                <?= htmlspecialchars($versionDisplay['build_date_human']) ?>
              </time>
            <?php endif; ?>
          </span>
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
    <script src="<?= htmlspecialchars(url_for('node_modules/tom-select/dist/js/tom-select.complete.min.js'), ENT_QUOTES) ?>"></script>
    <script src="<?= htmlspecialchars(url_for('public/js/search-select.js'), ENT_QUOTES) ?>"></script>
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

                if (!defaultLabel && !confirmLabel && button.dataset.originalLabel) {
                    button.textContent = button.dataset.originalLabel;
                }

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
                    if (button.form) button.form.dataset.confirmed = '1';
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

                if (!defaultLabel && !confirmLabel) {
                    button.dataset.originalLabel = button.textContent.trim();
                    button.textContent = 'Nochmal klicken';
                }

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

            // Migrate legacy inline confirm() handlers to the same two-click
            // interaction. This keeps destructive actions consistent across
            // older templates and dynamically generated buttons.
            const migrateConfirmForms = () => {
                document.querySelectorAll('form').forEach(form => {
                    const inline = form.getAttribute('onsubmit');
                    const handler = form.onsubmit;
                    if (!((inline && inline.includes('confirm(')) || (handler && String(handler).includes('confirm(')))) return;
                    form.removeAttribute('onsubmit');
                    form.onsubmit = null;
                    const button = form.querySelector('button[type="submit"], button:not([type])');
                    if (!button || button.dataset.doubleConfirm) return;
                    button.dataset.doubleConfirm = '1';
                });
            };
            migrateConfirmForms();
            new MutationObserver(migrateConfirmForms).observe(document.body, {childList: true, subtree: true});

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
