<nav class="navbar navbar-expand-lg navbar-navy mb-4">
  <div class="container-fluid">
    <a class="navbar-brand" href="index.php">Moodle-Zugang</a>
    <div class="d-flex ms-auto">
      <?php if (isset($_SESSION['user'])): ?>
        <span class="navbar-text me-3">
          Eingeloggt als <strong><?= htmlspecialchars($_SESSION['user']->preferred_username ?? 'Nutzer') ?></strong>
        </span>
        <a href="logout.php" class="btn btn-logout">Logout</a>
      <?php endif; ?>
    </div>
  </div>
</nav>
