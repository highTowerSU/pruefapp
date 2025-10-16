<script>
const kursId = new URLSearchParams(window.location.search).get("kurs");

const table = new Tabulator("#teilnehmer-tabelle", {
  layout: "fitColumns",
  ajaxURL: "api/teilnehmer.php?kurs=" + kursId,
  ajaxConfig: "GET",
  placeholder: "Keine Teilnehmer gefunden.",
  columns: [
    { title: "Vorname", field: "vorname", editor: "input" },
    { title: "Nachname", field: "nachname", editor: "input" },
    { title: "Geburtsdatum", field: "geburtsdatum", editor: "input" },
    { title: "Geburtsort", field: "geburtsort", editor: "input" },
    { title: "Benutzername", field: "benutzername" },
    { title: "E-Mail", field: "email" },
    {
      title: "Aktion",
      formatter: function(cell) {
        const id = cell.getRow().getData().id;
        return `<button class="btn btn-sm btn-danger btn-popover-confirm" data-id="${id}" data-confirmed="false" data-bs-toggle="popover" title="Wirklich löschen?">
          <i class="fa-solid fa-trash"></i>
        </button>`;
      },
      width: 60,
      hozAlign: "center",
      cellClick: function(e, cell) {
        const button = e.target.closest("button");
        if (!button) return;

        if (button.dataset.confirmed === "true") {
          const id = button.dataset.id;
          fetch("api/teilnehmer.php?kurs=" + kursId + "&delete=" + id, {
            method: "POST"
          }).then(() => table.replaceData());
        } else {
          button.dataset.confirmed = "true";
          button.setAttribute("title", "Nochmal klicken zum Löschen");
          setTimeout(() => {
            button.dataset.confirmed = "false";
            button.setAttribute("title", "Wirklich löschen?");
          }, 3000);
        }
      }
    }
  ],
  cellEdited: function(cell) {
    const data = cell.getRow().getData();
    fetch("api/teilnehmer.php?kurs=" + kursId, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(data)
    }).then(r => r.json()).then(res => {
      if (res.id) cell.getRow().update({ id: res.id });
    });
  }
});

document.getElementById('btn-add-row')?.addEventListener('click', () => {
  table.addRow({});
});
</script>
