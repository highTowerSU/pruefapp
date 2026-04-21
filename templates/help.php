<section class="mb-5">
  <h2 class="h4">Über diese Anleitung</h2>
  <p>
    Diese Seite beschreibt den Zielprozess für die Prüf-Doku-App. Der aktuelle Schwerpunkt liegt auf
    Elektroprüfungen nach DGUV Vorschrift 3. Die bestehende Anmeldung über <strong>Login.Koenigsbl.au</strong>
    bleibt dabei unverändert bestehen.
  </p>
</section>

<section class="mb-5">
  <h2 class="h4">Standardablauf für DGUV-V3-Prüfungen</h2>
  <ol class="ps-3">
    <li>
      <strong>Prüfauftrag anlegen:</strong>
      Lege einen neuen Auftrag an und hinterlege Standort, Firma, Prüfumfang sowie den geplanten Zeitraum.
    </li>
    <li>
      <strong>Prüfobjekte erfassen:</strong>
      Importiere Objekte per CSV oder erfasse sie manuell. Achte auf eindeutige Bezeichnungen, Inventarnummern
      und den zugehörigen Einsatzbereich.
    </li>
    <li>
      <strong>Prüfung dokumentieren:</strong>
      Erfasse Messwerte, Sichtprüfung, Funktionsprüfung und das Ergebnis je Objekt nachvollziehbar.
    </li>
    <li>
      <strong>Abschluss und Übergabe:</strong>
      Erzeuge Listen, Protokolle und Übergabeunterlagen für Auftraggeber, interne Ablage und Nachkontrollen.
    </li>
  </ol>
</section>

<section class="mb-5">
  <h2 class="h4">CSV-Import vorbereiten</h2>
  <p>
    Für strukturierte Importe kann weiterhin eine CSV-Datei genutzt werden. Verwende pro Zeile ein Prüfobjekt
    und halte einheitliche Feldnamen ein. So bleibt der Import reproduzierbar.
  </p>
  <p>
    Beispieldatei herunterladen:
    <a href="<?= htmlspecialchars(url_for('zugangsdaten_beispiel.csv'), ENT_QUOTES) ?>" download>
      Zugangsdaten-Beispiel.csv
    </a>
  </p>
</section>

<section class="mb-5">
  <h2 class="h4">Roadmap: Erweiterung auf weitere Prüfkategorien</h2>
  <p>
    Die App ist so vorbereitet, dass nach den Elektroprüfungen weitere Prüftypen ergänzt werden können, z. B.:
  </p>
  <ul class="ps-3">
    <li>Leitern und Tritte</li>
    <li>ortsveränderliche Arbeitsmittel</li>
    <li>wiederkehrende Sicht- und Funktionsprüfungen</li>
  </ul>
  <div class="alert alert-info mb-0">
    Empfehlung: Neue Prüfkategorien schrittweise einführen und pro Kategorie klare Pflichtfelder definieren,
    damit Auswertungen und Prüfberichte konsistent bleiben.
  </div>
</section>
