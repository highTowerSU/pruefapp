<div class="d-flex flex-wrap gap-2">
  <a href="import.php?kurs=<?= $kurs->id ?>" class="btn btn-sm btn-success">
    <i class="fa-solid fa-file-import"></i> Import
  </a>
  <a href="teilnehmer.php?kurs=<?= $kurs->id ?>" class="btn btn-sm btn-primary">
    <i class="fa-solid fa-users"></i> Teilnehmer
  </a>
  <a href="kurseinstellungen.php?kurs=<?= $kurs->id ?>" class="btn btn-sm btn-secondary">
    <i class="fa-solid fa-gear"></i> Einstellungen
  </a>
  <a href="link_erzeugen.php?kurs=<?= $kurs->id ?>" class="btn btn-sm btn-info">
    <i class="fa-solid fa-link"></i> Link
  </a>
  <button type="button"
          class="btn btn-sm btn-danger"
          data-double-confirm
          hx-delete="/kurse/<?= $kurs->id ?>"
          hx-target="#kurs-tabelle"
          hx-swap="outerHTML"
          hx-trigger="confirmed">
    <span data-label-default>
      <i class="fa-solid fa-trash"></i> Löschen
    </span>
    <span data-label-confirm class="d-none">
      <i class="fa-solid fa-circle-exclamation"></i> Nochmal klicken
    </span>
  </button>
</div>
