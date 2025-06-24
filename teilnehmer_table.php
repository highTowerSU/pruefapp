<div id="teilnehmer-tabelle"></div>

<script>
  const table = new Tabulator("#teilnehmer-tabelle", {
    layout: "fitColumns",
    ajaxURL: "api/teilnehmer.php?kurs=<?= (int)($_GET['kurs'] ?? 0) ?>",
    ajaxConfig: "GET",
    placeholder: "Keine Teilnehmer gefunden.",
    columns: [
      { title: "Vorname", field: "vorname", editor: "input" },
      { title: "Nachname", field: "nachname", editor: "input" },
      { title: "Geburtsdatum", field: "geburtsdatum", editor: "input" },
      { title: "Benutzername", field: "benutzername" },
      { title: "E-Mail", field: "email" },
      {
        title: "Aktion", formatter: "buttonCross", width: 40,
        cellClick: function(e, cell) {
          const id = cell.getRow().getData().id;
          if (confirm("Wirklich löschen?")) {
            fetch("api/teilnehmer.php?delete=" + id, { method: "POST" }).then(() => table.replaceData());
          }
        }
      }
    ]
  });
</script>
