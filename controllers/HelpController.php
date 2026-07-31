<?php

class HelpController
{
    public static function index(array $params, bool $isHx): array
    {
        $branding = get_branding();

        $content = render_template('help.php', [
            'branding' => $branding,
            'materials' => self::materials(),
        ]);

        $body = render_template('layout.php', [
            'title' => 'Hilfe & Anleitung',
            'content' => $content,
            'branding' => $branding,
        ]);

        return [200, [], $body];
    }

    private static function materials(): array
    {
        $root = app_data_root() . '/materials'; $items = [];
        foreach (glob($root . '/*') ?: [] as $path) {
            if (!is_file($path) || !in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), ['pdf', 'pptx', 'odt', 'ots'], true)) continue;
            $items[] = ['name' => basename($path), 'url' => url_for('hilfe/dokument/' . rawurlencode(basename($path)))];
        }
        usort($items, static fn(array $a, array $b): int => strnatcasecmp($a['name'], $b['name']));
        return $items;
    }

    public static function document(array $params, bool $isHx): array
    {
        if (!current_user()) return [403, [], ''];
        $name = basename(rawurldecode((string) ($params['file'] ?? '')));
        if ($name === '' || !in_array(strtolower(pathinfo($name, PATHINFO_EXTENSION)), ['pdf', 'pptx', 'odt', 'ots'], true)) return [404, [], 'Dokument nicht gefunden'];
        $root = realpath(app_data_root() . '/materials');
        $path = $root !== false ? realpath($root . DIRECTORY_SEPARATOR . $name) : false;
        if ($path === false || !str_starts_with($path, $root . DIRECTORY_SEPARATOR) || !is_file($path)) return [404, [], 'Dokument nicht gefunden'];
        $types = ['pdf' => 'application/pdf', 'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation', 'odt' => 'application/vnd.oasis.opendocument.text', 'ots' => 'application/vnd.oasis.opendocument.spreadsheet'];
        return [200, ['Content-Type' => $types[strtolower(pathinfo($name, PATHINFO_EXTENSION))], 'Content-Disposition' => 'inline; filename="' . addslashes($name) . '"'], (string) file_get_contents($path)];
    }
}
