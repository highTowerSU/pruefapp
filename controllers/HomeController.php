<?php

class HomeController
{
    public static function index(array $params, bool $isHx): array
    {
        // Bei HTMX-Anfragen direkt auf die Kursverwaltung umleiten
        if ($isHx) {
            return [200, ['HX-Redirect' => '/kurse'], ''];
        }

        $content = render_template('home.php');
        $body = render_template('layout.php', [
            'title' => 'Moodle Zugang',
            'content' => $content,
        ]);

        return [200, [], $body];
    }
}
