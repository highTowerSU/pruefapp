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
          <td>
            <div><?= htmlspecialchars($kurs->name) ?></div>
            <?php if (!empty($kurs->moodle_course_shortname ?? '')): ?>
              <div class="small text-muted">
                Moodle: <code><?= htmlspecialchars($kurs->moodle_course_shortname, ENT_QUOTES) ?></code>
                <?php if (!empty($kurs->moodle_course_fullname ?? '') && $kurs->moodle_course_fullname !== $kurs->name): ?>
                  · <?= htmlspecialchars($kurs->moodle_course_fullname, ENT_QUOTES) ?>
                <?php endif; ?>
                <?php if (!empty($kurs->moodle_template_shortname ?? '')): ?>
                  (Vorlage: <code><?= htmlspecialchars($kurs->moodle_template_shortname, ENT_QUOTES) ?></code>)
                <?php endif; ?>
              </div>
            <?php endif; ?>
            <?php if (!empty($kurs->auftraggeber ?? [])): ?>
              <div class="small text-muted">
                Auftraggeber:
                <?php foreach ($kurs->auftraggeber as $index => $firma): ?>
                  <?php if ($index > 0): ?> · <?php endif; ?>
                  <?= htmlspecialchars($firma, ENT_QUOTES) ?>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </td>
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
