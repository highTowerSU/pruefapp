# Änderungsprotokoll

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

Nutzerrelevante Änderungen erscheinen zusätzlich als persönliche „Was ist neu?“-Benachrichtigung und unter **Downloads → Was ist neu?**.
