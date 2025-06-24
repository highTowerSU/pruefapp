<form method="post">
    <h4>Einstellungen für Teilnehmerdateneingabe</h4>

    <div class="form-check mb-2">
        <input type="checkbox" class="form-check-input" id="feld_email" name="feld_email_aktiv"
               <?= $kurs->feld_email_aktiv ? 'checked' : '' ?>>
        <label class="form-check-label" for="feld_email">E-Mail-Adresse abfragen</label>
    </div>

    <div class="form-check mb-3">
        <input type="checkbox" class="form-check-input" id="feld_geburtsort" name="feld_geburtsort_aktiv"
               <?= $kurs->feld_geburtsort_aktiv ? 'checked' : '' ?>>
        <label class="form-check-label" for="feld_geburtsort">Geburtsort abfragen</label>
    </div>

    <button class="btn btn-primary">Speichern</button>
    <a href="index.php" class="btn btn-link">Zurück</a>
</form>
