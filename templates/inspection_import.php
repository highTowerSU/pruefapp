<div class="card">
  <div class="card-body">
    <h1 class="h4">Elektro-Prüfungen importieren</h1>
    <p class="text-body-secondary">JSON-Dateien aus den Vorjahren sowie CSV/ODS-Paare aus 25/26 werden zusammengeführt. CSV und ODS müssen im gleichen Ordner liegen; der Join erfolgt über Speicher Nr. und Speicherplatz.</p>
    <?php if ($message !== null): ?><div class="alert alert-info"><?= htmlspecialchars($message) ?></div><?php endif; ?>
    <form method="post" class="row g-2">
      <div class="col-12"><label class="form-label" for="directory">Verzeichnis</label><input class="form-control" id="directory" name="directory" required placeholder="/home/codexuser/Elektro-Testdaten"></div>
      <div class="col-12"><button class="btn btn-primary">Import starten</button></div>
    </form>
    <?php if (is_array($stats)): ?><hr><pre class="mb-0"><?= htmlspecialchars((string) json_encode($stats, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre><?php endif; ?>
  </div>
</div>
