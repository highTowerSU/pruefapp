<form method="post" class="mb-4">
    <div class="row g-2">
        <div class="col-md-6">
            <input type="text" name="kursname" class="form-control" placeholder="Neuer Kursname" required>
        </div>
        <div class="col-md-2">
            <button class="btn btn-primary">Kurs anlegen</button>
        </div>
    </div>
</form>

<table class="table table-striped align-middle">
  <thead class="table-dark">
    <tr>
      <th style="width: 30%;">Kursname</th>
      <th class="text-nowrap">Aktionen</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($kurse as $kurs): ?>
      <tr>
        <td><?= htmlspecialchars($kurs->name) ?></td>
        <td class="text-nowrap">
  <?php include 'templates/kurs_buttons.php'; ?>
</td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>

