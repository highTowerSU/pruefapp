<h4>Manuell Teilnehmer hinzufügen</h4>

<form method="post">
    <div id="manuelle-zeilen">
        <div class="row g-2 mb-2 manuell-eintrag">
            <div class="col"><input type="text" name="manuell[0][vorname]" class="form-control" placeholder="Vorname" required></div>
            <div class="col"><input type="text" name="manuell[0][nachname]" class="form-control" placeholder="Nachname" required></div>
            <div class="col"><input type="date" name="manuell[0][geburtsdatum]" class="form-control" required></div>
            <div class="col"><input type="text" name="manuell[0][geburtsort]" class="form-control" placeholder="Geburtsort"></div>
            <div class="col-auto"><button type="button" class="btn btn-danger btn-remove">–</button></div>
        </div>
    </div>
    <button type="button" id="btn-add" class="btn btn-secondary mb-3">+ weitere Zeile</button><br>
    <button class="btn btn-success">Speichern</button>
</form>

<script>
    let counter = 1;
    document.getElementById('btn-add').addEventListener('click', () => {
        const html = `
        <div class="row g-2 mb-2 manuell-eintrag">
            <div class="col"><input type="text" name="manuell[${counter}][vorname]" class="form-control" placeholder="Vorname" required></div>
            <div class="col"><input type="text" name="manuell[${counter}][nachname]" class="form-control" placeholder="Nachname" required></div>
            <div class="col"><input type="date" name="manuell[${counter}][geburtsdatum]" class="form-control" required></div>
            <div class="col"><input type="text" name="manuell[${counter}][geburtsort]" class="form-control" placeholder="Geburtsort"></div>
            <div class="col-auto"><button type="button" class="btn btn-danger btn-remove">–</button></div>
        </div>`;
        document.getElementById('manuelle-zeilen').insertAdjacentHTML('beforeend', html);
        counter++;
    });

    document.addEventListener('click', function (e) {
        if (e.target.classList.contains('btn-remove')) {
            e.target.closest('.manuell-eintrag').remove();
        }
    });
</script>
