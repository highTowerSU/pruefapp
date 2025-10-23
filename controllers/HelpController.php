<?php

class HelpController
{
    public static function index(array $params, bool $isHx): array
    {
        $branding = get_branding();

        $content = render_template('help.php', [
            'branding' => $branding,
        ]);

        $body = render_template('layout.php', [
            'title' => 'Hilfe & Anleitung',
            'content' => $content,
            'branding' => $branding,
        ]);

        return [200, [], $body];
    }
}
