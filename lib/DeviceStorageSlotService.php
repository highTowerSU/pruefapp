<?php

declare(strict_types=1);

final class DeviceStorageSlotService
{
    public const MAX_SLOTS = 8;

    /** @return list<array{number:string,comment:string}> */
    public static function fromDevice(object $device): array
    {
        $numbers = json_decode((string) ($device->storage_slots_json ?? '[]'), true);
        if (!is_array($numbers)) {
            $numbers = [];
        }
        if ($numbers === [] && trim((string) ($device->storage_slot ?? '')) !== '') {
            $numbers = [(string) $device->storage_slot];
        }
        $comments = json_decode((string) ($device->storage_slot_notes_json ?? '{}'), true);
        if (!is_array($comments)) {
            $comments = [];
        }
        $rows = [];
        foreach ($numbers as $number) {
            if (!is_scalar($number)) {
                continue;
            }
            $number = trim((string) $number);
            if ($number === '') {
                continue;
            }
            $comment = $comments[$number] ?? '';
            $rows[] = ['number' => $number, 'comment' => is_scalar($comment) ? (string) $comment : ''];
        }
        return $rows ?: [['number' => '', 'comment' => '']];
    }

    /** @return list<array{number:string,comment:string}> */
    public static function fromFields(mixed $numbers, mixed $comments): array
    {
        if (!is_array($numbers) || !is_array($comments)) {
            throw new InvalidArgumentException('Die Speicherplatzangaben sind ungültig.');
        }
        if (count($numbers) > self::MAX_SLOTS || count($comments) > self::MAX_SLOTS) {
            throw new InvalidArgumentException('Es können maximal acht Prüf-Speicherplätze je Gerät hinterlegt werden.');
        }
        $rows = [];
        $comments = array_values($comments);
        foreach (array_values($numbers) as $index => $number) {
            $comment = $comments[$index] ?? '';
            if (!is_scalar($number) || !is_scalar($comment)) {
                throw new InvalidArgumentException('Die Speicherplatzangaben sind ungültig.');
            }
            $rows[] = ['number' => trim((string) $number), 'comment' => trim((string) $comment)];
        }
        return $rows ?: [['number' => '', 'comment' => '']];
    }

    /** @return list<array{number:string,comment:string}> */
    public static function fromPost(array $post): array
    {
        if (array_key_exists('storage_slot_numbers', $post)) {
            $rows = self::fromFields($post['storage_slot_numbers'], $post['storage_slot_comments'] ?? []);
        } else {
            $legacy = trim((string) ($post['storage_slots'] ?? ''));
            $numbers = $legacy === '' ? [] : (preg_split('/[,;\s]+/u', $legacy) ?: []);
            $rows = array_map(static fn(string $number): array => ['number' => $number, 'comment' => ''], $numbers);
        }
        $saved = [];
        $seen = [];
        foreach ($rows as $row) {
            $number = $row['number'];
            $comment = $row['comment'];
            if ($number === '') {
                if ($comment !== '') {
                    throw new InvalidArgumentException('Ein Kommentar benötigt eine Speicherplatznummer.');
                }
                continue;
            }
            if (mb_strlen($number) > 40 || mb_strlen($comment) > 240) {
                throw new InvalidArgumentException('Speicherplatznummern dürfen höchstens 40, Kommentare höchstens 240 Zeichen haben.');
            }
            $key = preg_match('/^\d+$/', $number) === 1 ? (ltrim($number, '0') ?: '0') : mb_strtoupper($number);
            if (isset($seen[$key])) {
                throw new InvalidArgumentException('Jeder Prüf-Speicherplatz darf nur einmal eingetragen werden.');
            }
            $seen[$key] = true;
            $saved[] = $row;
        }
        if (count($saved) > self::MAX_SLOTS) {
            throw new InvalidArgumentException('Es können maximal acht Prüf-Speicherplätze je Gerät hinterlegt werden.');
        }
        return $saved;
    }
}
