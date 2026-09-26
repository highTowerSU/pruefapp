# Änderungsprotokoll

## 27.09.2026 – Gerätefilter ohne wiederholtes Nachladen

- Gerätefilter werden erst nach „Filtern“ oder Enter angewendet. Das verhindert zusätzliche HTMX-Aufrufe beim Auswählen mehrerer Filter.
- Hersteller-, Modell- und Bezeichnungsvorschläge werden erst geladen, wenn das jeweilige Geräteformular geöffnet wird. Ein Filterwechsel startet damit nicht mehr bis zu drei Vorschlagsabfragen pro zugeklapptem Gerät.
- Die Abrechnungsfilter behalten ihr bisheriges Verhalten; Seitennavigation tauscht weiterhin nur die Geräteliste.
- Revisionen: Prüfapp `78ffdbb`; Ceneos PHP Base `a631489` (unverändert).

## 27.09.2026 – Prüf-Speicherplätze gehören zur Prüfung

- Optionale BENNING-Speicherplätze und ihre Kommentare werden nur noch in der einzelnen Prüfung erfasst und angezeigt, nicht im Geräteformular oder in der Geräteübersicht.
- Die Prüfung übernimmt keine Geräte-Speicherplätze mehr als Vorschläge. Beim CSV-/ODS-Import werden Speicherplätze nicht mehr am Gerät gespeichert oder als Gerätekennung für die Zuordnung verwendet.
- Bereits vorhandene Gerätedaten bleiben aus Sicherheitsgründen in der Datenbank erhalten, werden aber nicht mehr bearbeitet oder angezeigt. Bestehende Prüfungs-Speicherplätze bleiben unverändert.
- Revisionen: Prüfapp `8f8ccba`; Ceneos PHP Base `a631489` (unverändert).

## 27.09.2026 – Fehlende Kabellänge bei der Bewertung benennen

- Liegt der Schutzleiterwiderstand über 0,3 Ω und höchstens bei 1,0 Ω, wird ohne Kabellänge kein pauschales Fehlerurteil mehr gefällt. Die Prüfung nennt ausdrücklich „Kabellänge fehlt“ und erklärt, warum der Messwert noch nicht eindeutig bewertet werden kann.
- Bei eindeutigem Messwert bis 0,3 Ω bzw. über 1,0 Ω bleibt die Bewertung auch ohne Längenangabe möglich. Ein ausdrücklich nicht bestandener Messwert bleibt nicht bestanden.
- In der Prüfungsdetailseite erscheint der Hinweis auch bei älteren, noch nicht neu bewerteten Messdaten. Ist eine Kabellänge bereits in der Prüfung hinterlegt, nutzt der erneute CSV-Messdatenimport sie auch dann, wenn die CSV-Spalte leer ist.
- Revisionen: Prüfapp `946a763`; Ceneos PHP Base `a631489` (unverändert).

## 27.09.2026 – Importierte Messwerte korrekt auswerten

- Beim Messdatenimport für einen einzelnen Speicherplatz werden die Werte nun auch in die strukturierte Messtabelle übernommen. Bisher konnte die Prüfmaske drei JSON-Messwerte zählen und dieselben Messungen trotzdem als fehlend melden.
- Bei bereits betroffenen Prüfungen berücksichtigt die Fehlauflistung die vorhandenen JSON-Messwerte ohne stillschweigende Datenbankänderung. Ist nur die gespeicherte Bewertung veraltet, weist die Maske auf „Prüfung serverseitig prüfen und abschließen“ hin; dieser Schritt übernimmt die Messwerte und bewertet die Prüfung neu.
- Fehlende und vorhandene, aber nicht auswertbare Messungen sind in der Hinweisliste unterscheidbar. Textuell gespeicherte Zahlenwerte werden wieder ausgewertet.
- Vier veraltete Testannahmen/-datensätze wurden korrigiert. Der reguläre Testlauf umfasst jetzt auch die Messdaten- und Prüfmaskenregressionen.
- Revisionen: Prüfapp `a2ed096`; Ceneos PHP Base `a631489` (unverändert).

## 27.09.2026 – Mehr Gerätedaten bei neuer Prüfung

- Oben in der Prüfmaske sind Inventar- und Seriennummer stets sichtbar, auch wenn sie am Gerät noch nicht hinterlegt sind. Vorhandene Altnummer, importierte Raumangabe und Kurzbeschreibung helfen bei der Identifizierung des Geräts.
- Die Gerätenummer-/Barcode-Suche bleibt unverändert.
- Revisionen: Prüfapp `a036ba8`; Ceneos PHP Base `a631489` (unverändert).

## 27.09.2026 – Gerätedaten und fehlende Prüfungsangaben sichtbar

- Nach der Gerätenummer-/Barcode-Suche und oben in der Prüfungsmaske werden Hersteller, Modell und vorhandene Inventar- bzw. Seriennummern angezeigt. Fehlende Hersteller- oder Modellangaben sind gelb markiert.
- Bei „Daten fehlen“ nennt die Ergebnisbox die konkreten offenen Pflichtangaben, Prüffragen und Messungen in einer gelb markierten Liste. Bei importierten Prüfungen werden nicht gespeicherte Pflichtfragen anhand des Prüfkatalogs ergänzt.
- Revisionen: Prüfapp `2b7c46e`; Ceneos PHP Base `a631489` (unverändert).

## 27.09.2026 – Barcode-Suche ohne schwere Startprüfungen

- Der lesende Endpunkt `/geraete/suche` startet ohne die bei jedem normalen App-Aufruf ausgeführten Schema-, Seed- und Wartungsprüfungen. Anmeldung und Kundenzugriff bleiben unverändert geprüft.
- Die Antwort zeigt `Server-Timing` für Bootstrap, Anmeldung, Datenbankabfrage und Zugriffskontrolle. Damit lässt sich die verbleibende Wartezeit im Browser gezielt zuordnen.
- Revisionen: Prüfapp `9209601`; Ceneos PHP Base `a631489` (unverändert).

## 27.09.2026 – Barcode-Suche beschleunigt

- Gescannte Nummern werden bei Enter sofort gesucht; längere Eingaben starten die Suche nach 100 statt 250 Millisekunden. Doppelte und überholte Anfragen werden abgebrochen, damit nur das aktuelle Ergebnis erscheint.
- Datenbank-Indizes für Geräte- und Altnummer verkürzen die Suche auch bei großem Gerätebestand.
- Revisionen: Prüfapp `9e2911e`; Ceneos PHP Base `a631489` (unverändert).

## 26.09.2026 – Fokus auf Inventarnummer nach Barcode-Suche

- Nach „Gerät neu anlegen“ aus der Gerätenummer-/Barcode-Suche wird das neue Geräteformular ohne Seitenwechsel geöffnet und der Fokus direkt auf „Inventarnummer“ gesetzt.
- Revisionen: Prüfapp `7e1d7cf`; Ceneos PHP Base `a631489` (unverändert).

## 26.09.2026 – Neues Gerät aus Barcode-Suche ohne Seitenwechsel

- Findet die Suche nach Gerätenummer oder Barcode kein Gerät, öffnet „Gerät neu anlegen“ das bereits vorhandene Geräteformular ohne Seiten-Reload. Die gesuchte Nummer wird übernommen, der Bereich sichtbar gemacht und das nächste Eingabefeld fokussiert.
- Ein begonnenes Formular mit anderer Gerätenummer wird nicht stillschweigend überschrieben; der Wechsel erfordert eine Bestätigung. Der Link bleibt als Rückfall für nicht verfügbare Skripte erhalten.
- Revisionen: Prüfapp `55007b5`; Ceneos PHP Base `a631489` (unverändert).

## 26.09.2026 – Speicherplatz-Kommentar nur bei mehreren Plätzen

- Bei einem einzelnen Prüf-Speicherplatz wird das Kommentarfeld ausgeblendet; vorhandene Kommentare bleiben beim Speichern erhalten. Ab zwei Plätzen sind die Kommentare zur Unterscheidung sichtbar.
- Als Beispiel nennt die Hilfe nun ausdrücklich einen Server mit zwei Netzteilen (PSU 1 und PSU 2).
- Revisionen: Prüfapp `a5e33bb`; Ceneos PHP Base `a631489` (unverändert).

## 26.09.2026 – Mehrere Speicherplätze direkt in der Prüfung

- In den Prüfungsdaten ergänzt „Weiterer Speicherplatz“ per HTMX zusätzliche BENNING-Speicherplätze mit Kommentar. Die Erklärung nennt ausdrücklich Geräte mit zwei Netzteilen (PSU 1 und PSU 2). Nummern und Kommentare bleiben beim Bearbeiten bestehender Prüfungen erhalten und erscheinen in der Prüfungsansicht.
- Beim Messdatenimport können mehrere Speicherplätze derselben manuellen Prüfung zugeordnet werden. Nach nur einem von zwei Netzteilen bleibt die Prüfung unvollständig; beide Messwertsätze werden getrennt erhalten.
- Revisionen: Prüfapp `bcdfea7`; Ceneos PHP Base `a631489` (unverändert).

## 26.09.2026 – Offene Prüfungen mit Messdaten klar kennzeichnen

- In der Importübersicht werden offene Prüfweb-Prüfungen mit bereits vorhandenen Messdaten und weiterhin fehlenden Angaben gelb als „Messdaten vorhanden · Daten fehlen“ markiert. Die Liste unterscheidet sie von Prüfungen ohne Messdaten.
- Bei leerem Speicherplatz führt „Speicherplatz hinzufügen“ direkt zum bearbeitbaren Speicherplatzfeld der Prüfung.
- Revisionen: Prüfapp `a4bc69e`; Ceneos PHP Base `a631489` (unverändert).

## 26.09.2026 – Messdaten aus CSV mit mehreren Prüftagen zuordnen

- Beim Import in bestehende Prüfweb-Prüfungen wird jede CSV-Zeile über ihr eigenes Prüfdatum und ihren Speicherplatz zugeordnet. Ein gemischter Export wird nicht mehr vollständig auf das Datum der ersten Zeile festgelegt.
- Das optionale Ersatz-Prüfdatum gilt nur für Zeilen ohne eigenes Datum. Nicht zuordenbare Zeilen bleiben unangetastet und erhalten einen konkreten Überspringgrund.
- Ein Regressionstest deckt einen Export mit 4, 20 und 52 Messungen an drei unterschiedlichen Prüftagen ab.
- Revisionen: Prüfapp `4cdffff`; Ceneos PHP Base `a631489` (unverändert).

## 25.09.2026 – Messdatenimport startet schneller

- „Messdaten importieren“ im Menü öffnet jetzt direkt eine schlanke Formularseite, ohne Kandidatensichtung und Importverlauf der großen Import-&-Sync-Seite aufzubauen. Beide Seiten verwenden dasselbe Upload-Formular; die neue Seite zeigt den Status der Hintergrundaufgabe.
- Der CSV-Upload für bestehende Prüfweb-Prüfungen wird vor dem Aufbau der umfangreichen Importübersicht als Hintergrundaufgabe vorgemerkt. Der Button zeigt während des Uploads einen Ladezustand.
- Die Übersicht lädt offene Prüfungen nur noch einmal, beschränkt die Messdatenliste auf manuelle Prüfungen und liest keine ungenutzten Messdaten-JSONs mehr mit.
- Revisionen: Prüfapp `87db3c7`, `29a6346`; Ceneos PHP Base `a631489` (unverändert).

## 25.09.2026 – Kommentierte Prüf-Speicherplätze je Gerät

- Im Geräteformular ergänzt „Weiterer Speicherplatz“ eine zusätzliche Zeile mit Speicherplatznummer und optionalem Kommentar, etwa „PSU links“ und „PSU rechts“. Hinzufügen und Entfernen aktualisieren per HTMX nur diesen Bereich; andere Formulareingaben bleiben erhalten.
- Bis zu acht Plätze werden am Gerät gespeichert und in der Geräteansicht sowie als Auswahlhilfe bei der Prüfung angezeigt. Bestehende Speicherplatznummern und die Import-Zuordnung bleiben erhalten.
- Revisionen: Prüfapp `60466fa`, Ceneos PHP Base `a631489`.

## 25.09.2026 – Schutzklassen-Auswahl mit Beispielbildern wiederhergestellt

- Die Karten für SK I, II, III und Kabel zeigen wieder passende Stecker-, Anschluss- und Leitungsbeispiele mit Bildunterschriften. Der CEE-Drehstrom-Sonderfall ist ebenfalls direkt sichtbar.
- Die Beispiele werden im HTML gerendert und bleiben auch ohne nachträgliche JavaScript-Umformung sichtbar. Die Auswahl bleibt auf kleinen Bildschirmen responsiv.
- Revisionen: Prüfapp `e42371c`, Ceneos PHP Base `a631489`.

## 25.09.2026 – Hersteller- und Modellauswahl wieder aktiv

- Hersteller und Modell werden im Geräteformular wieder als durchsuchbare Auswahl initialisiert, auch in bearbeiteten Gerätekarten.
- Modellvorschläge folgen dem gewählten Hersteller; bestehende Eingaben bleiben bei der Aktualisierung erhalten.
- Revisionen: Prüfapp `98a4346`, Ceneos PHP Base `a631489`.

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
