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
$assetVersion = app_asset_version();
$layoutUser = current_user();
$displayPreference = DisplayPreferenceService::forUser((int) ($layoutUser->id ?? 0));
?>
<!DOCTYPE html>
<html lang="de" data-bs-theme="auto" data-theme-preference="<?= htmlspecialchars($displayPreference['theme'], ENT_QUOTES) ?>" data-contrast="<?= htmlspecialchars($displayPreference['contrast'], ENT_QUOTES) ?>" data-font-scale="<?= htmlspecialchars($displayPreference['font_scale'], ENT_QUOTES) ?>" data-font-weight="<?= htmlspecialchars($displayPreference['font_weight'], ENT_QUOTES) ?>" data-motion="<?= htmlspecialchars($displayPreference['motion'], ENT_QUOTES) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($title ?? 'Seite') ?></title>
    <link rel="icon" type="image/svg+xml" href="<?= htmlspecialchars(url_for('public/favicon.svg'), ENT_QUOTES) ?>">
    <script>
        (() => {
            'use strict';

            const storageKey = 'theme-preview';
            const preferenceEndpoint = <?= json_encode($layoutUser !== null ? url_for('profil') : '', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
            let serverPreference = <?= json_encode($displayPreference, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
            const getStoredTheme = () => sessionStorage.getItem(storageKey);
            const setStoredTheme = theme => sessionStorage.setItem(storageKey, theme);

            const persistPreference = () => {
                if (!preferenceEndpoint) return;
                const body = new URLSearchParams({action: 'save_display_preferences', ...serverPreference});
                fetch(preferenceEndpoint, {method: 'POST', credentials: 'same-origin', body, headers: {'X-Requested-With': 'XMLHttpRequest'}}).catch(() => {});
            };

            const getPreferredTheme = () => {
                const storedTheme = serverPreference.theme === 'auto' ? getStoredTheme() : serverPreference.theme;
                if (storedTheme) {
                    return storedTheme;
                }

                return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
            };

            const setTheme = theme => {
                document.documentElement.setAttribute('data-theme-preference', theme);
                if (theme === 'auto') {
                    const systemPrefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                    document.documentElement.setAttribute('data-bs-theme', systemPrefersDark ? 'dark' : 'light');
                } else {
                    document.documentElement.setAttribute('data-bs-theme', theme);
                }
            };

            const applyAccessibilityPreference = () => {
                document.documentElement.setAttribute('data-contrast', serverPreference.contrast || 'standard');
                        document.documentElement.setAttribute('data-font-scale', serverPreference.font_scale || 'standard');
                        document.documentElement.setAttribute('data-font-weight', serverPreference.font_weight || 'standard');
                document.documentElement.setAttribute('data-motion', serverPreference.motion || 'system');
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

            const applyTheme = (theme, persist = false) => {
                serverPreference = {...serverPreference, theme};
                setStoredTheme(theme);
                setTheme(theme);
                updateThemeUI(theme);
                if (persist) persistPreference();
            };

            applyAccessibilityPreference();
            setTheme(getPreferredTheme());

            window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
                if (getStoredTheme() !== 'light' && getStoredTheme() !== 'dark') {
                    setTheme(getPreferredTheme());
                }
            });

            window.addEventListener('DOMContentLoaded', () => {
                const initialTheme = serverPreference.theme === 'auto' ? (getStoredTheme() || 'auto') : serverPreference.theme;
                updateThemeUI(initialTheme);

                const navbar = document.getElementById('appNavbarContent');
                const navbarToggle = document.querySelector('[data-bs-target="#appNavbarContent"]');
                if (navbar && navbarToggle) {
                    navbar.addEventListener('shown.bs.collapse', () => {
                        navbar.querySelector('a, button, input, select, textarea')?.focus({preventScroll: true});
                    });
                    navbar.addEventListener('hidden.bs.collapse', () => navbarToggle.focus({preventScroll: true}));
                }

                document.querySelectorAll('[data-bs-theme-value]').forEach(button => {
                    button.addEventListener('click', () => {
                        const theme = button.getAttribute('data-bs-theme-value');
                        applyTheme(theme, true);
                    });
                });

                const cycleButton = document.getElementById('themeCycleButton');
                if (cycleButton) {
                    cycleButton.addEventListener('click', () => {
                        const storedTheme = serverPreference.theme === 'auto' ? (getStoredTheme() || cycleButton.dataset.currentTheme || 'auto') : serverPreference.theme;
                        const currentIndex = themeOrder.indexOf(storedTheme);
                        const nextTheme = themeOrder[(currentIndex + 1) % themeOrder.length] || themeOrder[0];
                        applyTheme(nextTheme, true);
                    });
                }

                const preferenceForm = document.querySelector('[data-display-preferences-form]');
                if (preferenceForm) {
                    preferenceForm.addEventListener('change', () => {
                        const values = new FormData(preferenceForm);
                        serverPreference = {
                            theme: String(values.get('theme') || 'auto'),
                            contrast: String(values.get('contrast') || 'standard'),
                            font_scale: String(values.get('font_scale') || 'standard'),
                            font_weight: values.get('font_weight') === 'bold' ? 'bold' : 'standard',
                            motion: String(values.get('motion') || 'system')
                        };
                        applyAccessibilityPreference();
                        setTheme(serverPreference.theme === 'auto'
                            ? (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light')
                            : serverPreference.theme);
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
    <link rel="stylesheet" href="<?= htmlspecialchars(url_for('public/css/custom.css') . '?v=' . rawurlencode($assetVersion), ENT_QUOTES) ?>">
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
        /* Gemeinsame Bootstrap-Oberflächen für alle Verwaltungsseiten. */
        .card > summary.card-header,
        details.card > summary.card-header { cursor: pointer; list-style: none; }
        details.card > summary.card-header::-webkit-details-marker { display: none; }
        details.card > summary.card-header::before { content: '\f078'; font-family: 'Font Awesome 6 Free'; font-weight: 900; margin-right: .55rem; color: var(--app-primary); transition: transform .15s ease; }
        details.card[open] > summary.card-header::before { transform: rotate(180deg); }
        [data-bs-theme="light"] details.card > summary.card-header::before { color: var(--bs-secondary-color); }
        .table > thead { --bs-table-bg: color-mix(in srgb, var(--app-primary), transparent 88%); }
        .table > thead th { color: var(--bs-emphasis-color); font-weight: 700; border-bottom-width: 2px; }
        .table-hover > tbody > tr:hover { --bs-table-hover-bg: color-mix(in srgb, var(--app-primary), transparent 92%); }
        .badge { letter-spacing: .01em; }
        .status-badge { min-width: 5.5rem; text-align: center; }
        .structure-filter-item > details,
        .structure-filter-item > details > summary { transition: border-color .15s ease, box-shadow .15s ease; }
        .structure-filter-item > details[open] { border-color: var(--app-primary) !important; box-shadow: 0 .2rem .65rem color-mix(in srgb, var(--app-primary), transparent 82%); }
        .alert, .card, .table-responsive { border-radius: .65rem; }
        #page-action-navigation { min-height: 2.65rem; transition: opacity .18s ease, transform .18s ease; }
        #page-action-navigation.action-nav-empty { opacity: 0; pointer-events: none; transform: translateY(-.2rem); }
        #page-action-navigation .btn { white-space: nowrap; }
        @media (max-width: 575.98px) {
            .card-body { padding: .85rem; }
            .table { font-size: .875rem; }
            details.card > summary.card-header { padding: .7rem .85rem; }
        }
        [data-bs-theme="light"] .brand-panel { background: var(--app-theme-light); color: var(--bs-body-color); }
        [data-bs-theme="dark"] .brand-panel { background: var(--app-theme-dark); color: var(--bs-body-color); }
        .brand-logo-for-dark { display: none; }
        [data-bs-theme="dark"] .brand-logo-for-light { display: none; }
        [data-bs-theme="dark"] .brand-logo-for-dark { display: inline-block; }
    </style>
</head>
<body class="d-flex flex-column min-vh-100">
<a class="skip-link" href="#main-content">Zum Inhalt springen</a>
<?php include "templates/_navbar.php"; ?>
<main id="main-content" class="flex-grow-1" tabindex="-1">
<div class="container app-main-container py-4">
    <header class="page-header mb-4 noprint">
      <h1 class="mb-1"><?= htmlspecialchars($title ?? ($branding['app_title'] ?? 'Seite')) ?></h1>
      <?php if (($showCompanySubtitle ?? true) && !empty($branding['company_name'])): ?>
        <p class="mb-0 text-body-secondary">für <?= htmlspecialchars($branding['company_name']) ?></p>
      <?php endif; ?>
    </header>
        <?php if (!empty($_SESSION['meldung'])): ?>
  <div class="alert alert-info" role="status" aria-live="polite"><?= htmlspecialchars($_SESSION['meldung']) ?></div>
  <?php unset($_SESSION['meldung']); ?>
  <?php endif; ?>
<?php if (!empty($_SESSION['fehlermeldung'])): ?>
  <div class="alert alert-danger" role="alert"><?= htmlspecialchars($_SESSION['fehlermeldung']) ?></div>
  <?php unset($_SESSION['fehlermeldung']); ?>
<?php endif; ?>
<?php if ((int) ($_SESSION['impersonator_user_id'] ?? 0) > 0): ?>
  <div class="alert alert-warning d-flex align-items-center gap-2 flex-wrap" role="status">
    <i class="fa-solid fa-user-secret" aria-hidden="true"></i>
    <span class="flex-grow-1"><strong>Nutzeranmeldung aktiv.</strong> Du siehst die Anwendung als <?= htmlspecialchars((string) ((current_user()->name ?? '') ?: (current_user()->email ?? 'Nutzer/in'))) ?>.</span>
    <form method="post" action="<?= htmlspecialchars(url_for('admin/nutzer/login-as/stop'), ENT_QUOTES) ?>" class="mb-0"><button class="btn btn-sm btn-warning text-dark" type="submit"><i class="fa-solid fa-arrow-right-from-bracket me-1" aria-hidden="true"></i>Zurück zum Superadmin</button></form>
  </div>
<?php endif; ?>
<?php $loginReminders = is_array($_SESSION['login_reminders'] ?? null) ? $_SESSION['login_reminders'] : []; unset($_SESSION['login_reminders']); ?>
<?php foreach ($loginReminders as $loginReminder): ?>
  <div class="alert alert-warning d-flex align-items-start gap-2" role="alert">
    <i class="fa-solid fa-triangle-exclamation mt-1" aria-hidden="true"></i>
    <div class="flex-grow-1"><strong><?= htmlspecialchars((string) ($loginReminder['title'] ?? 'Hinweis')) ?></strong><div class="text-pre-line"><?= nl2br(htmlspecialchars((string) ($loginReminder['message'] ?? '')), false) ?></div><?php if (is_array($loginReminder['details'] ?? null) && $loginReminder['details'] !== []): ?><details class="mt-2"><summary class="small">Prüfungen anzeigen</summary><ul class="small mb-0 mt-1 ps-3"><?php foreach ($loginReminder['details'] as $detail): ?><li><?= htmlspecialchars((string) ($detail['test_date'] ?? '')) ?> · <?= htmlspecialchars((string) ($detail['inspection_number'] ?? '')) ?><?= trim((string) ($detail['device_name'] ?? '')) !== '' ? ' · ' . htmlspecialchars((string) $detail['device_name']) : '' ?></li><?php endforeach; ?></ul></details><?php endif; ?></div>
    <?php if (trim((string) ($loginReminder['action_url'] ?? '')) !== ''): ?><a class="btn btn-sm btn-warning text-dark text-nowrap" href="<?= htmlspecialchars((string) $loginReminder['action_url'], ENT_QUOTES) ?>">Öffnen</a><?php endif; ?>
  </div>
<?php endforeach; ?>

    <nav id="page-action-navigation" class="action-nav-empty mb-3" aria-label="Schnellzugriff auf Aktionen" aria-hidden="true"></nav>

    <?= $content ?>
</div>
</main>

<?php $versionDisplay = app_version_display_data(); $baseVersionDisplay = ceneos_base_version_display_data(); ?>
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
          <span class="d-block small text-body-tertiary mt-1">
            CENEOS Base <?= htmlspecialchars($baseVersionDisplay['version']) ?>
            <?php if (!empty($baseVersionDisplay['commit'])): ?>
              <span class="mx-1">·</span><span class="font-monospace">#<?= htmlspecialchars($baseVersionDisplay['commit']) ?></span>
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
    <script src="<?= htmlspecialchars(url_for('public/js/search-select.js') . '?v=' . rawurlencode($assetVersion), ENT_QUOTES) ?>"></script>
    <script src="<?= htmlspecialchars(url_for('public/js/companion-inbox.js') . '?v=' . rawurlencode($assetVersion), ENT_QUOTES) ?>"></script>
    <script src="<?= htmlspecialchars(url_for('node_modules/tabulator-tables/dist/js/tabulator.min.js'), ENT_QUOTES) ?>"></script>
    <script src="<?= htmlspecialchars(url_for('public/js/qrcode.min.js') . '?v=' . rawurlencode($assetVersion), ENT_QUOTES) ?>"></script>
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

            // Einheitliche Aktionssymbole auf allen Seiten. Bereits vorhandene
            // Font-Awesome-Icons bleiben unangetastet; das gilt auch für
            // Inhalte, die später per HTMX in die Seite eingesetzt werden.
            const enhanceActionButtons = (root = document) => {
                const icons = [
                    [/^(zurück|abbrechen|zur übersicht)/i, 'fa-arrow-left'],
                    [/löschen|entfernen|archivieren/i, 'fa-trash-can'],
                    [/speichern|übernehmen/i, 'fa-floppy-disk'],
                    [/import/i, 'fa-file-import'],
                    [/export|csv|ods|xlsx|json/i, 'fa-file-export'],
                    [/pdf/i, 'fa-file-pdf'],
                    [/zip/i, 'fa-file-zipper'],
                    [/hochladen|upload/i, 'fa-cloud-arrow-up'],
                    [/download|herunterladen/i, 'fa-file-arrow-down'],
                    [/senden|übermitteln/i, 'fa-paper-plane'],
                    [/link/i, 'fa-link'],
                    [/drucken|druckansicht/i, 'fa-print'],
                    [/suchen|filtern/i, 'fa-magnifying-glass'],
                    [/zurücksetzen|reset/i, 'fa-rotate-left'],
                    [/aktualisieren|synchronisieren/i, 'fa-arrows-rotate'],
                    [/bearbeiten|edit/i, 'fa-pen-to-square'],
                    [/öffnen|anzeigen/i, 'fa-eye'],
                    [/verschieben/i, 'fa-arrows-up-down-left-right'],
                    [/markieren|auswählen/i, 'fa-check'],
                    [/starten|erstellen|anlegen|neu/i, 'fa-plus'],
                    [/abbrechen/i, 'fa-xmark']
                ];
                const candidates = [];
                if (root.matches?.('button.btn, a.btn')) candidates.push(root);
                root.querySelectorAll?.('button.btn, a.btn').forEach(button => candidates.push(button));
                candidates.forEach(button => {
                    if (button.dataset.actionIconBound === '1' || button.matches('#themeCycleButton, [data-bs-theme-value], .dropdown-toggle') || button.querySelector('i.fa-solid, i.fas, i.far, i.fab')) return;
                    const label = button.textContent.trim();
                    if (!label) return;
                    const match = icons.find(([pattern]) => pattern.test(label));
                    if (!match) return;
                    const icon = document.createElement('i');
                    icon.className = `fa-solid ${match[1]} me-1`;
                    icon.setAttribute('aria-hidden', 'true');
                    button.prepend(icon);
                    button.dataset.actionIconBound = '1';
                });
            };
            enhanceActionButtons();
            new MutationObserver(records => records.forEach(record => record.addedNodes.forEach(node => {
                if (node.nodeType === Node.ELEMENT_NODE) enhanceActionButtons(node);
            }))).observe(document.body, {childList: true, subtree: true});

            // Einheitliche PDF-Vorschau: Berichte, Nachweise und Exporte
            // verhalten sich in jeder Ansicht gleich. Der direkte Download
            // bleibt als mobiler Browser-Fallback jederzeit verfügbar.
            document.addEventListener('click', event => {
                const link = event.target.closest('a[href]');
                if (!link || link.dataset.noPdfPreview === '1') return;
                const href = link.href || '';
                const looksLikePdf = /\.pdf(?:$|[?#])/i.test(href)
                    || /\/(bericht|nachweis|befaehigung)(?:\/|$)/.test(new URL(href, window.location.href).pathname)
                    || Boolean(link.querySelector('.fa-file-pdf'));
                if (!looksLikePdf || !window.bootstrap?.Modal) return;
                event.preventDefault();
                let modal = document.getElementById('app-pdf-preview-modal');
                if (!modal) {
                    document.body.insertAdjacentHTML('beforeend', '<div class="modal fade" id="app-pdf-preview-modal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-xl modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h2 class="modal-title fs-5"><i class="fa-solid fa-file-pdf me-2 text-danger" aria-hidden="true"></i>PDF-Vorschau</h2><a class="btn btn-sm btn-secondary me-2" data-pdf-download target="_blank" rel="noopener"><i class="fa-solid fa-download me-1" aria-hidden="true"></i>Öffnen / herunterladen</a><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button></div><div class="modal-body p-0"><iframe class="w-100 border-0" style="height:75vh" title="PDF-Vorschau" loading="lazy"></iframe><p class="small text-body-secondary p-3 mb-0 d-md-none">Falls die Vorschau auf diesem Gerät nicht erscheint, nutze „Öffnen / herunterladen“.</p></div></div></div></div>');
                    modal = document.getElementById('app-pdf-preview-modal');
                }
                modal.querySelector('iframe').src = href;
                modal.querySelector('[data-pdf-download]').href = href;
                window.bootstrap.Modal.getOrCreateInstance(modal).show();
            });

            // Jede Seite meldet ihre größeren Aktionsbereiche mit
            // data-action-nav an. Die Navigation wird zentral gebaut, bleibt
            // nach HTMX-Swaps korrekt und vermeidet pro Seite eigene Sprung-UI.
            const buildActionNavigation = () => {
                const navigation = document.getElementById('page-action-navigation');
                if (!navigation) return;
                const targets = [...document.querySelectorAll('[data-action-nav]')]
                    .filter(target => target.id && !target.closest('#page-action-navigation'));
                const unique = [...new Map(targets.map(target => [target.id, target])).values()];
                // Ein einzelner Aktionsbereich ist an seinem fachlichen Ort
                // bereits eindeutig. Die Kopfzeile ist nur bei mehreren
                // Sprungzielen eine echte Orientierungshilfe.
                if (unique.length < 2) {
                    navigation.classList.add('action-nav-empty');
                    navigation.setAttribute('aria-hidden', 'true');
                    navigation.replaceChildren();
                    return;
                }
                const toolbar = document.createElement('div');
                toolbar.className = 'card card-body py-2 d-flex flex-row flex-wrap align-items-center gap-2';
                const title = document.createElement('span');
                title.className = 'small fw-semibold text-body-secondary me-1';
                title.innerHTML = '<i class="fa-solid fa-bolt me-1" aria-hidden="true"></i>Aktionen:';
                toolbar.append(title);
                unique.forEach(target => {
                    const link = document.createElement('a');
                    link.className = 'btn btn-sm btn-outline-secondary';
                    link.href = '#' + encodeURIComponent(target.id);
                    const icon = target.dataset.actionIcon || 'fa-arrow-down';
                    link.innerHTML = `<i class="fa-solid ${icon} me-1" aria-hidden="true"></i>${target.dataset.actionNav}`;
                    toolbar.append(link);
                });
                navigation.replaceChildren(toolbar);
                navigation.classList.remove('action-nav-empty');
                navigation.setAttribute('aria-hidden', 'false');
            };
            buildActionNavigation();
            new MutationObserver(records => {
                const actionBlocksChanged = records.some(record => [...record.addedNodes].some(node =>
                    node.nodeType === Node.ELEMENT_NODE
                    && node.id !== 'page-action-navigation'
                    && (node.matches?.('[data-action-nav]') || node.querySelector?.('[data-action-nav]'))
                ));
                if (actionBlocksChanged) buildActionNavigation();
            }).observe(document.body, {childList: true, subtree: true});

            // Desktop users can open the utility menus on the right (profile,
            // notifications and theme) exactly like the primary navigation.
            // Bootstrap is used instead of CSS-only display so dropdown events
            // and HTMX notification refreshes continue to work.
            const enableUtilityDropdownHover = () => {
                if (!window.matchMedia('(min-width: 992px) and (hover: hover) and (pointer: fine)').matches || !window.bootstrap?.Dropdown) return;
                document.querySelectorAll('.navbar-hover-dropdown').forEach(container => {
                    if (container.dataset.hoverDropdownBound === '1') return;
                    const toggle = container.querySelector('[data-bs-toggle="dropdown"]');
                    if (!toggle) return;
                    container.dataset.hoverDropdownBound = '1';
                    let closeTimer = 0;
                    const dropdown = window.bootstrap.Dropdown.getOrCreateInstance(toggle);
                    container.addEventListener('mouseenter', () => {
                        window.clearTimeout(closeTimer);
                        dropdown.show();
                    });
                    container.addEventListener('mouseleave', () => {
                        closeTimer = window.setTimeout(() => dropdown.hide(), 180);
                    });
                });
            };
            enableUtilityDropdownHover();

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
