<?php

declare(strict_types=1);

use RedBeanPHP\R;

final class WhatsNewChecklistService
{
    /** @param list<array{id:string}> $entries @return array<string,bool> */
    public static function checkedByEntry(int $userId, array $entries): array
    {
        if ($userId < 1) return [];
        $ids = array_values(array_filter(array_map(static fn(array $entry): string => (string) ($entry['id'] ?? ''), $entries)));
        if ($ids === []) return [];
        $marks = implode(',', array_fill(0, count($ids), '?'));
        $rows = R::getCol('SELECT release_id FROM whatsnewreceipt WHERE oauthuser_id = ? AND release_id IN (' . $marks . ')', [$userId, ...$ids]);
        return array_fill_keys(array_map('strval', $rows), true);
    }

    public static function markChecked(int $userId, string $releaseId): void
    {
        if ($userId < 1 || trim($releaseId) === '') return;
        $row = R::findOne('whatsnewreceipt', ' oauthuser_id = ? AND release_id = ? ', [$userId, $releaseId]) ?? R::dispense('whatsnewreceipt');
        $row->oauthuser_id = $userId;
        $row->release_id = $releaseId;
        $row->checked_at = date(DATE_ATOM);
        R::store($row);
    }

    /** @param list<array{id:string}> $entries */
    public static function markAllChecked(int $userId, array $entries): void
    {
        foreach ($entries as $entry) self::markChecked($userId, (string) ($entry['id'] ?? ''));
    }
}
