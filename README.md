# Prüf-Doku App

Softwareprojekt der CENEOS GmbH – entwickelt für die Firmengruppe Koenigsbl.au.

Dieses Projekt stellt eine Verwaltungsoberfläche für die Dokumentation von Prüfungen bereit. Der aktuelle Schwerpunkt liegt auf Elektroprüfungen nach DGUV Vorschrift 3. Die Anwendung basiert auf PHP, nutzt RedBeanPHP als ORM und bindet weiterhin ein Keycloak/OpenID-Connect-Login ein (u. a. `login.koenigsbl.au`). Eine frühere Moodle-Integration wurde entfernt.

## Features

- Anmeldung per OpenID Connect (Keycloak)
- Verwaltung von Prüfaufträgen inklusive Einstellungen und Verknüpfungen
- Import, Export und Druck von Listen (z. B. Prüfobjekte/Teilnehmende)
- Optionale Erfassung der zugehörigen Firma für Datensätze
- Wiederverwendbare Datenflüsse für strukturierte Dokumentationsprozesse
- HTMX-basierte Oberfläche mit Bootstrap-Layout und Tabulator-Tabellen
- Zentrale Hilfe- und Ablaufseite für den Prüfprozess
- Prüfberichte als A4-Writer-Dokumente mit Mandantenbranding, festen
  Tabellenbreiten, Prüfschritten, Ergebnismarkierung und Unterschriftsbereich
- Persönliche Prüfbericht-Unterschrift im lokalen Benutzerprofil; historische
  Phoenix-Unterschriften bleiben bei Altberichten vorrangig erhalten
- Erst- und Folgeunterweisungen werden im Benutzerprofil mit Datum, Thema und
  internen Notizen dokumentiert
- Hintergrundaufgaben können aus der Audit-Ansicht abgebrochen werden; laufende
  Migrationen bleiben bewusst geschützt
- Fertige Exporte stehen in einer persönlichen Downloadübersicht und im
  Benachrichtigungsmenü der Navigation bereit
- Alle längeren Exporte, Berichtsaufbereitungen, Migrationen und Importe
  verwenden dieselbe datenbankgestützte Hintergrundqueue. Der Fortschritt wird
  nach jeder abgeschlossenen Einheit gespeichert und im nächsten Cron-Lauf
  fortgesetzt, statt erneut am Anfang zu beginnen.
- Importläufe erscheinen zusammengefasst im Audit; die einzelnen importierten,
  aktualisierten oder übersprungenen Datensätze bleiben dort filterbar erhalten.
- Zeitbudget, Arbeitsschrittgröße, Worker-Lease sowie FIFO-Grenzen für Cron- und
  Audit-Protokolle werden in der Superadmin-Konfiguration gepflegt.

- Mandantenfähiges Branding inklusive Verwaltung, Logo-Upload und Impressumssteuerung
- Individuelle Navigationsfarben pro Mandant
- Gemeinsame Ansicht „Audit & Revisionen“ für fachliche Ereignisse und ReBean-Datenänderungen
- Administratorische Benutzerübersicht inklusive Rollenzuweisung und Keycloak-Verlinkung
- Schnellzugriff auf die persönliche Keycloak-Account-Seite über das Benutzermenü

## Fachliche Ausrichtung

- **Jetzt:** Dokumentation von Elektroprüfungen nach DGUV Vorschrift 3.
- **Als Nächstes:** Erweiterung um weitere Prüfkategorien (z. B. Leitern/Tritte).
- **Bestehend:** Die Authentifizierung über Keycloak und `login.koenigsbl.au` bleibt unverändert nutzbar.

## Rollen und Berechtigungen

- **Administrator/in** – Vollzugriff auf alle Einstellungen, inklusive Nutzerverwaltung sowie Mandanten & Branding.
- **Editor/in** – Kann Kurse und Teilnehmerdaten anlegen und bearbeiten, hat jedoch keinen Zugriff auf Nutzer-, Firmen- oder Systemeinstellungen.
- **Betrachter/in** – Darf Kurse und Teilnehmer*innen einsehen, aber keine Änderungen vornehmen.


## Voraussetzungen

- PHP 8.1 oder höher mit SQLite-Unterstützung
- Composer für PHP-Abhängigkeiten
- Node.js (empfohlen LTS) und npm für Frontend-Abhängigkeiten

## Installation

1. Dieses Repository und `ceneos-php-base` als benachbarte Verzeichnisse
   klonen (`pruefapp/` und `ceneos-php-base/`).
2. Abhängigkeiten installieren:
   ```bash
   composer install
   npm install
   ```
3. Datenbankverzeichnis anlegen (falls nicht automatisch erzeugt) und sicherstellen, dass der PHP-Prozess Schreibrechte besitzt.
4. Webserver konfigurieren oder die eingebaute PHP-Entwicklungsumgebung nutzen:
   ```bash
   php -S localhost:8000 index.php
   ```

## Entwicklung

- Der Einstiegspunkt befindet sich in `index.php`, der Router ist unter `lib/router.php` implementiert.
- Business-Logik findet sich in den Controllern im Verzeichnis `controllers/` sowie in den zugehörigen Templates unter `templates/`.
- Weitere Hilfsfunktionen liegen im Verzeichnis `lib/`.
- Frontend-Assets (Bootstrap, HTMX, Font Awesome, Tabulator) werden via npm verwaltet und liegen unter `public/`.

## Konfiguration

Die Anwendung lädt beim Start standardmäßig die externe Datei
`/var/www/config/pruefapp.php`, wenn das Projekt unter
`/var/www/html/pruefapp` liegt. Der Pfad liegt damit außerhalb des
Document-Roots und des Git-Repositories. Die Datei muss ein PHP-Array zurückgeben und insbesondere
die OIDC-Zugangsdaten enthalten:

```php
<?php

return [
    'APP_STORAGE_NAMESPACE' => 'pruefapp',
    'APP_OIDC_ISSUER_URL' => 'https://login.example.org/realms/example',
    'APP_OIDC_CLIENT_ID' => 'pruefapp',
    'APP_OIDC_CLIENT_SECRET' => 'lokales-secret',
];
```

Die Datei sollte nur für den betreibenden Benutzer und den Webserver lesbar
sein. Installationsspezifische Werte und Secrets gehören nicht ins Repository.

### Keycloak

- `APP_KEYCLOAK_ADMIN_CONSOLE_BASE_URL` – optionaler Direktlink zur Keycloak-Admin-Oberfläche eines Realms. Falls nicht gesetzt, wird die URL aus `APP_KEYCLOAK_SERVER_URL` und `APP_KEYCLOAK_REALM` abgeleitet; für die Standard-Konfiguration der Königsblau-Instanz wird automatisch `https://keycloak.koenigsbl.au` verwendet. Die URL kann alternativ im Backend unter „Konfiguration“ hinterlegt werden.
- `APP_KEYCLOAK_ACCOUNT_CONSOLE_BASE_URL` – optionaler Direktlink zur Keycloak-Account-Oberfläche für Nutzer*innen. Falls nicht gesetzt, wird die URL aus `APP_KEYCLOAK_SERVER_URL` und `APP_KEYCLOAK_REALM` abgeleitet; für die Standard-Konfiguration der Königsblau-Instanz wird automatisch `https://keycloak.koenigsbl.au` verwendet. Die URL kann alternativ im Backend unter „Konfiguration“ hinterlegt werden.
- `APP_KEYCLOAK_SERVER_URL` – Basis-URL der Keycloak-Instanz (Standard: `https://login.koenigsbl.au`).
- `APP_KEYCLOAK_REALM` – Name des Keycloak-Realms (Standard: `koenigsbl.au`).

### App-Instanz / Multi-App-Betrieb

Wenn mehrere PHP-Apps unter derselben Domain bzw. auf demselben Server laufen, kann die Prüf-Doku-App mit eigenen Session-Cookies und einem eigenen SQLite-Speicherbereich konfiguriert werden:

- Für **pruefapp** ist in der externen Konfigurationsdatei der Namespace `pruefapp` hinterlegt.
- `APP_STORAGE_NAMESPACE` – Namespace für den SQLite-Ablagepfad (Standard: `pruefapp`). Die DB wird bevorzugt in `data/<namespace>/db.sqlite` gesucht/angelegt.
- `APP_INSTANCE_ID` – Fallback für den Namespace, falls `APP_STORAGE_NAMESPACE` nicht gesetzt ist.
- `APP_SESSION_NAME` – expliziter PHP-Session-Cookie-Name. Ohne Wert wird automatisch ein stabiler Name auf Basis des Namespace erzeugt.

Diese Werte werden als weitere Einträge im zurückgegebenen PHP-Array gepflegt.

`APP_TENANT_DATABASE_PATH` verweist in beiden Apps auf dieselbe
Mandanten-Datenbank, empfohlen ist `/var/www/data/ceneos/tenants.sqlite`.

### Mandanten & Branding

Unter **Mandanten & Branding** werden Logo, Seitentitel, Navigationsfarben und
Rechtstexte je Mandant gepflegt. Genau ein Mandant kann über
**„Für die Login-Maske verwenden“** als Login-Branding ausgewählt werden.

Beim Erstellen eines öffentlichen Übermittlungslinks ist ein Branding-Mandant
Pflicht. Die öffentliche Teilnehmerseite übernimmt anschließend Logo, Farben,
Texte, Impressum und Datenschutz dieses Mandanten. Links ohne Mandantenzuordnung
gelten als nicht mehr gültig.

Für BSW Consult, CENEOS und Koenigsbl.au sind passende SVG-Logos und
Ausgangsfarben bereits unter `public/img/` enthalten. Eigene Logos können in
unter **Mandanten & Branding** hochgeladen werden.

Die Login-Maske kann gezielt über `login.php?tenant=<slug>` aufgerufen werden,
zum Beispiel `login.php?tenant=bsw`. Die Auswahl bleibt während des
OIDC-Redirects erhalten. Je Mandant lassen sich Logos für helle und dunkle
Hintergründe sowie Primary-, Light-, Dark- und davon unabhängige
Navbar-Farben pflegen.

### Objekt- und Kundenstruktur

Die Anwendung besitzt jetzt ein eigenes Strukturmodul (`/struktur`) mit folgenden Entitäten:

- **Kunden** (optional mit Unterkunden über `parent_customer_id`)
- **Standorte** (gehören zu einem Kunden)
- **Gebäude** (gehören zu einem Standort)
- **Etagen** (gehören zu einem Gebäude und besitzen Kürzel sowie Sortierung)
- **Bereiche** (optional innerhalb einer Etage, z. B. E oder F)
- **Räume** (gehören zu einer Etage und optional zu einem Bereich)
- **Geräte** (eigene Verwaltung unter `/geraete`, jeweils einem Raum zugeordnet)

Die Tabellen werden beim Start automatisch angelegt (`ensure_structure_schema()` in `lib/lib.inc.php`).
Alle Strukturebenen sind bearbeitbar und besitzen Beschreibung, Kommentar sowie frei
ergänzbare JSON-Metadaten. Gebäude erhalten ein Kürzel. Raumkennungen werden
pro Kunde über `auto` oder ein Muster mit `{building}`, `{floor}`, `{area}`
und `{room}` gebildet; eine Etage kann das Kundenmuster überschreiben.
Beispiele sind `1.24`, `E10`, `NU07` und `K181`. Untergeschosse mit Kürzel
`U`, `UG` oder `K` werden in der automatischen Sortierung vor dem Erdgeschoss
einsortiert.
Etagen werden automatisch aus Gebäude- und Etagenkürzel benannt, beispielsweise
`AB` + `0` als `AB0`. Die Strukturansicht lässt sich nach Freitext, Kunde,
Standort, Gebäude und Etage filtern.
Kunden und Standorte können ebenfalls optionale Kürzel erhalten. Auswahlfelder
zeigen die Hierarchie kompakt mit diesen Kürzeln, etwa
`KD SN Altbau (AB)` oder `KD SN AB0`.
Kurzbeschreibungen sind auf 240 Zeichen begrenzt und werden in Struktur- und
Geräteübersichten direkt unter Name beziehungsweise Kennung angezeigt.

### Elektro-Prüfungen importieren

Historische JSON-Exporte sowie die aktuellen Benning-Dateien können über
`php bin/import_electro.php /pfad/zu/Elektro-Testdaten` oder als Administrator
unter `/admin/pruefungen/import` eingelesen werden. Bei CSV/ODS-Paaren werden
Messwerte aus der CSV und Geräteinformationen aus der ODS über
`Speicher Nr`/`Speicherplatz` verbunden. Die neue Gerätenummer (`Nr. neu`),
alte Nummer und Speicherplatz bleiben am Gerät erhalten; jede Prüfung wird
separat mit Datum, Messwerten, Rohdaten und optionalem PDF-Bericht gespeichert.
Für den alten Bestand kann einmalig eine transportable JSONL-Datei erzeugt werden:
`php bin/build_legacy_import.php /pfad/zu/2023-2024 altbestand-import.jsonl`.
Auf dem Server wird diese Datei mit demselben Importbefehl eingelesen. Ein
separater PDF-Quellordner kann als zweites Argument angegeben werden, zum
Beispiel `php bin/import_electro.php /tmp/altbestand-import.jsonl /var/www/berichte`.

## Tests

### Prüf-App v1.1 / Abrechnung

Dieser Stand ist die Programmversion 1.1. Die Abrechnung ist ein Bestandteil
dieser Version; Prüfungen aus 2024 und älter werden grundsätzlich nicht zur
Abrechnung angeboten.

Die Abrechnung trennt die fachliche Abrechenbarkeit (`billable`/`not_billable`) vom
Rechnungsstatus. SevDesk-Exporte werden über eine idempotente Exporthistorie und
aktive Rechnungspositionen nachvollziehbar gespeichert. Prüfungen, Geräte und
Rechnungen sind miteinander verknüpft; ein Zurücksetzen eines erfolgreichen
Exports ist ausschließlich für Superadministratoren möglich, verlangt eine
Bestätigung mit Warnhinweis und deaktiviert die alte Zuordnung nicht aus der
Historie. Die Abrechnungsübersicht nutzt Bootstrap und HTMX für Filter und
Statusaktualisierungen ohne vollständigen Seitenreload.

Rechnungen und Exporthistorien starten bei einer neuen Installation leer. Bei
bestehenden Prüfungen werden die bisherigen `billable`- und Exportfelder in die
neuen Statusspalten gespiegelt; alte Zuordnungen werden nicht erfunden.

Der Bootstrap einschließlich externer Konfiguration und SQLite-Anbindung wird
mit einer temporären, isolierten Instanz geprüft:

```bash
php tests/bootstrap_config_test.php
```

Fachliche Änderungen sollten zusätzlich manuell über die Weboberfläche geprüft
werden.

Die automatisierten Tests werden gemeinsam gestartet mit:

```bash
php tests/run.php
```

Das Layout eines Prüfberichts lässt sich unabhängig von der Datenbank als PDF
erzeugen und technisch sowie visuell prüfen:

```bash
php tests/inspection_report_render_test.php /tmp/pruefapp-inspection-report.pdf
```

## Lizenz

Keine explizite Lizenzdatei vorhanden. Bitte interne Richtlinien beachten.
