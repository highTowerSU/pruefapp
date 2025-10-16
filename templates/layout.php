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
<?php include __DIR__ . '/_navbar.php'; ?>
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
<script>
(function() {
  const CONFIRM_CLASS = 'btn-outline-danger';
  document.addEventListener('click', function (event) {
    const button = event.target.closest('[data-double-confirm]');
    if (!button) {
      return;
    }

    const defaultLabel = button.querySelector('[data-label-default]');
    const confirmLabel = button.querySelector('[data-label-confirm]');

    if (button.dataset.armed === 'true') {
      button.dataset.armed = 'false';
      button.classList.remove(CONFIRM_CLASS);
      button.classList.add('btn-danger');
      if (defaultLabel && confirmLabel) {
        confirmLabel.classList.add('d-none');
        defaultLabel.classList.remove('d-none');
      }
      button.dispatchEvent(new CustomEvent('confirmed', { bubbles: true }));
    } else {
      event.preventDefault();
      button.dataset.armed = 'true';
      button.classList.add(CONFIRM_CLASS);
      button.classList.remove('btn-danger');
      if (defaultLabel && confirmLabel) {
        defaultLabel.classList.add('d-none');
        confirmLabel.classList.remove('d-none');
      }
      window.setTimeout(function () {
        if (button.dataset.armed === 'true') {
          button.dataset.armed = 'false';
          button.classList.remove(CONFIRM_CLASS);
          button.classList.add('btn-danger');
          if (defaultLabel && confirmLabel) {
            confirmLabel.classList.add('d-none');
            defaultLabel.classList.remove('d-none');
          }
        }
      }, 3500);
    }
  });
})();
</script>

<?php if (!empty($scripts)) echo $scripts; ?>
</body>
</html>
