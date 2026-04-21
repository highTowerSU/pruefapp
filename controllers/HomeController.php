<?php

class HomeController
{
    public static function index(array $params, bool $isHx): array
    {
        // Bei HTMX-Anfragen direkt auf die Prüfauftragsübersicht umleiten
        if ($isHx) {
            return [200, ['HX-Redirect' => url_for('kurse')], ''];
        }

        $branding = get_branding();
        $content = render_template('home.php', [
            'branding' => $branding,
        ]);
        $body = render_template('layout.php', [
            'title' => $branding['app_title'],
            'content' => $content,
            'branding' => $branding,
        ]);

        return [200, [], $body];
    }
}
