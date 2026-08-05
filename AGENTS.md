# Projekt-Richtlinien

- Bitte halte dich beim PHP-Code an PSR-12 und verwende bestehende Helper aus `lib/`, sofern verfügbar.
- Neue Templates gehören in das Verzeichnis `templates/` und sollten möglichst keine Business-Logik enthalten.
- Bevor größere Änderungen vorgenommen werden, README.md aktualisieren.

## Gemeinsame CENEOS-UI

- Gemeinsame UI-Regeln aus `ceneos-php-base` gelten auch hier: Bootstrap-first, responsive Spalten, klare Abstände und keine unnötigen Dropdowns.
- Buttons, Navigation, Filter, Formular-Labels und Ausklappboxen mit passenden Icons versehen; Icon-Spalten in gleichartigen Menüs einheitlich breit halten.
- Light-/Dark-Mode-Kontrast prüfen, besonders bei aktiven Menüpunkten, gelben Icons und sekundären Texten.
- Gemeinsame Footer-, Versions-, Branding- und Mandantenlogik nicht duplizieren, sondern aus der Base verwenden.
- Bei wiederkehrenden Masken denselben serverseitigen Renderer/Controller verwenden; keine parallelen, abweichenden „Neu anlegen“-Formulare bauen.
- Technische Aufbewahrungs- und FIFO-Limits (z. B. Cron-Logs) immer zusätzlich in der Superadmin-Konfiguration anbieten; Umgebungsvariablen bleiben nur Fallbacks.
