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
- **Etagen** (gehören zu einem Gebäude)
- **Räume** (gehören zu einer Etage)
- **Geräte** (gehören zu einem Raum)

Die Tabellen werden beim Start automatisch angelegt (`ensure_structure_schema()` in `lib/lib.inc.php`).

## Tests

Der Bootstrap einschließlich externer Konfiguration und SQLite-Anbindung wird
mit einer temporären, isolierten Instanz geprüft:

```bash
php tests/bootstrap_config_test.php
```

Fachliche Änderungen sollten zusätzlich manuell über die Weboberfläche geprüft
werden.

## Lizenz

Keine explizite Lizenzdatei vorhanden. Bitte interne Richtlinien beachten.
