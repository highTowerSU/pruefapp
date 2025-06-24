<?php
$felder = ['Vorname', 'Nachname', 'Geburtsdatum', 'Geburtsort', 'Benutzername', 'Passwort', 'E-Mail'];
ob_start();
?>
<form method="post" action="import_process.php?kurs=<?= (int)($_GET['kurs'] ?? 0) ?>">
    <?php foreach ($felder as $feld): ?>
        <div class="mb-3">
            <label><?= $feld ?>:</label>
            <select name="map_<?= $feld ?>" class="form-select">
                <option value="">Nicht zuordnen</option>
                <?php foreach ($header as $spalte): ?>
                    <option value="<?= htmlspecialchars($spalte) ?>"><?= htmlspecialchars($spalte) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    <?php endforeach; ?>
    <button class="btn btn-success">Import starten</button>
</form>
<?php
return ob_get_clean();
