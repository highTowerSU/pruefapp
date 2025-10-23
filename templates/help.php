<section class="mb-5">
  <h2 class="h4">Über diese Anleitung</h2>
  <p>
    Diese Seite fasst den vollständigen Ablauf zur Vorbereitung eines Moodle-Kurses zusammen.
    Sie begleitet dich von der Kurskopie über den Teilnehmerimport bis hin zur Ausgabe der Zugangsdaten
    und der organisatorischen Packliste. Alle Schritte beziehen sich auf die Funktionen dieser Plattform
    und die Abläufe auf Moodle selbst.
  </p>
</section>

<section class="mb-5">
  <h2 class="h4">Kurs vorbereiten</h2>
  <ol class="ps-3">
    <li>
      <strong>Kurs kopieren:</strong>
      Auf der Moodle-Plattform den bestehenden Kurs auswählen und über die Kurs-Einstellungen kopieren.
      Die Kopie bildet die Grundlage für den neuen Durchlauf.
    </li>
    <li>
      <strong>Kursdatum und Fristen anpassen:</strong>
      Im neuen Kurs unter <em>Einstellungen</em> das Kursbeginn- und Kursendedatum sowie sämtliche relevanten
      Fälligkeiten (Vortest, Test, Fragebögen usw.) auf den neuen Kurszeitraum einstellen.
    </li>
    <li>
      <strong>Dozent:innen aktualisieren:</strong>
      Ebenfalls in den Einstellungen die Felder für Dozent:innen – inklusive Themenreihenfolge, Zertifikats- und Testzuordnungen –
      auf den aktuellen Lehrendenstand bringen. Prüfe auch das Feld darüber, damit alle verantwortlichen Personen korrekt
      hinterlegt sind.
    </li>
  </ol>
</section>

<section class="mb-5">
  <h2 class="h4">Teilnehmerliste vorbereiten</h2>
  <p>
    Erstelle oder überarbeite die CSV-Liste der Teilnehmenden. Die Datei muss alle Felder enthalten, die Moodle für den Import
    benötigt. Nutze bei Bedarf die bereitgestellte Beispieldatei als Vorlage.
  </p>
  <div class="alert alert-info">
    <strong>Hinweis:</strong> Geburtsdatum, Geburtsort und Passwort sind Pflichtfelder. Für sichere Passwörter empfiehlt sich
    die Generierung über VaultWarden oder ein vergleichbares Tool.
  </div>
  <p>
    Beispieldatei herunterladen:
    <a href="<?= htmlspecialchars(url_for('zugangsdaten_beispiel.csv'), ENT_QUOTES) ?>" download>
      Zugangsdaten-Beispiel.csv
    </a>
  </p>
</section>

<section class="mb-5">
  <h2 class="h4">Nutzer in Moodle importieren</h2>
  <ol class="ps-3">
    <li>Kurs öffnen und <em>Teilnehmer/innen</em> auswählen.</li>
    <li>
      Über das Zahnrad-Menü rechts oben <em>Teilnehmer/innen hochladen</em> wählen und die vorbereitete CSV-Datei hochladen.
    </li>
    <li>
      Im Importformular prüfen, ob das Trennzeichen korrekt erkannt wurde (in der Regel Komma) und das Feld
      <em>Passwort</em> aktiviert ist.
    </li>
    <li>
      Die Option <em>Nutzer automatisch anlegen</em> aktivieren und den Import abschließen.
    </li>
  </ol>
  <div class="alert alert-info">
    <strong>Moodle-Namensschilder:</strong> Nach dem Import werden die Namensschilder im Kurs automatisch erzeugt.
    Du kannst sie direkt aus Moodle herunterladen und ausdrucken.
  </div>
</section>

<section class="mb-5">
  <h2 class="h4">Zugangsdatenkarten erstellen</h2>
  <ol class="ps-3">
    <li>
      Die identische CSV-Datei auf der Seite <em>Zugangsdaten erzeugen</em> hochladen. Ergänze dafür eine zusätzliche Spalte
      <code>Nutzer!</code>, damit die Karten personalisiert ausgegeben werden.
    </li>
    <li>
      Sicherstellen, dass die Datei weiterhin das Komma als Trennzeichen verwendet.
    </li>
    <li>
      Die erzeugten Karten herunterladen, ausdrucken und für die Kursteilnehmenden vorbereiten.
    </li>
    <li>
      Zugangsdaten stichprobenartig testen, um sicherzustellen, dass Anmeldung und Moodle-Zugriff funktionieren.
    </li>
  </ol>
</section>

<section class="mb-5">
  <h2 class="h4">Letzte Checks vor Kursbeginn</h2>
  <ul class="ps-3">
    <li>Nutzerliste in Moodle gegenprüfen: Sind alle Teilnehmenden vorhanden?</li>
    <li>Namensschilder und Zugangsdatenzettel bereit legen.</li>
    <li>Notwendige Tests (inkl. Test&nbsp;3 für Nachprüfungen) sowie Antwortbögen ausdrucken.</li>
    <li>Anwesenheitsliste und Zeitplan bereithalten.</li>
    <li>Laptops, Hotspot, Mehrfachsteckdosen und weiteres technisches Equipment packen.</li>
    <li>Beamer, Stativ, Kabelroller sowie Unterlagen für praktische Übungen einpacken.</li>
    <li>Interventionsberichte und sonstige Vorlagen vorbereiten.</li>
  </ul>
</section>

<section class="mb-5">
  <h2 class="h4">Packliste (Kurzfassung)</h2>
  <ul class="ps-3">
    <li>Interventionsberichte</li>
    <li>Vorlagen praktische Übungen</li>
    <li>Namensschilder</li>
    <li>Anwesenheitsliste</li>
    <li>Zeitpläne</li>
    <li>Test 3 für manuelle Nachprüfungen</li>
    <li>Antwortbögen</li>
    <li>Zugangsdatenzettel</li>
    <li>Laptops, Hotspot, Mehrfachsteckdosen u. Ä.</li>
    <li>Laptop, Beamer, Ständer, Kabelroller</li>
  </ul>
</section>

<section class="mb-5">
  <h2 class="h4">Tipps für den Kursstart</h2>
  <ul class="ps-3">
    <li>Zugangsdaten rechtzeitig verteilen und bei Bedarf digitale Backups bereithalten.</li>
    <li>Mindestens einen Moodle-Login vor Ort testen, bevor die Teilnehmenden starten.</li>
    <li>Alle Dozent:innen über den aktualisierten Kursablauf informieren.</li>
    <li>Bei Änderungen im Ablauf die Packliste direkt in diesem Kurs pflegen, damit sie aktuell bleibt.</li>
  </ul>
</section>
