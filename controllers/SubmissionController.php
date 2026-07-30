<?php

use \RedBeanPHP\R as R;

class SubmissionController
{
    public static function form(array $params, bool $isHx): array
    {
        $token = $params['token'] ?? '';
        $link = R::findOne('uebermittlungslink', ' token = ? ', [$token]);

        if (!$link) {
            return [404, [], '<h1>Ungültiger Link</h1>'];
        }

        $branding = get_company_branding((int) ($link->company_id ?? 0));
        if ($branding === null) {
            return [410, [], '<h1>Dieser Link ist nicht mehr gültig.</h1>'];
        }

        $kurs = $link->kurs;
        $showThankYou = isset($_GET['danke']);

        if (!$link->aktiv && !$showThankYou) {
            return self::renderPage(
                'Teilnehmerdaten übermitteln',
                '<div class="alert alert-warning">Dieser Übermittlungslink ist derzeit deaktiviert.</div>',
                $branding,
                403
            );
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $hatEintraege = false;
            foreach ($_POST['person'] ?? [] as $eintrag) {
                $vorname = trim($eintrag['vorname'] ?? '');
                $nachname = trim($eintrag['nachname'] ?? '');
                $geburtsdatum = trim($eintrag['geburtsdatum'] ?? '');

                if ($vorname === '' || $nachname === '' || $geburtsdatum === '') {
                    continue;
                }

                $teilnehmer = R::dispense('teilnehmer');
                $teilnehmer->vorname = $vorname;
                $teilnehmer->nachname = $nachname;
                $teilnehmer->geburtsdatum = normalize_birthdate($geburtsdatum);
                $teilnehmer->geburtsort = $kurs->feld_geburtsort_aktiv ? trim($eintrag['geburtsort'] ?? '') : '';
                $teilnehmer->firma = $kurs->feld_firma_aktiv ? trim($eintrag['firma'] ?? '') : '';
                $teilnehmer->email = $kurs->feld_email_aktiv ? trim($eintrag['email'] ?? '') : '';
                $teilnehmer->benutzername = generate_username($teilnehmer->vorname, $teilnehmer->nachname);
                $teilnehmer->passwort = generate_password();
                if ($teilnehmer->email === '' && $teilnehmer->benutzername !== '') {
                    $teilnehmer->email = generate_email($teilnehmer->benutzername);
                }
                $teilnehmer->quelle = 'extern';
                $teilnehmer->kurs = $kurs;

                try {
                    R::store($teilnehmer);
                    $hatEintraege = true;
                } catch (\InvalidArgumentException $exception) {
                    continue;
                }
            }

            if ($hatEintraege) {
                $link->aktiv = 0;
                R::store($link);
            }

            return [303, ['Location' => url_for('uebermitteln/' . $link->token . '?danke=1')], ''];
        }

        $content = $showThankYou
            ? '<div class="alert alert-success">Vielen Dank. Die Daten wurden übermittelt.</div>'
            : render_template('uebermitteln_form.php', ['kurs' => $kurs]);

        $body = render_template('layout.php', [
            'title' => 'Teilnehmerdaten übermitteln',
            'content' => $content,
            'branding' => $branding,
        ]);

        return [200, [], $body];
    }

    private static function renderPage(string $title, string $content, array $branding, int $status): array
    {
        return [
            $status,
            [],
            render_template('layout.php', [
                'title' => $title,
                'content' => $content,
                'branding' => $branding,
            ]),
        ];
    }
}
