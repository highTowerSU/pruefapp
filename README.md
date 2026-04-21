# Prüf-Doku App

Softwareprojekt der CENEOS GmbH – entwickelt für die Firmengruppe Koenigsbl.au.

Dieses Projekt stellt eine Verwaltungsoberfläche für die Dokumentation von Prüfungen bereit. Der aktuelle Schwerpunkt liegt auf Elektroprüfungen nach DGUV Vorschrift 3. Die Anwendung basiert auf PHP, nutzt RedBeanPHP als ORM und bindet weiterhin ein Keycloak/OpenID-Connect-Login ein (u. a. `login.koenigsbl.au`).

## Features

- Anmeldung per OpenID Connect (Keycloak)
- Verwaltung von Prüfaufträgen inklusive Einstellungen und Verknüpfungen
- Import, Export und Druck von Listen (z. B. Prüfobjekte/Teilnehmende)
- Optionale Erfassung der zugehörigen Firma für Datensätze
- Wiederverwendbare Datenflüsse für strukturierte Dokumentationsprozesse
- HTMX-basierte Oberfläche mit Bootstrap-Layout und Tabulator-Tabellen
- Zentrale Hilfe- und Ablaufseite für den Prüfprozess

- Mandantenfähiges Branding inklusive Firmenverwaltung, Logo-Upload und Impressumssteuerung
- Individuelle Navigationsfarben pro Firma
- Administratorische Benutzerübersicht inklusive Rollenzuweisung und Keycloak-Verlinkung
- Schnellzugriff auf die persönliche Keycloak-Account-Seite über das Benutzermenü

## Fachliche Ausrichtung

- **Jetzt:** Dokumentation von Elektroprüfungen nach DGUV Vorschrift 3.
- **Als Nächstes:** Erweiterung um weitere Prüfkategorien (z. B. Leitern/Tritte).
- **Bestehend:** Die Authentifizierung über Keycloak und `login.koenigsbl.au` bleibt unverändert nutzbar.

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

### Keycloak

- `APP_KEYCLOAK_ADMIN_CONSOLE_BASE_URL` – optionaler Direktlink zur Keycloak-Admin-Oberfläche eines Realms. Falls nicht gesetzt, wird die URL aus `APP_KEYCLOAK_SERVER_URL` und `APP_KEYCLOAK_REALM` abgeleitet; für die Standard-Konfiguration der Königsblau-Instanz wird automatisch `https://keycloak.koenigsbl.au` verwendet. Die URL kann alternativ im Backend unter „Konfiguration“ hinterlegt werden.
- `APP_KEYCLOAK_ACCOUNT_CONSOLE_BASE_URL` – optionaler Direktlink zur Keycloak-Account-Oberfläche für Nutzer*innen. Falls nicht gesetzt, wird die URL aus `APP_KEYCLOAK_SERVER_URL` und `APP_KEYCLOAK_REALM` abgeleitet; für die Standard-Konfiguration der Königsblau-Instanz wird automatisch `https://keycloak.koenigsbl.au` verwendet. Die URL kann alternativ im Backend unter „Konfiguration“ hinterlegt werden.
- `APP_KEYCLOAK_SERVER_URL` – Basis-URL der Keycloak-Instanz (Standard: `https://login.koenigsbl.au`).
- `APP_KEYCLOAK_REALM` – Name des Keycloak-Realms (Standard: `koenigsbl.au`).

### Moodle-Verknüpfung

Im Backend können unter „Konfiguration“ alle Moodle-Einstellungen gepflegt werden. Alternativ greifen die Umgebungsvariablen `MOODLE_PATH`, `MOODLE_WEBSERVICE_URL` und `MOODLE_WEBSERVICE_TOKEN`.

- **Pfad zur Moodle-Installation (`MOODLE_PATH`)** – Lokaler Serverpfad, damit CLI-Skripte wie `admin/tool/uploaduser/cli/uploaduser.php` sowie für die Kurskopie `course/management/cli/duplicate_course.php` (ältere Moodle-Versionen) bzw. `admin/cli/import.php` (Moodle 5.1+) gefunden werden.
- **Webservice-URL (`MOODLE_WEBSERVICE_URL`)** – Basis-URL der Zielinstanz (z. B. `https://moodle.example.org`). Die Anwendung ergänzt automatisch den REST-Endpunkt `/webservice/rest/server.php`.
- **Webservice-Token (`MOODLE_WEBSERVICE_TOKEN`)** – Zugriffsschlüssel eines berechtigten Webservice-Nutzers.

> 💡 Eine Schritt-für-Schritt-Anleitung zur Einrichtung von Webservices in Moodle findest du in der deutschsprachigen Moodle-Dokumentation: [Webservices nutzen](https://docs.moodle.org/501/de/Webservices_nutzen).

Nachdem die Moodle-URL hinterlegt wurde, führen folgende Schritte zu einem kompatiblen Webservice-Setup:

1. **Eigene Rolle für den Webservice definieren:** Unter `Website-Administration → Nutzer*innen → Berechtigungen → Rollen verwalten` eine neue Rolle anlegen (z. B. „Webservice Synchronisation“) und mindestens die Capabilities `webservice/rest:use` sowie die für Kurs- und Teilnehmerabfragen nötigen Rechte (`moodle/course:view`, `moodle/user:viewalldetails`, `moodle/role:assign`) vergeben.
2. **Webservice aktivieren:** Unter `Website-Administration → Plugins → Webservices → Externe Dienste` einen neuen externen Dienst anlegen oder einen vorhandenen aktivieren. Stelle sicher, dass die Funktionen `core_course_get_courses`, `core_course_create_courses`, `core_enrol_get_enrolled_users` und `core_webservice_get_site_info` zugeordnet sind.
3. **Dienstnutzer anlegen:** Einen technischen Nutzer erstellen oder auswählen und ihm die zuvor definierte Rolle über die Systemebene zuweisen. Der Login dieses Kontos wird ausschließlich für API-Aufrufe genutzt.
4. **Token generieren:** Unter `Website-Administration → Plugins → Webservices → Tokens verwalten` ein neues Token für den Dienstnutzer erzeugen und in der Anwendung hinterlegen.


Optional können zusätzlich Links zu relevanten Moodle-Seiten (z. B. Kurs- oder Teilnehmerverwaltung) im Konfigurationsformular hinterlegt werden, sobald die Instanz-URL bekannt ist. So gelangen Administrator*innen direkt aus der Anwendung zu den passenden Bereichen der Moodle-Administration.

## Synchronisation

Die Teilnehmerverwaltung bietet zwei Wege, Daten mit Moodle abzugleichen:

1. **Import via CLI-Skript** – wie bisher werden Teilnehmer*innen als CSV-Datei über `admin/tool/uploaduser/cli/uploaduser.php` in Moodle importiert. Dabei werden bestehende Einträge aktualisiert und neue Nutzer*innen angelegt.
2. **Webservice-Synchronisation** – über den Moodle-Webservice lassen sich Kurslisten abrufen und lokale Daten damit abgleichen. Voraussetzung ist ein Webservice-Nutzer mit Zugriff auf die Funktionen `core_course_get_courses` und `core_enrol_get_enrolled_users` sowie ein gültiges Token.

Nach der Konfiguration erscheinen in der Teilnehmerübersicht zusätzliche Aktionen:

- **Aus Moodle importieren** aktualisiert lokale Datensätze anhand der aktuellen Einschreibungen. Nutzer*innen werden über die Moodle-ID, den Benutzernamen oder – falls notwendig – über die E-Mail-Adresse zugeordnet. Fehlende Teilnehmer*innen werden automatisch angelegt.
- **Mit Moodle synchronisieren** kombiniert den bestehenden CSV-Import mit einem anschließenden Webservice-Abgleich. So werden neue Nutzer*innen angelegt, Änderungen übernommen und die hinterlegte Moodle-ID aktualisiert.

Alle Aktionen werden im Audit-Log protokolliert. Fehler- und Erfolgsmeldungen erscheinen nach der Ausführung direkt in der Oberfläche.

## Hinweise zur Moodle-Kurskopie (Moodle 5.1)

Für das Anlegen neuer Kurse aus einer Vorlage unterstützt die Anwendung zwei Moodle-Varianten:

1. **Legacy-Modus** über `course/management/cli/duplicate_course.php` (direkte Duplizierung).
2. **Moodle-5.1-Modus** über `admin/cli/import.php`:
   - Der Zielkurs wird bei Bedarf zuerst per Webservice (`core_course_create_courses`) angelegt.
   - Anschließend wird der Kursinhalt per CLI-Import aus dem Quellkurs übernommen.

Damit der Moodle-5.1-Modus funktioniert, müssen sowohl `MOODLE_PATH` als auch Webservice-URL und Webservice-Token gesetzt sein.

### Troubleshooting: `Access Control Exception` bei Kurskopie

Wenn die Kurskopie mit einer Meldung wie `Moodle-Webservice meldet einen Fehler: Access Control Exception` abbricht, liegt meist eine fehlende Berechtigung im externen Dienst oder ein falscher Kontext (System/Kategorie/Kurs) vor.

Empfohlene Prüfschritte:

1. **Token und externer Dienst prüfen**
   - Geh zu `Website-Administration → Plugins → Webservices → Externe Dienste`.
   - Verifiziere, dass der verwendete Dienst aktiv ist und dem Token-Nutzer zugeordnet wurde.
   - Stelle sicher, dass mindestens folgende Funktionen im Dienst enthalten sind:
     - `core_webservice_get_site_info`
     - `core_course_get_courses`
     - `core_course_create_courses`
     - `core_course_copy_courses` (falls die Moodle-Version/Funktion verfügbar ist)
2. **Rolle und Capability-Kontext prüfen**
   - Prüfe unter `Nutzer*innen → Berechtigungen → Rechte prüfen`, ob der technische Webservice-Nutzer die benötigten Capabilities im richtigen Kontext besitzt (System oder Zielkategorie/Kurs).
   - Für die Kursanlage/Kopie reicht eine Systemrolle oft nicht aus, wenn die Zielkategorie gesonderte Overrides hat.
3. **Konfiguration in dieser Anwendung prüfen**
   - Unter **Konfiguration → Status der Moodle-Integration** prüfen, ob Webservice-URL, Token und Moodle-Pfad korrekt erkannt werden.
   - Zusätzlich können dort per Button **„Skripte prüfen“** und **„Webservice prüfen“** direkte Laufzeitchecks ausgeführt werden.
4. **Webservice außerhalb der Anwendung testen**
   - Schnelltest für das Token:
     ```bash
     curl -sS "https://MOODLE_HOST/webservice/rest/server.php?wstoken=TOKEN&wsfunction=core_webservice_get_site_info&moodlewsrestformat=json"
     ```
   - Gibt Moodle hierbei bereits einen Fehler zurück, liegt das Problem in Moodle-Rechten/Dienstkonfiguration und nicht im CSV-Importskript.

## Tests

Aktuell sind keine automatisierten Tests definiert. Bitte testen Sie Änderungen manuell über die Weboberfläche.

## Lizenz

Keine explizite Lizenzdatei vorhanden. Bitte interne Richtlinien beachten.
