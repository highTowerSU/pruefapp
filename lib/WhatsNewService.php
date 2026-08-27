<?php

declare(strict_types=1);

use Ceneos\PhpBase\Notification\ReleaseNotePublisher;

final class WhatsNewService
{
    /** @return list<array{id:string,date:string,title:string,items:list<string>}> */
    public static function entries(): array
    {
        return [[
            'id' => '2026-08-27-candidate-leading-zeroes',
            'date' => '27.08.2026',
            'title' => 'Kandidaten: Speicherplätze mit führenden Nullen',
            'items' => [
                'Speicherplätze wie 3 und 003 werden als gleicher Wert erkannt und nicht mehr als Widerspruch angezeigt.',
                'Gleiche CSV- und Prüfweb-Prüfungen werden damit automatisch zusammengeführt.',
            ],
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
        foreach (self::entries() as $entry) {
            ReleaseNotePublisher::publishForUser(
                (int) $user->id,
                $entry['id'],
                'Was ist neu? ' . $entry['title'],
                implode(' ', array_slice($entry['items'], 0, 2)),
                url_for('downloads#whats-new')
            );
        }
    }
}
