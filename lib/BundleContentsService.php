<?php

declare(strict_types=1);

/** Layout shared by bundle pagination and its dedicated landscape contents table. */
final class BundleContentsService
{
    public const WIDTHS = [280, 32, 300, 100, 82];
    public const HEADERS = ['Raum / Standort', 'Seite', 'Gerät', 'Prüfung', 'Prüfdatum'];
    public const TOP = 505;
    public const BOTTOM = 30;

    /** @return list<list<array{cells:list<list<string>>,height:int,entry:int}>> */
    public static function layout(array $entries): array
    {
        $pages = [[]];
        $remaining = self::TOP - self::BOTTOM;
        foreach (array_values($entries) as $index => $entry) {
            $room = trim(implode(' · ', array_filter([
                $entry['site_name'] ?? '', $entry['building_name'] ?? '',
                $entry['floor_name'] ?? '', $entry['room_number'] ?? '',
            ], static fn($value): bool => trim((string) $value) !== ''))) ?: 'ohne Raum';
            $date = trim((string) ($entry['test_date'] ?? ''));
            $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
            if ($parsed && $parsed->format('Y-m-d') === $date) {
                $date = $parsed->format('d.m.Y');
            }
            $values = [$room, '00000', trim((string) ($entry['device_number'] ?? '') . ' · ' . (string) ($entry['device_name'] ?? ''), ' ·'), (string) ($entry['external_number'] ?? ''), $date];
            $cells = [];
            foreach ($values as $column => $value) {
                // Page references are filled after the contents pagination is known.
                $cells[] = $column === 1 ? [''] : self::wrap($value, self::WIDTHS[$column] - 12);
            }
            $lineCount = max(array_map('count', $cells));
            for ($offset = 0; $offset < $lineCount; $offset += 45) {
                $height = max(24, min(45, $lineCount - $offset) * 10 + 12);
                if ($height > $remaining && $pages[count($pages) - 1] !== []) {
                    $pages[] = [];
                    $remaining = self::TOP - self::BOTTOM;
                }
                $pages[count($pages) - 1][] = ['cells' => array_map(static fn(array $lines): array => array_slice($lines, $offset, 45), $cells), 'height' => $height, 'entry' => $index];
                $remaining -= $height;
            }
        }
        return $pages;
    }

    /** Conservative Helvetica widths in points at 8 pt, including wide glyphs. */
    public static function textWidth(string $text): float
    {
        $width = 0.0;
        foreach (mb_str_split($text) as $character) {
            $width += match (true) {
                str_contains(' ilI.,:;!\'|', $character) => 0.30,
                str_contains('frt()[]', $character) => 0.40,
                str_contains('MW@%&', $character) => 1.02,
                $character === 'm' => 0.86,
                $character === 'w' => 0.76,
                preg_match('/[A-ZÄÖÜ]/u', $character) === 1 => 0.82,
                default => 0.62,
            } * 8;
        }
        return $width;
    }

    /** @return list<string> */
    private static function wrap(string $text, float $width): array
    {
        $words = preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $lines = [];
        $line = '';
        foreach ($words as $word) {
            $candidate = $line === '' ? $word : $line . ' ' . $word;
            if (self::textWidth($candidate) <= $width) {
                $line = $candidate;
                continue;
            }
            if ($line !== '') {
                $lines[] = $line;
                $line = '';
            }
            foreach (mb_str_split($word) as $character) {
                if ($line !== '' && self::textWidth($line . $character) > $width) {
                    $lines[] = $line;
                    $line = '';
                }
                $line .= $character;
            }
        }
        if ($line !== '' || $lines === []) {
            $lines[] = $line;
        }
        return $lines;
    }
}
