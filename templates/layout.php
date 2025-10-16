<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($title ?? 'Seite') ?></title>
    <script>
        (function () {
            const storageKey = 'preferred-theme';

            const getStoredTheme = () => localStorage.getItem(storageKey);
            const getPreferredTheme = () => {
                const storedTheme = getStoredTheme();
                if (storedTheme) {
                    return storedTheme;
                }

                return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
            };

            const applyTheme = theme => {
                if (theme === 'auto') {
                    const systemPrefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                    document.documentElement.setAttribute('data-bs-theme', systemPrefersDark ? 'dark' : 'light');
                } else {
                    document.documentElement.setAttribute('data-bs-theme', theme);
                }
            };

            applyTheme(getPreferredTheme());

            window.__setPreferredTheme = theme => {
                localStorage.setItem(storageKey, theme);
                applyTheme(theme);
            };

            window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
                const storedTheme = getStoredTheme();
                if (!storedTheme || storedTheme === 'auto') {
                    applyTheme(getPreferredTheme());
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
<body>
<?php include "templates/_navbar.php"; ?>
<div class="container py-4">
    <h1><?= htmlspecialchars($title ?? 'Seite') ?></h1>
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

<!-- Scripts -->
    <script src="<?= htmlspecialchars(url_for('node_modules/jquery/dist/jquery.min.js'), ENT_QUOTES) ?>"></script>
    <script src="<?= htmlspecialchars(url_for('node_modules/bootstrap/dist/js/bootstrap.bundle.min.js'), ENT_QUOTES) ?>"></script>
    <script src="<?= htmlspecialchars(url_for('node_modules/htmx.org/dist/htmx.min.js'), ENT_QUOTES) ?>"></script>
    <script src="<?= htmlspecialchars(url_for('node_modules/tabulator-tables/dist/js/tabulator.min.js'), ENT_QUOTES) ?>"></script>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const themeButtons = document.querySelectorAll('[data-theme-value]');
            const dropdownLabel = document.getElementById('themeDropdownLabel');
            const dropdownIcon = document.getElementById('themeDropdownIcon');
            const storageKey = 'preferred-theme';

            const iconMap = {
                light: 'fa-sun',
                dark: 'fa-moon',
                auto: 'fa-circle-half-stroke'
            };

            const getStoredTheme = () => localStorage.getItem(storageKey) || 'auto';

            const updateActiveState = theme => {
                themeButtons.forEach(button => {
                    button.classList.toggle('active', button.dataset.themeValue === theme);
                });

                if (dropdownLabel) {
                    const labels = {
                        light: 'Hell',
                        dark: 'Dunkel',
                        auto: 'Automatisch'
                    };
                    dropdownLabel.textContent = labels[theme] || 'Automatisch';
                }

                if (dropdownIcon) {
                    dropdownIcon.className = `fas ${iconMap[theme] || iconMap.auto} me-2`;
                }
            };

            const initialTheme = getStoredTheme();
            updateActiveState(initialTheme);

            themeButtons.forEach(button => {
                button.addEventListener('click', () => {
                    const theme = button.dataset.themeValue;
                    window.__setPreferredTheme(theme);
                    updateActiveState(theme);
                });
            });
        });
    </script>

<?php if (!empty($scripts)) echo $scripts; ?>
</body>
</html>
