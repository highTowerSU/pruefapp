<script>
const tableElement = document.getElementById('teilnehmer-tabelle');
const kursId = tableElement?.dataset.kursId;

if (!kursId) {
  throw new Error('Kurs-ID nicht gefunden.');
}

const apiUrl = <?= json_encode($apiUrl, JSON_UNESCAPED_SLASHES) ?>;

const table = new Tabulator('#teilnehmer-tabelle', {
  layout: "fitColumns",
  ajaxURL: apiUrl,
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
          fetch(apiUrl + "?delete=" + id, {
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
    fetch(apiUrl, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(data)
    }).then(r => r.json()).then(res => {
      if (res && typeof res === "object") {
        cell.getRow().update(res);
      }
    });
  }
});

document.getElementById('btn-add-row')?.addEventListener('click', () => {
  table.addRow({});
});
</script>
