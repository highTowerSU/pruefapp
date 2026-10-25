# Projekt-Richtlinien

- Bitte halte dich beim PHP-Code an PSR-12 und verwende bestehende Helper aus `lib/`, sofern verfügbar.
- Neue Templates gehören in das Verzeichnis `templates/` und sollten möglichst keine Business-Logik enthalten.
- Bevor größere Änderungen vorgenommen werden, README.md aktualisieren.

## Gemeinsame CENEOS-UI

- Gemeinsame UI-Regeln aus `ceneos-php-base` gelten auch hier: Bootstrap-first, responsive Spalten, klare Abstände und keine unnötigen Dropdowns.
- Auf großen Bildschirmen darf ein zentrierter Bootstrap-`container` genutzt werden; `container-fluid` bleibt für Ansichten vorbehalten, die tatsächlich die volle Breite benötigen.
- Buttons, Navigation, Filter, Formular-Labels und Ausklappboxen mit passenden Icons versehen; Icon-Spalten in gleichartigen Menüs einheitlich breit halten.
- Light-/Dark-Mode-Kontrast prüfen, besonders bei aktiven Menüpunkten, gelben Icons und sekundären Texten.
- Gemeinsame Footer-, Versions-, Branding- und Mandantenlogik nicht duplizieren, sondern aus der Base verwenden.
- Bei wiederkehrenden Masken denselben serverseitigen Renderer/Controller verwenden; keine parallelen, abweichenden „Neu anlegen“-Formulare bauen.
- Betriebsrelevante, durch Betreiber änderbare Konfigurationen gehören immer in die GUI; Umgebungsvariablen und externe Dateien bleiben auf Bootstrap-/Deployment-Fallbacks beschränkt.
- Bei jeder nutzerrelevanten Änderung `CHANGELOG.md` und `WhatsNewService::entries()` ergänzen, damit sie in der GUI und als persönliche „Was ist neu?“-Benachrichtigung erscheint. Im Changelog gehören die beteiligten Prüfapp- und Ceneos-PHP-Base-Commit-Revisionen dazu.

## HTTP-Deployment auf vServer 2

Deployments laufen über den signierten HTTP-Endpunkt von CENEOS Base, nicht per SSH. Endpunkt: `https://abrechnung.koenigsbl.au/_ceneos-deploy/`. Client: `/home/codexuser/ceneos-php-base/bin/deploy-request.php`.

| Target | Lokaler Checkout | GitHub | Branch |
|---|---|---|---|
| `ceneos-php-base` | `/home/codexuser/ceneos-php-base` | `git@github.com:highTowerSU/ceneos-php-base.git` | `main` |
| `pruefapp` | `/home/codexuser/pruefapp` | `git@github.com:highTowerSU/pruefapp.git` | `codex-test` |
| `moodle-user-gen` | `/home/codexuser/moodle_user_gen` | `git@github.com:highTowerSU/moodle_user_gen.git` | `codex-test` |
| `kb-abrechnung` | `/home/codexuser/sevdesk/kb-abrechnung` | `https://github.com/highTowerSU/kb-abrechnung.git` | `main` |
| `auftraege` | `/home/codexuser/auftraege/app` | `https://github.com/highTowerSU/auftraege.git` | `main` |

`CENEOS_DEPLOY_SECRET` kommt ausschließlich aus dem vorhandenen Secret-Store; niemals in Dateien, Git, Logs oder sichtbare Shell-Ausgabe schreiben. Aufruf: `php /home/codexuser/ceneos-php-base/bin/deploy-request.php deploy TARGET`; danach mit `status REQUEST_ID` bis `success` oder `failed` abfragen. Der Worker läuft minütlich. Die Server-Allowlist liegt geschützt in `/etc/ceneos/deploy.php`; neue Targets müssen dort vom Betreiber mit Pfad, Branch und Composer-Option eingetragen werden. Der Worker führt `git pull --ff-only` und, wenn konfiguriert, `composer install` aus.

## Freigegebener SSH-Betrieb auf vserver2

Seit 25.10.2026 hat der Betreiber den öffentlichen Agent-Schlüssel hinterlegt und SSH-Nutzung ausdrücklich freigegeben. Direkter Host: `root@192.168.168.102`, Schlüssel `/home/codexuser/.ssh/id_ed25519`; `IdentityAgent=none` verwenden. Vor Eingriffen den Hostnamen `vserver2` prüfen. Die öffentlichen Namen `vserver2.koenigsbl.au` und `vserver2.server-cd.eu` landen über SSH auf der Firewall; nicht mit dem Anwendungsserver verwechseln.

Laufende Byobu-/tmux-Jobs zuerst prüfen und nicht durch einen zweiten Migrationslauf oder Eingaben unterbrechen. Datenbankumstellungen laufen mit dem gemeinsamen Base-Werkzeug `bin/mysql-migrate-suite.php`; root-eigene Logs/Sicherungen liegen unter `/var/backups/ceneos/`. Quellcode weiterhin über den oben dokumentierten signierten HTTP-Deploy ausrollen. Secrets weder ausgeben noch in Git übernehmen.
