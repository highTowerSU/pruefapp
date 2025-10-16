<div id="kurs-tabelle">
  <?php if (!empty($message)): ?>
    <div class="alert alert-success mb-3"><?= htmlspecialchars($message) ?></div>
  <?php endif; ?>

  <?php if (!empty($error)): ?>
    <div class="alert alert-danger mb-3"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <table class="table table-striped align-middle">
    <thead class="table-dark">
      <tr>
        <th style="width: 30%;">Kursname</th>
        <th class="text-nowrap">Aktionen</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($kurse as $kurs): ?>
        <tr id="kurs-row-<?= (int) $kurs->id ?>">
          <td><?= htmlspecialchars($kurs->name) ?></td>
          <td class="text-nowrap">
            <?php include __DIR__ . '/kurs_buttons.php'; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (empty($kurse)): ?>
        <tr>
          <td colspan="2" class="text-center text-muted">Noch keine Kurse angelegt.</td>
        </tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>
