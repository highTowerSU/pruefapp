# Änderungsprotokoll

## 25.09.2026 – Beide Prüfdaten sind Pflicht

- Das Prüfdatum ist bei neuen Prüfungen mit „heute“ vorausgefüllt; das nächste Prüfdatum wird zunächst ein Jahr später vorgeschlagen. Beide Angaben können angepasst werden.
- Elektro-, Leiter- und Legacy-Prüfungen verlangen beide Daten im Formular und beim serverseitigen Speichern. Die Prüfungsauswertung nennt ein fehlendes nächstes Prüfdatum ausdrücklich.
- Die kurzzeitig eingeführte Optionalität des nächsten Prüftermins wurde damit zurückgenommen.
- Revisionen: Prüfapp `d8d2960`, Ceneos PHP Base `a631489`.

## 25.09.2026 – Prüfdatum bleibt Pflichtfeld

- Das aktuelle Prüfdatum ist in Elektro-, Leiter- und Legacy-Prüfungen sichtbar als Pflichtfeld gekennzeichnet und wird auch beim Zwischenspeichern serverseitig verlangt.
- Der „Nächste Prüftermin“ ist davon getrennt und bleibt optional.
- Dieser Zwischenstand wurde mit `d8d2960` zurückgenommen; auch das nächste Prüfdatum ist Pflicht.
- Revisionen: Prüfapp `33e413e`, Ceneos PHP Base `a631489`.

## 25.09.2026 – Schutzklassen-Karten und optionaler Prüftermin

- Die grafische Auswahl für SK I–III und Kabel nutzt wieder ein kompaktes Kartenraster ohne übergroße feste Höhen.
- Das nächste Prüfdatum ist bei Elektro- und Leiterprüfungen optional. Neue Prüfungen beginnen ohne Termin; nur ein bewusst gewähltes Intervall setzt einen Vorschlag.
- Dieser Zwischenstand wurde mit `d8d2960` zurückgenommen.
- Revisionen: Prüfapp `7f61c56`, Ceneos PHP Base `a631489`.

## 25.09.2026 – Neues Gerät bleibt beim Listenwechsel erhalten

- „Neues Gerät“ steht jetzt wie „Neue Prüfung“ außerhalb des HTMX-Listenbereichs. Filter und Seitennavigation setzen ein begonnenes Geräteformular nicht mehr zurück.
- Eine übernommene Gerätenummer wird bei späteren Listenaktualisierungen nicht erneut ins Formular geschrieben.
- Revisionen: Prüfapp `3263915`, Ceneos PHP Base `a631489`.

## 25.09.2026 – Geräte-Vorschläge und gezielte Listenaktualisierung

- Hersteller und Modelle schlagen vorhandene Werte direkt im Eingabefeld vor, auch nach dem Filtern oder Seitenwechsel.
- HTMX liefert beim Filtern und Blättern nur noch die Geräteliste; die Schnellsuche „Neue Prüfung“ bleibt unverändert.
- Revisionen: Prüfapp `f0209c9`, Ceneos PHP Base `a631489`.

## 25.09.2026 – Mehrere Prüf-Speicherplätze je Gerät

- Geräte können optional mehrere Prüf-Speicherplätze hinterlegen, etwa Server oder Gateways mit zwei Netzteilen.
- Importzeilen für dieselbe Gerätenummer mit unterschiedlichen Speicherplätzen werden getrennt verarbeitet und nicht mehr als Widerspruch zusammengeworfen.
- Revisionen: Prüfapp `b0c098a`, Ceneos PHP Base `a631489`.

## 25.09.2026 – Geräteliste ohne Formular-Reset aktualisieren

- Filter und Seitennavigation aktualisieren nur noch die Geräteliste unterhalb der Schnellsuche.
- Eine bereits eingescannte oder eingetippte Gerätenummer für „Neue Prüfung“ bleibt dabei erhalten.
- Revisionen: Prüfapp `f368ea6`, Ceneos PHP Base `a631489`.

## 30.08.2026 – Leere ODS-Spalten ausgeblendet

- Wiederholte leere Tabellenzellen aus ODS-Dateien werden nicht mehr als künstliche „Spalte …“-Felder angezeigt oder gespeichert.
- Revisionen: Prüfapp `c3e877e`, Ceneos PHP Base `a631489`.

## 30.08.2026 – Kandidatenseite speichersparend geladen

- Rohdaten werden erst beim Öffnen eines einzelnen Kandidaten geladen; große Kandidatenläufe führen nicht mehr zu einem Speicherfehler der Importseite.
- Revisionen: Prüfapp `398e2ac`, Ceneos PHP Base `a631489`.

## 30.08.2026 – ODS-Herkunft bei CSV-Kandidaten sichtbar

- Zu jeder CSV-Zeile wird die zugeordnete ODS-Zeile lesbar als Tabelle angezeigt, einschließlich Geräte- und Regieangaben.
- Revisionen: Prüfapp `ebfeff9`, Ceneos PHP Base `a631489`.

## 28.08.2026 – CSV-Quellen zu Prüfweb-Prüfungen prüfen

- Der technische Debug-Zugang kann aktuelle Prüfweb-Prüfungen direkt mit den CSV-, ODS- und JSON-Kandidaten des neuesten Laufs abgleichen, auch wenn sie noch nicht zusammengeführt sind.
- Revisionen: Prüfapp `52ab6bb`, `b123e15`, Ceneos PHP Base `a631489`.

## 28.08.2026 – Rohdaten bei Kandidaten eingeklappt

- Die vollständigen Quelldaten bleiben beim Laden und Aktualisieren der Kandidatensicht eingeklappt.
- Revisionen: Prüfapp `d79e39b`, Ceneos PHP Base `a631489`.

## 28.08.2026 – Leere Zeilen aus Importverlauf entfernt

- Nachbearbeitungslisten zeigen nur noch Prüfungen mit gültiger Gerätenummer; alte Leerzeilen werden vollständig ausgeblendet.
- Revisionen: Prüfapp `9ef70bd`, Ceneos PHP Base `a631489`.

## 28.08.2026 – Importverlauf bereinigt

- Gelöschte oder nur kurz nummerierte Altgeräte und ihre Prüfungen erscheinen nicht mehr in den Nachbearbeitungslisten.
- Revisionen: Prüfapp `92ea96b`, Ceneos PHP Base `a631489`.

## 28.08.2026 – Benning-Kabelzeilen zuverlässig einlesen

- Kopflose ST-725-Zeilen mit „Kabel“ werden als SK1-Sonderfall erkannt und nicht mehr als leere Importkandidaten angezeigt.
- Revisionen: Prüfapp `c9d4bfd`, Ceneos PHP Base `a631489`.

## 28.08.2026 – Importseite stabilisiert

- Die Importseite bleibt funktionsfähig, wenn keine Statistik zur Prüfer-Migration vorliegt.
- Revisionen: Prüfapp `4cccd7f`, Ceneos PHP Base `a631489`.

## 28.08.2026 – Was ist neu? in sinnvoller Reihenfolge

- Aktuelle Prüfapp-Änderungen stehen vor älteren gemeinsamen Änderungen der Ceneos PHP Base.
- Revisionen: Prüfapp `85a51a6`, Ceneos PHP Base `4750c8f`.

## 28.08.2026 – Benning-CSV ohne Kopfzeile erkennen

- Unvollständige ST-725-Exporte mit einer einzelnen Vorsatzzeile werden wieder als Semikolon-CSV eingelesen.
- Speicherplatz, Schutzklasse, Prüfdatum und Ergebnis bleiben erhalten; es entstehen keine Scheinspalten oder JSON-artigen Rohdatensätze mehr.
- Revisionen: Prüfapp `2c26045`, Ceneos PHP Base `4750c8f`.

## 28.08.2026 – Kandidatenquellen vollständig vergleichen

- Hersteller und Modell stehen direkt in der Kandidatenvergleichstabelle.
- Die vollständigen Rohdaten jeder beteiligten Quelle können bei jedem Kandidatenfall angezeigt werden.
- Revisionen: Prüfapp `bad55c8`, Ceneos PHP Base `4750c8f`.

## 28.08.2026 – Eindeutige Prüfweb-Zuordnung

- Eindeutige manuelle Treffer werden auch dann automatisch ergänzt, wenn ihr Speicherplatz nur durch führende Nullen abweicht, etwa `045` und `45`.
- Eine fehlende manuelle Prüfart wird dabei aus der eindeutigen CSV ergänzt.
- Revisionen: Prüfapp `3db8191`, `0a96364`, Ceneos PHP Base `4750c8f`.

## 28.08.2026 – Revisions-Tags in „Was ist neu?“

- Die Prüfapp- und Ceneos-PHP-Base-Revisionen erscheinen direkt an den jeweiligen Änderungen als Tags.
- Revisionen: Prüfapp `e2cd2ed`, Ceneos PHP Base `4750c8f`.

## 28.08.2026 – Was ist neu? als persönliche Checkliste

- Es gibt nur noch eine aktuelle „Was ist neu?“-Benachrichtigung.
- Die einzelnen Änderungen stehen direkt unter Benachrichtigungen, sind bis zur Kenntnisnahme gelb markiert und lassen sich einzeln oder gesammelt abhaken.
- Revisionen: Prüfapp `786ddbb`, Ceneos PHP Base `4750c8f`.

## 28.08.2026 – Benachrichtigungsverlauf kompakt

- Unter Downloads sind zunächst nur die zehn neuesten Benachrichtigungen sichtbar; ältere Einträge lassen sich bei Bedarf aufklappen.
- Revisionen: Prüfapp `a37fbc9`, Ceneos PHP Base `4750c8f`.

## 28.08.2026 – Kandidatenfälle gesammelt öffnen

- Die offenen Fälle eines Kandidatenlaufs lassen sich gesammelt auf- und einklappen.

## 27.08.2026 – Quelldaten unvollständiger Kandidaten

- Die Kandidatensichtung zeigt auf Wunsch alle eingelesenen Werte der konkreten CSV-/ODS-Zeile an.

## 27.08.2026 – Manuelles Prüfergebnis hat Vorrang

- Ein in Prüfweb manuell als nicht bestanden markiertes Ergebnis bleibt bei der Kandidatenzusammenführung erhalten. CSV-Messwerte ergänzen die Prüfung, überschreiben aber eine mangelhafte Sichtprüfung nicht.

## 27.08.2026 – Speicherplätze beim Kandidatenabgleich

- Speicherplätze mit unterschiedlichen führenden Nullen, etwa `3` und `003`, werden als identisch behandelt und automatisch zusammengeführt.

## 27.08.2026 – Import-Neuaufbau und Kandidatensichtung

- Importierte Schutzklassen werden als `SK1`, `SK2` oder `SK3` vereinheitlicht.
- Fehlende CSV-/ODS-Regiezeit wird nicht mehr als `0` behandelt.
- Widersprüche in Importkandidaten sind gelb markiert und feldweise entscheidbar.
- Der Neuaufbau entfernt Altprüfungen ohne mindestens sechsstellige Gerätenummer.
- Revisionen: Prüfapp `a68d53a`, Ceneos PHP Base `4750c8f`.

Nutzerrelevante Änderungen erscheinen zusätzlich als persönliche „Was ist neu?“-Benachrichtigung und unter **Downloads → Was ist neu?**.
