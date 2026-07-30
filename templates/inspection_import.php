<div class="card">
  <div class="card-body">
    <h1 class="h4">Elektro-Prüfungen importieren</h1>
    <p class="text-body-secondary">JSON-Dateien aus den Vorjahren sowie CSV/ODS-Paare aus 25/26 werden zusammengeführt. CSV und ODS müssen im gleichen Ordner liegen; der Join erfolgt über Speicher Nr. und Speicherplatz.</p>
    <?php if ($message !== null): ?><div class="alert alert-info"><?= htmlspecialchars($message) ?></div><?php endif; ?>
    <form method="post" class="row g-2">
      <div class="col-12"><label class="form-label" for="directory">Verzeichnis</label><input class="form-control" id="directory" name="directory" required placeholder="/home/codexuser/Elektro-Testdaten"></div>
      <div class="col-12"><button class="btn btn-primary">Import starten</button></div>
    </form>
    <hr>
    <h2 class="h6">Aktuelle Prüfung als Paar hochladen</h2>
    <p class="text-body-secondary">CSV und ODS müssen denselben Dateinamen haben, zum Beispiel <code>AK_Elektro-26_04_02.csv</code> und <code>AK_Elektro-26_04_02.ods</code>.</p>
    <form method="post" enctype="multipart/form-data" class="row g-2">
      <div class="col-md-6"><label class="form-label" for="csv">Messdaten (CSV)</label><input class="form-control" id="csv" name="csv" type="file" accept=".csv" required></div>
      <div class="col-md-6"><label class="form-label" for="ods">Gerätedaten (ODS)</label><input class="form-control" id="ods" name="ods" type="file" accept=".ods" required></div>
      <div class="col-12"><button class="btn btn-primary">Paar importieren</button></div>
    </form>
    <?php if (is_array($stats)): ?><hr><pre class="mb-0"><?= htmlspecialchars((string) json_encode($stats, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre><?php endif; ?>
    <?php if (is_array($stats) && !empty($stats['new_devices'])): ?><details class="mt-3"><summary>Neu angelegte Geräte (<?= count($stats['new_devices']) ?>)</summary><ul class="mt-2"><?php foreach ($stats['new_devices'] as $newDevice): ?><li><a href="<?= htmlspecialchars(url_for('geraete?device_id=' . (int) $newDevice['id']), ENT_QUOTES) ?>"><?= htmlspecialchars((string) $newDevice['number']) ?> · <?= htmlspecialchars((string) $newDevice['name']) ?></a></li><?php endforeach; ?></ul></details><?php endif; ?>
  </div>
</div>
