<?php

declare(strict_types=1);

use Ceneos\PhpBase\Notification\ReleaseNotePublisher;

final class WhatsNewService
{
    /** @return list<array{id:string,date:string,title:string,items:list<string>}> */
    public static function entries(): array
    {
        return [[
            'id' => '2026-08-28-whats-new-revision-badges',
            'date' => '28.08.2026',
            'title' => 'Revisionen direkt sichtbar',
            'items' => [
                'Die beteiligten Prüfapp- und Base-Revisionen stehen jetzt direkt als Tags an den Änderungen.',
            ],
            'pruefapp_revision' => '5d9a95f',
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
        ]];
    }

    public static function publishForCurrentUser(): void
    {
        $user = current_user();
        if ($user === null) {
            return;
        }
        $userId = (int) $user->id;
        $releaseId = '2026-08-28-whats-new-revision-badges';
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
