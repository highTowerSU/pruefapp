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
  <h2 class="h4">Teilnehmerlinks und CSV-Import</h2>
  <p>
    Lege für jeden Kurs einen oder mehrere Upload-Links an. Ordne die Links den Firmen zu und schicke ihnen den jeweiligen Link.
    Die Firmen können darüber die Teilnehmenden importieren oder die fertigen CSV-Dateien zurücksenden.
  </p>
  <p>
    Für den Upload wird weiterhin eine CSV-Datei benötigt. Sie muss alle Pflichtfelder enthalten, die Moodle erwartet – unter
    anderem Geburtsdatum, Geburtsort und Passwort. Nutze bei Bedarf die bereitgestellte Beispieldatei als Vorlage.
  </p>
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
    <li>Anwesenheitsliste, Zeitplan sowie eventuelle Tests oder Feedbackbögen ausdrucken.</li>
    <li>Laptops, Hotspot, Mehrfachsteckdosen und weiteres technisches Equipment packen.</li>
    <li>Beamer, Stativ, Kabel und Materialien für Kursaktivitäten vorbereiten.</li>
  </ul>
</section>

<section class="mb-5">
  <h2 class="h4">Packliste (Kurzfassung)</h2>
  <ul class="ps-3">
    <li>Namensschilder und Zugangsdatenzettel</li>
    <li>Anwesenheitsliste und Zeitpläne</li>
    <li>Materialien für Übungen und Feedback</li>
    <li>Laptops, Hotspot, Mehrfachsteckdosen u. Ä.</li>
    <li>Laptop, Beamer, Ständer, Kabel und Adapter</li>
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
