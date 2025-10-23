<?php

function app_version_info(): array
{
    static $cache = null;

    if ($cache !== null) {
        return $cache;
    }

    $version = env_value('APP_VERSION');
    if ($version === null) {
        $version = detect_app_version_from_json(dirname(__DIR__) . '/composer.json');
    }
    if ($version === null) {
        $version = detect_app_version_from_json(dirname(__DIR__) . '/package.json');
    }

    $commit = env_value('APP_GIT_COMMIT');
    if ($commit === null) {
        $commit = detect_git_commit();
    }

    $buildDate = env_value('APP_BUILD_DATE');

    $cache = [
        'version' => $version ?? 'dev',
        'commit' => $commit,
        'build_date' => $buildDate,
    ];

    return $cache;
}

function app_version(): string
{
    $info = app_version_info();

    return (string) ($info['version'] ?? 'dev');
}

function app_version_display_data(): array
{
    $info = app_version_info();
    $buildDateIso = null;
    $buildDateHuman = null;

    if (!empty($info['build_date'])) {
        try {
            $date = new \DateTimeImmutable($info['build_date']);
            $buildDateIso = $date->format(\DateTimeInterface::ATOM);
            $buildDateHuman = $date->format('d.m.Y H:i');
        } catch (\Throwable $throwable) {
            $buildDateIso = null;
            $buildDateHuman = null;
        }
    }

    return [
        'version' => (string) ($info['version'] ?? 'dev'),
        'commit' => $info['commit'] !== null ? substr((string) $info['commit'], 0, 12) : null,
        'build_date_iso' => $buildDateIso,
        'build_date_human' => $buildDateHuman,
    ];
}

function detect_app_version_from_json(string $path): ?string
{
    if (!is_file($path)) {
        return null;
    }

    $contents = @file_get_contents($path);
    if ($contents === false) {
        return null;
    }

    $data = json_decode($contents, true);
    if (!is_array($data)) {
        return null;
    }

    $version = $data['version'] ?? null;
    if (!is_string($version)) {
        return null;
    }

    $version = trim($version);

    return $version === '' ? null : $version;
}

function detect_git_commit(): ?string
{
    $baseDir = dirname(__DIR__);
    $gitDir = $baseDir . DIRECTORY_SEPARATOR . '.git';

    if (!is_dir($gitDir)) {
        return null;
    }

    $headFile = $gitDir . DIRECTORY_SEPARATOR . 'HEAD';
    $headContents = @file_get_contents($headFile);
    if ($headContents === false) {
        return null;
    }

    $headContents = trim($headContents);
    if ($headContents === '') {
        return null;
    }

    $hash = null;
    if (strpos($headContents, 'ref:') === 0) {
        $ref = trim(substr($headContents, 4));
        $ref = str_replace(['\\'], '/', $ref);
        $ref = ltrim($ref, '/');
        if ($ref === '' || strpos($ref, '..') !== false) {
            return null;
        }
        $refPath = $gitDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $ref);
        if (!is_file($refPath)) {
            return null;
        }
        $hash = @file_get_contents($refPath);
        if ($hash === false) {
            return null;
        }
    } else {
        $hash = $headContents;
    }

    $hash = trim((string) $hash);
    if ($hash === '') {
        return null;
    }

    return substr($hash, 0, 12);
}
