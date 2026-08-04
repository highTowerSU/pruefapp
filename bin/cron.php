#!/usr/bin/env php
<?php
declare(strict_types=1);

// Zentraler Prüfapp-Cron-Einstiegspunkt. Die bisherige Datei bleibt als
// kompatibler Alias erhalten, damit bestehende Cron-Einträge weiterlaufen.
require __DIR__ . '/phoenix_sync_cron.php';
