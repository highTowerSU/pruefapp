#!/usr/bin/env php
<?php

declare(strict_types=1);

$source = realpath($argv[1] ?? '');
$target = $argv[2] ?? getcwd() . '/altbestand-import.jsonl';
if ($source === false || !is_dir($source)) {
    fwrite(STDERR, "Verwendung: php bin/build_legacy_import.php /pfad/zu/2023-2024 [ziel.jsonl]\n");
    exit(2);
}
$out = fopen($target, 'wb');
if ($out === false) throw new RuntimeException('Zieldatei konnte nicht angelegt werden.');
$count = 0;
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $file) {
    if (!$file->isFile() || strtolower($file->getExtension()) !== 'json' || strtolower($file->getBasename()) === 'pruefungen.json') continue;
    try { $data = json_decode((string) file_get_contents($file->getPathname()), true, 512, JSON_THROW_ON_ERROR); } catch (Throwable) { continue; }
    $records = isset($data['number']) ? [$data] : (($data['resources']['data'] ?? null) ?: (is_array($data) && array_is_list($data) ? $data : []));
    foreach ($records as $record) {
        if (!is_array($record) || trim((string) ($record['number'] ?? '')) === '') continue;
        $record['_legacy_source'] = $file->getPathname();
        fwrite($out, json_encode($record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
        $count++;
    }
}
fclose($out);
echo "{$count} Datensätze geschrieben: {$target}\n";
