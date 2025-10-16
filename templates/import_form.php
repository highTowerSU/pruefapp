<form method="post" enctype="multipart/form-data" class="mb-4">
    <h4>CSV-Datei hochladen</h4>
    <div class="mb-3">
        <input type="file" name="csv" class="form-control">
        <small class="form-text text-muted">Nach dem Upload können Sie die Spalten den Moodle-Feldern zuordnen.</small>
    </div>

    <p class="text-muted">Die Teilnehmerliste wird vollständig über die CSV-Datei importiert.</p>
    <button class="btn btn-primary">Importieren</button>
    <a href="<?= htmlspecialchars(url_for('kurse/' . (int) $kurs->id . '/teilnehmer'), ENT_QUOTES) ?>" class="btn btn-link">Zurück</a>
</form>
