<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($title ?? 'Seite') ?></title>
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

<?php if (!empty($scripts)) echo $scripts; ?>
</body>
</html>
