<form method="post">
    <div id="eingabe-liste">
        <div class="row g-2 mb-2 person-eintrag">
            <div class="col"><input type="text" name="person[0][vorname]" class="form-control" placeholder="Vorname" required></div>
            <div class="col"><input type="text" name="person[0][nachname]" class="form-control" placeholder="Nachname" required></div>
            <div class="col"><input type="date" name="person[0][geburtsdatum]" class="form-control" required></div>
            <?php if ($kurs->feld_geburtsort_aktiv): ?>
                <div class="col"><input type="text" name="person[0][geburtsort]" class="form-control" placeholder="Geburtsort"></div>
            <?php endif; ?>
            <?php if ($kurs->feld_email_aktiv): ?>
                <div class="col"><input type="email" name="person[0][email]" class="form-control" placeholder="E-Mail"></div>
            <?php endif; ?>
            <div class="col-auto"><button type="button" class="btn btn-danger btn-remove">–</button></div>
        </div>
    </div>

    <button type="button" id="btn-add" class="btn btn-secondary mb-3">+ weitere Person</button><br>
    <button class="btn btn-primary">Daten übermitteln</button>
</form>

<script>
  const feld_geburtsort_aktiv = <?= $kurs->feld_geburtsort_aktiv ? 'true' : 'false' ?>;
  const feld_email_aktiv = <?= $kurs->feld_email_aktiv ? 'true' : 'false' ?>;
let counter = 1;
document.getElementById('btn-add').addEventListener('click', () => {
    let html = `
    <div class="row g-2 mb-2 person-eintrag">
        <div class="col"><input type="text" name="person[${counter}][vorname]" class="form-control" placeholder="Vorname" required></div>
        <div class="col"><input type="text" name="person[${counter}][nachname]" class="form-control" placeholder="Nachname" required></div>
        <div class="col"><input type="date" name="person[${counter}][geburtsdatum]" class="form-control" required></div>`;

    if (feld_geburtsort_aktiv) {
        html += `<div class="col"><input type="text" name="person[${counter}][geburtsort]" class="form-control" placeholder="Geburtsort"></div>`;
    }
    if (feld_email_aktiv) {
        html += `<div class="col"><input type="email" name="person[${counter}][email]" class="form-control" placeholder="E-Mail"></div>`;
    }

    html += `<div class="col-auto"><button type="button" class="btn btn-danger btn-remove">–</button></div></div>`;

    document.getElementById('eingabe-liste').insertAdjacentHTML('beforeend', html);
    counter++;
});

document.addEventListener('click', function (e) {
    if (e.target.classList.contains('btn-remove')) {
        e.target.closest('.person-eintrag').remove();
    }
});
</script>
