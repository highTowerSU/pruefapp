<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($title ?? 'Seite') ?></title>
    <!-- Styles -->
<link rel="stylesheet" href="node_modules/bootstrap/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="node_modules/tabulator-tables/dist/css/tabulator_bootstrap5.min.css">
<link rel="stylesheet" href="node_modules/@fortawesome/fontawesome-free/css/all.min.css">
<link rel="stylesheet" href="public/css/custom.css">


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
<script src="node_modules/jquery/dist/jquery.min.js"></script>
<script src="node_modules/bootstrap/dist/js/bootstrap.bundle.min.js"></script>
<script src="node_modules/htmx.org/dist/htmx.min.js"></script>
<!--<script src="node_modules/tabulator-tables/dist/js/tabulator.min.js"></script>-->

<?php if (!empty($scripts)) echo $scripts; ?>
</body>
</html>
