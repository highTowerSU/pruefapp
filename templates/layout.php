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

    <?= $content ?>
</div>

<template id="popover-template">
  <form method="post" action="kurs_loeschen.php" class="m-0">
    <input type="hidden" name="kurs_id" value="__KURS_ID__">
    <button type="submit" class="btn btn-sm btn-danger mt-1">
      <i class="fa-solid fa-check"></i> Bestätigen
    </button>
  </form>
</template>

<!-- Scripts -->
<script src="node_modules/jquery/dist/jquery.min.js"></script>
<script src="node_modules/bootstrap/dist/js/bootstrap.bundle.min.js"></script>
<script src="node_modules/htmx.org/dist/htmx.min.js"></script>
<!--<script src="node_modules/tabulator-tables/dist/js/tabulator.min.js"></script>-->


<script>
$(function() {
  const template = document.getElementById('popover-template').innerHTML;
  const buttons = document.querySelectorAll('[data-bs-toggle="popover"]');

  buttons.forEach(button => {
    const kursId = button.dataset.kursId;
    const html = template.replace('__KURS_ID__', kursId);
    new bootstrap.Popover(button, {
      html: true,
      trigger: 'focus',
      content: html
    });
  });

  $(".btn-popover-confirm").popover();

  $(".btn-popover-confirm").on("click", function () {
    const $btn = $(this);
    const confirmed = $btn.data("confirmed");

    if (confirmed) {
      const kursId = $btn.data("kurs-id");
      const teilnehmerId = $btn.data("teilnehmer-id");

      if (kursId) {
        window.location.href = "index.php?delete_kurs=" + kursId;
      } else if (teilnehmerId) {
        window.location.href = "teilnehmer_loeschen.php?id=" + teilnehmerId;
      }

    } else {
      $btn.data("confirmed", true);
      setTimeout(() => {
        $btn.data("confirmed", false);
        $btn.popover('hide');
      }, 3000);
    }
  });
});

</script>
<?php if (!empty($scripts)) echo $scripts; ?>
</body>
</html>
