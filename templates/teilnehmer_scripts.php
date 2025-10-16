<script>
(() => {
  'use strict';

  const tableElement = document.getElementById('teilnehmer-tabelle');
  const kursId = tableElement?.dataset.kursId;
  const canManage = tableElement?.dataset.canManage === '1';

  if (!kursId) {
    console.error('Kurs-ID nicht gefunden.');
    return;
  }

  const bootstrapLib = window.bootstrap;
  if (!bootstrapLib || typeof bootstrapLib.Popover !== 'function') {
    console.error('Bootstrap Popover konnte nicht geladen werden.');
    return;
  }

  const TabulatorLib = window.Tabulator;
  if (typeof TabulatorLib !== 'function') {
    console.error('Tabulator konnte nicht geladen werden.');
    return;
  }

  const { Popover } = bootstrapLib;
  const apiUrl = <?= json_encode($apiUrl, JSON_UNESCAPED_SLASHES) ?>;

  const tableBootstrapClasses = ['table', 'table-striped', 'table-hover', 'table-sm'];
  tableElement.classList.add(...tableBootstrapClasses);

  const syncTableTheme = () => {
    const theme = document.documentElement.getAttribute('data-bs-theme');
    tableElement.classList.toggle('table-dark', theme === 'dark');
  };

  syncTableTheme();

  const themeObserver = new MutationObserver((mutations) => {
    if (mutations.some(mutation => mutation.type === 'attributes' && mutation.attributeName === 'data-bs-theme')) {
      syncTableTheme();
    }
  });

  themeObserver.observe(document.documentElement, { attributes: true });

  const columns = [
    { title: "Vorname", field: "vorname", editor: canManage ? "input" : false },
    { title: "Nachname", field: "nachname", editor: canManage ? "input" : false },
    { title: "Geburtsdatum", field: "geburtsdatum", editor: canManage ? "input" : false },
    { title: "Geburtsort", field: "geburtsort", editor: canManage ? "input" : false },
    { title: "Benutzername", field: "benutzername" },
    { title: "E-Mail", field: "email" }
  ];

  if (canManage) {
    columns.push({
      title: "Aktion",
      formatter: function(cell) {
        const id = cell.getRow().getData().id;
        return `<button class="btn btn-sm btn-danger btn-popover-confirm" data-id="${id}" data-confirmed="false" data-bs-toggle="popover" data-bs-trigger="manual" data-bs-placement="left" data-bs-content="Wirklich löschen?">
          <i class="fa-solid fa-trash"></i>
        </button>`;
      },
      width: 60,
      hozAlign: "center",
      cellClick: function(e, cell) {
        const button = e.target.closest("button") ?? cell.getElement().querySelector("button");
        if (!button) return;

        const popover = Popover.getOrCreateInstance(button, {
          trigger: "manual",
          container: document.body
        });

        if (button.dataset.confirmed === "true") {
          popover.hide();
          button.dataset.confirmed = "false";
          const id = button.dataset.id;
          fetch(apiUrl + "?delete=" + id, {
            method: "POST"
          }).then(response => {
            if (!response.ok) {
              throw new Error(`HTTP ${response.status}`);
            }

            return null;
          }).then(() => reloadParticipants()).catch(error => {
            console.error("Löschen des Teilnehmers fehlgeschlagen", error);
            showTableError("Teilnehmer konnte nicht gelöscht werden.");
          });
        } else {
          document.querySelectorAll('.btn-popover-confirm[data-confirmed="true"]').forEach(activeButton => {
            if (activeButton === button) {
              return;
            }

            activeButton.dataset.confirmed = "false";
            Popover.getInstance(activeButton)?.hide();
          });

          popover.show();
          button.dataset.confirmed = "true";

          setTimeout(() => {
            if (button.dataset.confirmed !== "true") {
              return;
            }

            button.dataset.confirmed = "false";
            popover.hide();
          }, 3000);
        }
      }
    });
  }

  const tableOptions = {
    layout: "fitColumns",
    placeholder: "Keine Teilnehmer gefunden.",
    columns
  };

  if (canManage) {
    tableOptions.cellEdited = function(cell) {
      const data = cell.getRow().getData();
      fetch(apiUrl, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(data)
      }).then(response => {
        if (!response.ok) {
          throw new Error(`HTTP ${response.status}`);
        }

        return response.json();
      }).then(res => {
        if (res && typeof res === "object") {
          cell.getRow().update(res);
        }
      }).catch(error => {
        console.error("Speichern des Teilnehmers fehlgeschlagen", error);
        showTableError("Änderungen konnten nicht gespeichert werden.");
        reloadParticipants();
      });
    };
  }

  const table = new TabulatorLib('#teilnehmer-tabelle', tableOptions);

  const showTableError = (message) => {
    if (typeof table.alertError === "function") {
      table.alertError(message);
    } else {
      console.error(message);
    }
  };

  const reloadParticipants = () => {
    fetch(apiUrl, {
      headers: { "Accept": "application/json" }
    }).then(response => {
      if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
      }

      return response.json();
    }).then(data => {
      if (!Array.isArray(data)) {
        throw new Error("Antwort ist kein Array");
      }

      table.setData(data);
    }).catch(error => {
      console.error("Teilnehmer konnten nicht geladen werden", error);
      table.setData([]);
      showTableError("Teilnehmer konnten nicht geladen werden.");
    });
  };

  reloadParticipants();

  if (canManage) {
    document.getElementById('btn-add-row')?.addEventListener('click', () => {
      table
        .addRow({}, true)
        .then(row => {
          table.scrollToRow(row, "center", true);
          row.getCell("vorname")?.edit();
        })
        .catch(() => {
          table.alertError?.("Neue Zeile konnte nicht hinzugefügt werden.");
        });
    });
  }

  document.addEventListener('click', (event) => {
    if (event.target.closest('.btn-popover-confirm') || event.target.closest('.popover')) {
      return;
    }

    document.querySelectorAll('.btn-popover-confirm[data-confirmed="true"]').forEach(button => {
      button.dataset.confirmed = "false";
      Popover.getInstance(button)?.hide();
    });
  });
})();
</script>
