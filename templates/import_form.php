<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

<form method="post" enctype="multipart/form-data" class="mb-4">
    <h4>CSV-Datei hochladen</h4>
    <div class="mb-3">
        <input type="file" name="csv" class="form-control">
        <small class="form-text text-muted">Spalten: Vorname, Nachname, Geburtsdatum, Geburtsort</small>
    </div>

    <hr>

    <h4>Manuelle Eingabe</h4>
    <div id="manuelle-zeilen">
        <div class="row g-2 mb-2 manuell-eintrag">
            <div class="col"><input type="text" name="manuell[0][vorname]" class="form-control" placeholder="Vorname" required></div>
            <div class="col"><input type="text" name="manuell[0][nachname]" class="form-control" placeholder="Nachname" required></div>
            <div class="col"><input type="date" name="manuell[0][geburtsdatum]" class="form-control" required></div>
            <div class="col"><input type="text" name="manuell[0][geburtsort]" class="form-control" placeholder="Geburtsort"></div>
            <div class="col-auto"><button type="button" class="btn btn-danger btn-remove">–</button></div>
        </div>
    </div>

    <button type="button" id="btn-add" class="btn btn-secondary mb-3">+ Zeile hinzufügen</button><br>
    <button class="btn btn-primary">Importieren</button>
    <a href="index.php" class="btn btn-link">Zurück</a>
</form>

<script>
    let counter = 1;
    $('#btn-add').click(function () {
        const row = `
        <div class="row g-2 mb-2 manuell-eintrag">
            <div class="col"><input type="text" name="manuell[${counter}][vorname]" class="form-control" placeholder="Vorname" required></div>
            <div class="col"><input type="text" name="manuell[${counter}][nachname]" class="form-control" placeholder="Nachname" required></div>
            <div class="col"><input type="date" name="manuell[${counter}][geburtsdatum]" class="form-control" required></div>
            <div class="col"><input type="text" name="manuell[${counter}][geburtsort]" class="form-control" placeholder="Geburtsort"></div>
            <div class="col-auto"><button type="button" class="btn btn-danger btn-remove">–</button></div>
        </div>`;
        $('#manuelle-zeilen').append(row);
        counter++;
    });

    $(document).on('click', '.btn-remove', function () {
        $(this).closest('.manuell-eintrag').remove();
    });
</script>
