# Moodle User Generator

Dieses Projekt stellt eine kleine Verwaltungsoberfläche bereit, mit der Kurse und Teilnehmer*innen für Moodle vorbereitet werden können. Die Anwendung basiert auf PHP, nutzt RedBeanPHP als ORM und bindet ein Keycloak/OpenID-Connect-Login ein.

## Features

- Anmeldung per OpenID Connect (Keycloak)
- Verwaltung von Kursen inklusive Einstellungen und Verknüpfungen
- Import, Export und Druck von Teilnehmerlisten
- Generierung von Benutzernamen, Passwörtern und E-Mail-Adressen
- HTMX-basierte Oberfläche mit Bootstrap-Layout und Tabulator-Tabellen

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

## Tests

Aktuell sind keine automatisierten Tests definiert. Bitte testen Sie Änderungen manuell über die Weboberfläche.

## Lizenz

Keine explizite Lizenzdatei vorhanden. Bitte interne Richtlinien beachten.
