# Moodle User Generator

Softwareprojekt der CENEOS GmbH – entwickelt für die Firmengruppe Koenigsbl.au.

Dieses Projekt stellt eine kleine Verwaltungsoberfläche bereit, mit der Kurse und Teilnehmer*innen für Moodle vorbereitet werden können. Die Anwendung basiert auf PHP, nutzt RedBeanPHP als ORM und bindet ein Keycloak/OpenID-Connect-Login ein.

## Features

- Anmeldung per OpenID Connect (Keycloak)
- Verwaltung von Kursen inklusive Einstellungen und Verknüpfungen
- Import, Export und Druck von Teilnehmerlisten
- Optionale Erfassung der zugehörigen Firma für Teilnehmer*innen
- Generierung von Benutzernamen, Passwörtern und E-Mail-Adressen
- HTMX-basierte Oberfläche mit Bootstrap-Layout und Tabulator-Tabellen
- Zentrale Hilfe- und Ablaufseite mit allen Schritten zur Kursvorbereitung

- Mandantenfähiges Branding inklusive Firmenverwaltung, Logo-Upload und Impressumssteuerung
- Individuelle Navigationsfarben pro Firma
- Administratorische Benutzerübersicht inklusive Rollenzuweisung und Keycloak-Verlinkung
- Schnellzugriff auf die persönliche Keycloak-Account-Seite über das Benutzermenü

## Rollen und Berechtigungen

- **Administrator/in** – Vollzugriff auf alle Einstellungen, inklusive Nutzer- und Firmenverwaltung.
- **Editor/in** – Kann Kurse und Teilnehmerdaten anlegen und bearbeiten, hat jedoch keinen Zugriff auf Nutzer-, Firmen- oder Systemeinstellungen.
- **Betrachter/in** – Darf Kurse und Teilnehmer*innen einsehen, aber keine Änderungen vornehmen.


## Voraussetzungen

- PHP 8.1 oder höher mit SQLite-Unterstützung
- Composer für PHP-Abhängigkeiten
- Node.js (empfohlen LTS) und npm für Frontend-Abhängigkeiten

## Installation

1. Repository klonen.
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

- `APP_KEYCLOAK_ADMIN_CONSOLE_BASE_URL` – optionaler Direktlink zur Keycloak-Admin-Oberfläche eines Realms. Falls nicht gesetzt, wird die URL aus `APP_KEYCLOAK_SERVER_URL` und `APP_KEYCLOAK_REALM` abgeleitet; für die Standard-Konfiguration der Königsblau-Instanz wird automatisch `https://keycloak.koenigsbl.au` verwendet. Die URL kann alternativ im Backend unter „Konfiguration“ hinterlegt werden.
- `APP_KEYCLOAK_ACCOUNT_CONSOLE_BASE_URL` – optionaler Direktlink zur Keycloak-Account-Oberfläche für Nutzer*innen. Falls nicht gesetzt, wird die URL aus `APP_KEYCLOAK_SERVER_URL` und `APP_KEYCLOAK_REALM` abgeleitet; für die Standard-Konfiguration der Königsblau-Instanz wird automatisch `https://keycloak.koenigsbl.au` verwendet. Die URL kann alternativ im Backend unter „Konfiguration“ hinterlegt werden.
- `APP_KEYCLOAK_SERVER_URL` – Basis-URL der Keycloak-Instanz (Standard: `https://login.koenigsbl.au`).
- `APP_KEYCLOAK_REALM` – Name des Keycloak-Realms (Standard: `koenigsbl.au`).
- Der Pfad zur Moodle-Installation sowie die Direktlinks zur Keycloak-Account- und Admin-Oberfläche können im Backend unter „Konfiguration“ gesetzt werden. Alternativ greifen die Umgebungsvariablen `MOODLE_PATH`, `APP_KEYCLOAK_ACCOUNT_CONSOLE_BASE_URL` bzw. `APP_KEYCLOAK_ADMIN_CONSOLE_BASE_URL`.
- Für den Zugriff auf den Moodle-Webservice können optional `MOODLE_WEBSERVICE_URL` und `MOODLE_WEBSERVICE_TOKEN` gesetzt werden. Beide Werte lassen sich ebenfalls in der Anwendung konfigurieren.

## Synchronisation

Die Teilnehmerverwaltung bietet zwei Wege, Daten mit Moodle abzugleichen:

1. **Import via CLI-Skript** – wie bisher werden Teilnehmer*innen als CSV-Datei über `admin/tool/uploaduser/cli/uploaduser.php` in Moodle importiert. Dabei werden bestehende Einträge aktualisiert und neue Nutzer*innen angelegt.
2. **Webservice-Synchronisation** – über den Moodle-Webservice lassen sich Kurslisten abrufen und lokale Daten damit abgleichen. Voraussetzung ist ein Webservice-Nutzer mit Zugriff auf die Funktionen `core_course_get_courses` und `core_enrol_get_enrolled_users` sowie ein gültiges Token.

Nach der Konfiguration erscheinen in der Teilnehmerübersicht zusätzliche Aktionen:

- **Aus Moodle importieren** aktualisiert lokale Datensätze anhand der aktuellen Einschreibungen. Nutzer*innen werden über die Moodle-ID, den Benutzernamen oder – falls notwendig – über die E-Mail-Adresse zugeordnet. Fehlende Teilnehmer*innen werden automatisch angelegt.
- **Mit Moodle synchronisieren** kombiniert den bestehenden CSV-Import mit einem anschließenden Webservice-Abgleich. So werden neue Nutzer*innen angelegt, Änderungen übernommen und die hinterlegte Moodle-ID aktualisiert.

Alle Aktionen werden im Audit-Log protokolliert. Fehler- und Erfolgsmeldungen erscheinen nach der Ausführung direkt in der Oberfläche.

## Tests

Aktuell sind keine automatisierten Tests definiert. Bitte testen Sie Änderungen manuell über die Weboberfläche.

## Lizenz

Keine explizite Lizenzdatei vorhanden. Bitte interne Richtlinien beachten.
