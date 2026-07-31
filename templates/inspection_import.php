<div class="card">
  <div class="card-body">
    <h1 class="h4">Elektro-Prüfungen importieren</h1>
    <?php if (!empty($cron['healthy'])): ?><div class="alert alert-success py-2">Cron letzter Lauf: <?= htmlspecialchars((string) $cron['last_run']) ?></div><?php else: ?><div class="alert alert-warning py-2">Warnung: Der Phoenix-Cron wurde seit mehr als 5 Minuten nicht ausgeführt<?= !empty($cron['last_run']) ? ' (letzter Lauf: ' . htmlspecialchars((string) $cron['last_run']) . ')' : '' ?>.</div><?php endif; ?>
    <?php if (!empty($jobs)): ?><details class="mb-3" open><summary><strong>Import-Jobs</strong> (<?= count($jobs) ?>)</summary><div class="table-responsive mt-2"><table class="table table-sm"><thead><tr><th>Status</th><th>Kunde</th><th>Fortschritt</th><th>Gerät</th><th>Details</th><th></th></tr></thead><tbody><?php foreach ($jobs as $job): $jobId=(string)($job['id']??''); ?><tr data-phoenix-job="<?= htmlspecialchars($jobId, ENT_QUOTES) ?>"><td class="job-state"><?= htmlspecialchars((string) ($job['state'] ?? '')) ?></td><td><?= htmlspecialchars((string) ($job['customer_id'] ?? '')) ?></td><td class="job-progress"><?php if (($job['total'] ?? 0) > 0): ?><?= (int) ($job['step'] ?? 0) ?> / <?= (int) $job['total'] ?><?php else: ?>—<?php endif; ?></td><td class="job-device"><?= htmlspecialchars((string) ($job['current_device'] ?? '')) ?></td><td><details><summary>anzeigen</summary><pre class="small mb-0 job-details"><?= htmlspecialchars((string) json_encode($job['stats'] ?? ($job['error'] ?? $job['message'] ?? ''), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre></details></td><td><?php if (in_array(($job['state'] ?? ''), ['queued', 'running'], true)): ?><form method="post" action="<?= htmlspecialchars(url_for('admin/pruefungen/import/' . rawurlencode($jobId) . '/abbrechen'), ENT_QUOTES) ?>" onsubmit="return confirm('Diesen Import wirklich abbrechen? Bereits importierte Datensätze bleiben erhalten.');"><button class="btn btn-sm btn-outline-danger">Abbrechen</button></form><?php endif; ?></td></tr><?php endforeach; ?></tbody></table></div></details><?php endif; ?>
    <?php if (!empty($importLogs)): ?><details class="mb-3"><summary><strong>Letzte Importe</strong> (<?= count($importLogs) ?>)</summary><div class="mt-2"><?php foreach ($importLogs as $log): ?><details class="border rounded p-2 mb-2"><summary><?= htmlspecialchars((string) ($log['type'] ?? 'Import')) ?> · <?= htmlspecialchars((string) ($log['created_at'] ?? '')) ?></summary><pre class="small mb-0 mt-2"><?= htmlspecialchars((string) json_encode($log['stats'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre></details><?php endforeach; ?></div></details><?php endif; ?>
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
    <hr>
    <h2 class="h6">Phoenix-Einweg-Sync (nur neue Geräte)</h2>
    <p class="text-body-secondary">Lädt Prüfungen eines Phoenix-Kunden über die API und übernimmt ausschließlich Gerätenummern, die lokal noch nicht existieren. Der Bearer-Token wird nicht gespeichert.</p>
    <form method="post" class="row g-2">
      <input type="hidden" name="action" value="phoenix_sync">
      <div class="col-md-3"><label class="form-label" for="phoenix_customer_id">Phoenix-Kunden-ID</label><input class="form-control" id="phoenix_customer_id" name="phoenix_customer_id" inputmode="numeric" required></div>
      <div class="col-md-5"><label class="form-label" for="phoenix_token">Bearer-Token</label><input class="form-control" id="phoenix_token" name="phoenix_token" type="password" autocomplete="off" required></div>
      <div class="col-md-4"><label class="form-label" for="phoenix_api_url">API-URL</label><input class="form-control" id="phoenix_api_url" name="phoenix_api_url" value="https://api.phoenix-arbeitswelt.de/phoenix"></div>
      <div class="col-12"><button class="btn btn-outline-primary">Neue Phoenix-Geräte synchronisieren</button></div>
    </form>
    <?php if (is_array($stats)): ?><hr><pre class="mb-0"><?= htmlspecialchars((string) json_encode($stats, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre><?php endif; ?>
    <?php if (is_array($stats) && !empty($stats['new_devices'])): ?><details class="mt-3"><summary>Neu angelegte Geräte (<?= count($stats['new_devices']) ?>)</summary><ul class="mt-2"><?php foreach ($stats['new_devices'] as $newDevice): ?><li><a href="<?= htmlspecialchars(url_for('geraete?device_id=' . (int) $newDevice['id']), ENT_QUOTES) ?>"><?= htmlspecialchars((string) $newDevice['number']) ?> · <?= htmlspecialchars((string) $newDevice['name']) ?></a></li><?php endforeach; ?></ul></details><?php endif; ?>
    <?php if (is_array($stats) && !empty($stats['not_imported'])): ?><details class="mt-3"><summary>Nicht importiert (<?= count($stats['not_imported']) ?>)</summary><div class="table-responsive mt-2"><table class="table table-sm"><thead><tr><th>Speicherplatz</th><th>Quelle</th><th>Grund</th></tr></thead><tbody><?php foreach ($stats['not_imported'] as $entry): ?><tr><td><?= htmlspecialchars((string) ($entry['storage_slot'] ?? '')) ?></td><td><?= htmlspecialchars((string) ($entry['source'] ?? '')) ?></td><td><?= htmlspecialchars((string) ($entry['reason'] ?? '')) ?></td></tr><?php endforeach; ?></tbody></table></div></details><?php endif; ?>
  </div>
</div>
<?php if (!empty($_GET['phoenix_job'])): ?><script>
(function(){const id=<?= json_encode((string)$_GET['phoenix_job']) ?>; const url=<?= json_encode(url_for('admin/pruefungen/import/'.rawurlencode((string)$_GET['phoenix_job']).'/status')) ?>; const poll=()=>fetch(url,{credentials:'same-origin'}).then(r=>r.json()).then(j=>{const row=document.querySelector('[data-phoenix-job="'+id+'"]');if(!row)return;row.querySelector('.job-state').textContent=j.state||'';row.querySelector('.job-progress').textContent=j.total?(j.step||0)+' / '+j.total:'—';row.querySelector('.job-device').textContent=j.current_device||'';if(j.state==='running'||j.state==='queued')setTimeout(poll,2000);});poll();})();
</script><?php endif; ?>
