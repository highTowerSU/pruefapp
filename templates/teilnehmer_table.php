<div class="mb-3">
    <a href="druck.php?kurs=<?= $kurs->id ?>" class="btn btn-primary">Zugangsdaten drucken</a>
    <a href="export.php?kurs=<?= $kurs->id ?>" class="btn btn-secondary">CSV für Moodle exportieren</a>
    <a href="import_csv.php?kurs=<?= $kurs->id ?>" class="btn btn-outline-dark">CSV hochladen</a>
</div>

<?php if (empty($nutzer)): ?>
    <div class="alert alert-warning">Noch keine Teilnehmer.</div>
<?php else: ?>
    <table class="table table-bordered table-sm tablesorter">
        <thead>
        <tr>
            <th>Vorname</th><th>Nachname</th><th>Geburtsdatum</th><th>Geburtsort</th><th>Benutzername</th><th>Passwort</th><th>E-Mail</th><th>Aktion</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($nutzer as $n): ?>
            <?= render_template('user_row.php', ['user' => $n]) ?>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
