<?php

declare(strict_types=1);

use Ceneos\PhpBase\Notification\ReleaseNotePublisher;
use Ceneos\PhpBase\Update\ReleaseNotes;

final class WhatsNewService
{
    /** @return list<array{id:string,date:string,title:string,items:list<string>}> */
    public static function entries(): array
    {
        return array_merge([[
            'id' => '2026-09-25-commented-device-storage-slots',
            'date' => '25.09.2026',
            'title' => 'Mehrere Prüf-Speicherplätze mit Kommentar',
            'items' => [
                'Im Geräteformular ergänzt „Weiterer Speicherplatz“ eine Zeile für Nummer und Kommentar, etwa für zwei Netzteile. Nur der Speicherplatzbereich wird dabei aktualisiert.',
                'Die hinterlegten Plätze und Kommentare erscheinen auch als Auswahlhilfe in der Prüfung.',
            ],
            'pruefapp_revision' => '60466fa',
            'base_revision' => 'a631489',
        ], [
            'id' => '2026-09-25-protection-class-examples-restored',
            'date' => '25.09.2026',
            'title' => 'Schutzklassen-Auswahl mit Beispielbildern',
            'items' => [
                'Die Karten für SK I, II, III und Kabel zeigen wieder passende Stecker-, Anschluss- und Leitungsbeispiele direkt im Formular.',
                'Der CEE-Drehstrom-Sonderfall ist ebenfalls sichtbar; die Bildbeispiele hängen nicht mehr von einer nachträglichen JavaScript-Umformung ab.',
            ],
            'pruefapp_revision' => 'e42371c',
            'base_revision' => 'a631489',
        ], [
            'id' => '2026-09-25-manufacturer-model-selects',
            'date' => '25.09.2026',
            'title' => 'Hersteller- und Modellauswahl wieder aktiv',
            'items' => [
                'Hersteller und Modell sind wieder durchsuchbare Auswahlfelder; Modellvorschläge folgen dem gewählten Hersteller.',
            ],
            'pruefapp_revision' => '98a4346',
            'base_revision' => 'a631489',
        ], [
            'id' => '2026-09-25-both-inspection-dates-required',
            'date' => '25.09.2026',
            'title' => 'Beide Prüfdaten sind Pflicht',
            'items' => [
                'Das Prüfdatum ist mit „heute“ vorausgefüllt; das nächste Prüfdatum wird zunächst ein Jahr später vorgeschlagen.',
                'Beide Daten können geändert werden, dürfen beim Speichern aber nicht leer bleiben. Die frühere Optionalität wurde zurückgenommen.',
            ],
            'pruefapp_revision' => 'd8d2960',
            'base_revision' => 'a631489',
        ], [
            'id' => '2026-09-25-current-test-date-required',
            'date' => '25.09.2026',
            'title' => 'Prüfdatum bleibt Pflichtfeld',
            'items' => [
                'Zwischenstand: Das aktuelle Prüfdatum war Pflicht, der nächste Prüftermin optional. Auch das nächste Prüfdatum ist inzwischen wieder Pflicht.',
            ],
            'pruefapp_revision' => '33e413e',
            'base_revision' => 'a631489',
        ], [
            'id' => '2026-09-25-protection-cards-optional-due-date',
            'date' => '25.09.2026',
            'title' => 'Schutzklassen-Karten und Prüftermin-Zwischenstand',
            'items' => [
                'Die grafische Auswahl für SK I–III und Kabel ist wieder kompakt und übersichtlich.',
                'Zwischenstand: Das nächste Prüfdatum konnte kurzzeitig leer bleiben; diese Änderung wurde zurückgenommen.',
            ],
            'pruefapp_revision' => '7f61c56',
            'base_revision' => 'a631489',
        ], [
            'id' => '2026-09-25-new-device-form-stable',
            'date' => '25.09.2026',
            'title' => 'Neues Gerät bleibt beim Listenwechsel erhalten',
            'items' => [
                'Filter und Seitennavigation setzen ein begonnenes Geräteformular nicht mehr zurück.',
                'Eine übernommene Gerätenummer wird bei späteren Listenaktualisierungen nicht erneut ins Formular geschrieben.',
            ],
            'pruefapp_revision' => '3263915',
            'base_revision' => 'a631489',
        ], [
            'id' => '2026-09-25-device-autocomplete-list-refresh',
            'date' => '25.09.2026',
            'title' => 'Geräte-Vorschläge und gezielte Listenaktualisierung',
            'items' => [
                'Hersteller und Modelle schlagen vorhandene Werte direkt im Eingabefeld vor, auch nach dem Filtern oder Seitenwechsel.',
                'Beim Filtern und Blättern bleibt die Schnellsuche „Neue Prüfung“ unverändert.',
            ],
            'pruefapp_revision' => 'f0209c9',
            'base_revision' => 'a631489',
        ], [
            'id' => '2026-09-25-multiple-device-storage-slots',
            'date' => '25.09.2026',
            'title' => 'Mehrere Prüf-Speicherplätze je Gerät',
            'items' => [
                'Geräte können optional mehrere Prüf-Speicherplätze hinterlegen, etwa Server oder Gateways mit zwei Netzteilen.',
                'Importzeilen für dieselbe Gerätenummer mit unterschiedlichen Speicherplätzen werden getrennt verarbeitet und nicht mehr als Widerspruch zusammengeworfen.',
            ],
            'pruefapp_revision' => 'b0c098a',
            'base_revision' => 'a631489',
        ], [
            'id' => '2026-09-25-device-list-partial-refresh',
            'date' => '25.09.2026',
            'title' => 'Geräteliste ohne Formular-Reset aktualisieren',
            'items' => [
                'Filter und Seitennavigation aktualisieren nur noch die Geräteliste unterhalb der Schnellsuche.',
                'Eine bereits eingescannte oder eingetippte Gerätenummer für „Neue Prüfung“ bleibt dabei erhalten.',
            ],
            'pruefapp_revision' => 'f368ea6',
            'base_revision' => 'a631489',
        ], [
            'id' => '2026-08-30-ods-empty-columns',
            'date' => '30.08.2026',
            'title' => 'Leere ODS-Spalten ausgeblendet',
            'items' => [
                'Wiederholte leere Tabellenzellen aus ODS-Dateien werden nicht mehr als künstliche „Spalte …“-Felder angezeigt oder gespeichert.',
            ],
            'pruefapp_revision' => 'c3e877e',
            'base_revision' => 'a631489',
        ], [
            'id' => '2026-08-30-import-page-memory-stability',
            'date' => '30.08.2026',
            'title' => 'Kandidatenseite speichersparend geladen',
            'items' => [
                'Rohdaten werden erst beim Öffnen eines einzelnen Kandidaten geladen; große Kandidatenläufe führen nicht mehr zu einem Speicherfehler der Importseite.',
            ],
            'pruefapp_revision' => '398e2ac',
            'base_revision' => 'a631489',
        ], [
            'id' => '2026-08-30-candidate-ods-evidence',
            'date' => '30.08.2026',
            'title' => 'ODS-Herkunft bei CSV-Kandidaten sichtbar',
            'items' => [
                'Zu jeder CSV-Zeile wird die zugeordnete ODS-Zeile lesbar als Tabelle angezeigt, einschließlich Geräte- und Regieangaben.',
            ],
            'pruefapp_revision' => 'ebfeff9',
            'base_revision' => 'a631489',
        ], [
            'id' => '2026-08-28-candidate-source-debug',
            'date' => '28.08.2026',
            'title' => 'CSV-Quellen zu Prüfweb-Prüfungen prüfen',
            'items' => [
                'Der technische Debug-Zugang kann aktuelle Prüfweb-Prüfungen direkt mit den CSV-, ODS- und JSON-Kandidaten des neuesten Laufs abgleichen, auch wenn sie noch nicht zusammengeführt sind.',
            ],
            'pruefapp_revision' => '52ab6bb · b123e15',
            'base_revision' => 'a631489',
        ], [
            'id' => '2026-08-28-candidate-source-data-collapsed',
            'date' => '28.08.2026',
            'title' => 'Rohdaten bei Kandidaten eingeklappt',
            'items' => [
                'Die vollständigen Quelldaten bleiben beim Laden und Aktualisieren der Kandidatensicht eingeklappt.',
            ],
            'pruefapp_revision' => 'd79e39b',
            'base_revision' => 'a631489',
        ], [
            'id' => '2026-08-28-import-history-empty-rows',
            'date' => '28.08.2026',
            'title' => 'Leere Zeilen aus Importverlauf entfernt',
            'items' => [
                'Nachbearbeitungslisten zeigen nur noch Prüfungen mit gültiger Gerätenummer; alte Leerzeilen werden vollständig ausgeblendet.',
            ],
            'pruefapp_revision' => '9ef70bd',
            'base_revision' => 'a631489',
        ], [
            'id' => '2026-08-28-import-history-cleanup',
            'date' => '28.08.2026',
            'title' => 'Importverlauf bereinigt',
            'items' => [
                'Gelöschte oder nur kurz nummerierte Altgeräte und ihre Prüfungen erscheinen nicht mehr in den Nachbearbeitungslisten.',
            ],
            'pruefapp_revision' => '92ea96b',
            'base_revision' => 'a631489',
        ], [
            'id' => '2026-08-28-benning-cable-records',
            'date' => '28.08.2026',
            'title' => 'Benning-Kabelzeilen zuverlässig einlesen',
            'items' => [
                'Kopflose ST-725-Zeilen mit „Kabel“ werden als SK1-Sonderfall erkannt und nicht mehr als leere Importkandidaten angezeigt.',
            ],
            'pruefapp_revision' => 'c9d4bfd',
            'base_revision' => 'a631489',
        ], [
            'id' => '2026-08-28-import-page-stability',
            'date' => '28.08.2026',
            'title' => 'Importseite stabilisiert',
            'items' => [
                'Die Importseite bleibt funktionsfähig, wenn keine Statistik zur Prüfer-Migration vorliegt.',
            ],
            'pruefapp_revision' => '4cccd7f',
            'base_revision' => 'a631489',
        ], [
            'id' => '2026-08-28-whats-new-order',
            'date' => '28.08.2026',
            'title' => 'Was ist neu? in sinnvoller Reihenfolge',
            'items' => [
                'Aktuelle Prüfapp-Änderungen stehen jetzt vor älteren gemeinsamen Änderungen der Ceneos PHP Base.',
            ],
            'pruefapp_revision' => '85a51a6',
            'base_revision' => '4750c8f',
        ], [
            'id' => '2026-08-28-benning-headerless-csv',
            'date' => '28.08.2026',
            'title' => 'Benning-CSV ohne Kopfzeile erkennen',
            'items' => [
                'Unvollständige ST-725-Exporte mit einer einzelnen Vorsatzzeile werden wieder als CSV gelesen.',
                'Speicherplatz, Schutzklasse, Prüfdatum und Ergebnis werden daraus korrekt übernommen; es entstehen keine Scheinspalten oder JSON-artigen Rohdatensätze mehr.',
            ],
            'pruefapp_revision' => '2c26045',
            'base_revision' => '4750c8f',
        ], [
            'id' => '2026-08-28-candidate-source-comparison',
            'date' => '28.08.2026',
            'title' => 'Kandidatenquellen vollständig vergleichen',
            'items' => [
                'Die Kandidatentabelle zeigt Hersteller und Modell direkt neben den übrigen Werten.',
                'Die vollständigen Rohdaten aller beteiligten Quellen lassen sich in jedem Fall anzeigen.',
            ],
            'pruefapp_revision' => 'bad55c8',
            'base_revision' => '4750c8f',
        ], [
            'id' => '2026-08-28-manual-candidate-safe-match',
            'date' => '28.08.2026',
            'title' => 'Eindeutige Prüfweb-Zuordnung',
            'items' => [
                'CSV-Messdaten werden bei gleicher Gerätenummer und gleichem Datum automatisch an die passende Prüfweb-Prüfung ergänzt.',
                'Speicherplätze werden dabei auch bei führenden Nullen gleich behandelt, etwa 045 und 45; eine fehlende manuelle Prüfart wird aus der eindeutigen CSV ergänzt.',
            ],
            'pruefapp_revision' => '3db8191 · 0a96364',
            'base_revision' => '4750c8f',
        ], [
            'id' => '2026-08-28-compact-notification-history',
            'date' => '28.08.2026',
            'title' => 'Kompakter Benachrichtigungsverlauf',
            'items' => [
                'Unter Benachrichtigungen werden zunächst die zehn neuesten Einträge angezeigt; ältere lassen sich aufklappen.',
            ],
            'pruefapp_revision' => 'a37fbc9',
            'base_revision' => '4750c8f',
        ], [
            'id' => '2026-08-28-candidate-toggle-all',
            'date' => '28.08.2026',
            'title' => 'Kandidatenfälle gesammelt öffnen',
            'items' => [
                'Im Kandidatenlauf lassen sich alle offenen Fälle mit einem Klick aufklappen und anschließend wieder einklappen.',
            ],
            'pruefapp_revision' => 'cc6ace9',
            'base_revision' => '4750c8f',
        ], [
            'id' => '2026-08-27-candidate-source-data',
            'date' => '27.08.2026',
            'title' => 'Unvollständige Importkandidaten prüfen',
            'items' => [
                'Bei unvollständigen Kandidaten lassen sich jetzt alle eingelesenen Quelldaten der konkreten CSV-/ODS-Zeile anzeigen.',
            ],
            'pruefapp_revision' => '734503c',
            'base_revision' => '4750c8f',
        ], [
            'id' => '2026-08-27-manual-failure-wins',
            'date' => '27.08.2026',
            'title' => 'Manuell nicht bestandene Prüfungen',
            'items' => [
                'Ein manuell als nicht bestanden markiertes Prüfergebnis bleibt verbindlich, auch wenn importierte Messwerte bestanden sind.',
                'CSV-Messdaten werden trotzdem ergänzt, etwa zur Dokumentation einer mangelhaften Sichtprüfung.',
            ],
            'pruefapp_revision' => '2fe43e2',
            'base_revision' => '4750c8f',
        ], [
            'id' => '2026-08-27-candidate-leading-zeroes',
            'date' => '27.08.2026',
            'title' => 'Kandidaten: Speicherplätze mit führenden Nullen',
            'items' => [
                'Speicherplätze wie 3 und 003 werden als gleicher Wert erkannt und nicht mehr als Widerspruch angezeigt.',
                'Gleiche CSV- und Prüfweb-Prüfungen werden damit automatisch zusammengeführt.',
            ],
            'pruefapp_revision' => '979f09a',
            'base_revision' => '4750c8f',
        ], [
            'id' => '2026-08-27-import-rebuild',
            'date' => '27.08.2026',
            'title' => 'Import-Neuaufbau und Kandidatensichtung',
            'items' => [
                'Prüfarten aus Importquellen werden einheitlich als SK1, SK2 oder SK3 gespeichert.',
                'Fehlende Regiezeit aus CSV/ODS wird nicht mehr als 0 interpretiert.',
                'Widersprüchliche Kandidatenfelder werden gelb hervorgehoben und müssen gezielt entschieden werden.',
                'Beim Neuaufbau werden Altprüfungen ohne mindestens sechsstellige Gerätenummer entfernt.',
            ],
            'pruefapp_revision' => 'a68d53a',
            'base_revision' => '4750c8f',
        ]], ReleaseNotes::entries());
    }

    public static function latestReleaseId(): string
    {
        return (string) (self::entries()[0]['id'] ?? '');
    }

    public static function publishForCurrentUser(): void
    {
        $user = current_user();
        if ($user === null) {
            return;
        }
        $userId = (int) $user->id;
        $releaseId = self::latestReleaseId();
        $obsoleteIds = [];
        foreach (\Ceneos\PhpBase\Notification\NotificationRepository::forUser($userId, 500) as $notification) {
            if (($notification['category'] ?? '') === 'whats_new' && ($notification['dedupe_key'] ?? '') !== 'whats-new:' . $releaseId . ':user:' . $userId) {
                $obsoleteIds[] = (int) ($notification['id'] ?? 0);
            }
        }
        \Ceneos\PhpBase\Notification\NotificationRepository::deleteMany($obsoleteIds);
        ReleaseNotePublisher::publishForUser($userId, $releaseId, 'Was ist neu?', 'Neue und geänderte Funktionen sind zur Kenntnisnahme markiert.', url_for('downloads#whats-new'));
    }
}
