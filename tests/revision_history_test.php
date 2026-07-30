<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Ceneos\PhpBase\Audit\RevisionHistory;
use Ceneos\PhpBase\Audit\AuditTrailRepository;
use Ceneos\PhpBase\Database\RevisionSupport;
use RedBeanPHP\R;

R::setup('sqlite::memory:');

$emptyAudit = (new AuditTrailRepository())->paginateEvents();
if ($emptyAudit['entries'] !== [] || $emptyAudit['pagination']['total_entries'] !== 0) {
    throw new RuntimeException('Ein frisches System muss ein leeres Ereignisprotokoll liefern.');
}

$tenant = R::dispense('company');
$tenant->name = 'Revisionsmandant';
$tenant->client_secret = 'nicht-ausgeben';
R::store($tenant);

R::exec(
    'CREATE TABLE revisioncompany (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        action TEXT NOT NULL,
        lastedit TEXT NOT NULL,
        original_id INTEGER NOT NULL,
        name TEXT,
        client_secret TEXT
    )'
);
R::exec(
    "INSERT INTO revisioncompany
        (action, lastedit, original_id, name, client_secret)
     VALUES ('update', datetime('now'), ?, ?, ?)",
    [$tenant->id, $tenant->name, $tenant->client_secret]
);

RevisionSupport::enableFor(['company']);
if (R::getCell('SELECT client_secret FROM revisioncompany LIMIT 1') !== null) {
    throw new RuntimeException('Historische Secrets wurden nicht aus der Revision entfernt.');
}
R::exec('DELETE FROM revisioncompany');

$tenant->name = 'Geänderter Mandant';
R::store($tenant);
R::trash($tenant);

$revisions = (new RevisionHistory())->latest(RevisionSupport::enabledTables());
if (count($revisions) !== 2) {
    throw new RuntimeException('Update und Löschung wurden nicht revisioniert.');
}

if (array_column($revisions, 'action') !== ['delete', 'update']) {
    throw new RuntimeException('Die ReBean-Aktionen haben nicht die erwartete Reihenfolge.');
}

if ((int) R::getCell('SELECT COUNT(*) FROM revisioncompany WHERE client_secret IS NOT NULL') !== 0) {
    throw new RuntimeException('Sensible Werte dürfen nicht in Revisionen gespeichert werden.');
}

R::close();
