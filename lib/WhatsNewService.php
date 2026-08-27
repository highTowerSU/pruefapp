<?php

declare(strict_types=1);

use Ceneos\PhpBase\Notification\ReleaseNotePublisher;
use Ceneos\PhpBase\Update\ReleaseNotes;

final class WhatsNewService
{
    /** @return list<array{id:string,date:string,title:string,items:list<string>}> */
    public static function entries(): array
    {
        $entries = array_merge([[
            'id' => '2026-08-28-whats-new-order',
            'date' => '28.08.2026',
            'changed_at' => '2026-08-28T00:31:20+02:00',
            'title' => 'Was ist neu? in sinnvoller Reihenfolge',
            'items' => [
                'Aktuelle Prüfapp-Änderungen stehen jetzt vor älteren gemeinsamen Änderungen der Ceneos PHP Base.',
            ],
            'pruefapp_revision' => '85a51a6',
            'base_revision' => '4750c8f',
        ], [
            'id' => '2026-08-28-benning-headerless-csv',
            'date' => '28.08.2026',
            'changed_at' => '2026-08-28T00:29:07+02:00',
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
            'changed_at' => '2026-08-28T00:25:00+02:00',
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
            'changed_at' => '2026-08-28T00:25:49+02:00',
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
            'changed_at' => '2026-08-28T00:04:37+02:00',
            'title' => 'Kompakter Benachrichtigungsverlauf',
            'items' => [
                'Unter Benachrichtigungen werden zunächst die zehn neuesten Einträge angezeigt; ältere lassen sich aufklappen.',
            ],
            'pruefapp_revision' => 'a37fbc9',
            'base_revision' => '4750c8f',
        ], [
            'id' => '2026-08-28-candidate-toggle-all',
            'date' => '28.08.2026',
            'changed_at' => '2026-08-28T00:00:59+02:00',
            'title' => 'Kandidatenfälle gesammelt öffnen',
            'items' => [
                'Im Kandidatenlauf lassen sich alle offenen Fälle mit einem Klick aufklappen und anschließend wieder einklappen.',
            ],
            'pruefapp_revision' => 'cc6ace9',
            'base_revision' => '4750c8f',
        ], [
            'id' => '2026-08-27-candidate-source-data',
            'date' => '27.08.2026',
            'changed_at' => '2026-08-28T00:00:02+02:00',
            'title' => 'Unvollständige Importkandidaten prüfen',
            'items' => [
                'Bei unvollständigen Kandidaten lassen sich jetzt alle eingelesenen Quelldaten der konkreten CSV-/ODS-Zeile anzeigen.',
            ],
            'pruefapp_revision' => '734503c',
            'base_revision' => '4750c8f',
        ], [
            'id' => '2026-08-27-manual-failure-wins',
            'date' => '27.08.2026',
            'changed_at' => '2026-08-27T23:57:15+02:00',
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
            'changed_at' => '2026-08-27T23:54:58+02:00',
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
            'changed_at' => '2026-08-26T21:26:14+02:00',
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
        usort($entries, static fn(array $left, array $right): int => strcmp(
            (string) ($right['changed_at'] ?? $right['date'] ?? ''),
            (string) ($left['changed_at'] ?? $left['date'] ?? '')
        ));
        return $entries;
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
