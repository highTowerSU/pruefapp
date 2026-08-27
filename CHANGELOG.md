# Änderungsprotokoll

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
