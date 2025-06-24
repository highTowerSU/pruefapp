<script>
$(function () {
  const kursId = <?= (int)$kurs ?>;

  const table = new Tabulator("#teilnehmer-tabelle", {
    layout: "fitColumns",
    ajaxURL: "api/teilnehmer.php?kurs=" + kursId,
    ajaxConfig: "GET",
    placeholder: "Keine Teilnehmer gefunden.",
    columns: [
      { title: "Vorname", field: "vorname", editor: "input" },
      { title: "Nachname", field: "nachname", editor: "input" },
      { title: "Geburtsdatum", field: "geburtsdatum", editor: "input" },
      { title: "Benutzername", field: "benutzername" },
      { title: "E-Mail", field: "email" },
      {
        title: "Aktion", width: 60,
        formatter: () =>
          '<button class="btn btn-danger btn-sm btn-popover-confirm" data-bs-toggle="popover" title="Wirklich löschen?" data-confirmed="false"><i class="fa-solid fa-trash"></i></button>',
        cellClick: function (e, cell) {
          const btn = $(cell.getElement()).find('.btn-popover-confirm');
          const confirmed = btn.data("confirmed");

          if (confirmed) {
            const id = cell.getRow().getData().id;
            $.post("api/teilnehmer.php?kurs=" + kursId + "&delete=" + id)
              .done(() => table.replaceData());
          } else {
            btn.popover('show').data("confirmed", true);
            setTimeout(() => {
              btn.data("confirmed", false).popover('hide');
            }, 3000);
          }
        }
      }
    ],
    cellEdited: function (cell) {
      const data = cell.getRow().getData();
      $.ajax({
        url: "api/teilnehmer.php?kurs=" + kursId,
        method: "POST",
        contentType: "application/json",
        data: JSON.stringify(data),
        success: function (res) {
          if (res && res.id) {
            cell.getRow().update({ id: res.id });
          }
        }
      });
    }
  });

  $("<button>")
    .addClass("btn btn-secondary mt-3")
    .text("➕ Teilnehmer hinzufügen")
    .on("click", () => {
      table.addRow({
        vorname: "", nachname: "", geburtsdatum: "", kurs_id: kursId
      }, true);
    })
    .insertAfter("#teilnehmer-tabelle");

  table.on("dataProcessed", function () {
    setTimeout(() => {
      $(".btn-popover-confirm").popover({
        trigger: "manual",
        html: true,
        content: "Wirklich löschen?"
      });
    }, 50);
  });
});
</script>

