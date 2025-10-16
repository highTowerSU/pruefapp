<?php

use \RedBeanPHP\R as R;

class ParticipantController
{
    public static function index(array $params, bool $isHx): array
    {
        $kurs = self::findCourse($params);
        if ($kurs === null) {
            return self::notFoundResponse();
        }

        $content = render_template('teilnehmer_table.php', [
            'kurs' => $kurs,
        ]);

        $scripts = render_template('teilnehmer_scripts.php', [
            'kursId' => $kurs->id,
        ]);

        $body = render_template('layout.php', [
            'title' => 'Teilnehmer – ' . $kurs->name,
            'content' => $content,
            'scripts' => $scripts,
        ]);

        return [200, [], $body];
    }

    public static function import(array $params, bool $isHx): array
    {
        $kurs = self::findCourse($params);
        if ($kurs === null) {
            return self::notFoundResponse();
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            self::handleCsvImport($kurs);
            self::handleManualImport($kurs);

            $_SESSION['meldung'] = 'Teilnehmer wurden importiert.';

            return [303, ['Location' => '/kurse/' . $kurs->id . '/teilnehmer'], ''];
        }

        $content = render_template('import_form.php', ['kurs' => $kurs]);
        $body = render_template('layout.php', [
            'title' => 'Import – ' . $kurs->name,
            'content' => $content,
        ]);

        return [200, [], $body];
    }

    public static function print(array $params, bool $isHx): array
    {
        $kurs = self::findCourse($params);
        if ($kurs === null) {
            return self::notFoundResponse();
        }

        $teilnehmer = self::participantsForCourse($kurs->id);
        $content = render_template('druckliste.php', [
            'kurs' => $kurs,
            'nutzer' => $teilnehmer,
        ]);

        $body = render_template('layout.php', [
            'title' => 'Druck – ' . $kurs->name,
            'content' => $content,
        ]);

        return [200, [], $body];
    }

    public static function export(array $params, bool $isHx): array
    {
        $kurs = self::findCourse($params);
        if ($kurs === null) {
            return self::notFoundResponse();
        }

        $teilnehmer = self::participantsForCourse($kurs->id);

        $rows = [];
        $rows[] = ['username', 'password', 'firstname', 'lastname', 'email', 'profile_field_birthdate', 'profile_field_birthplace'];
        foreach ($teilnehmer as $tn) {
            $rows[] = [
                $tn->benutzername,
                $tn->passwort,
                $tn->vorname,
                $tn->nachname,
                $tn->email,
                $tn->geburtsdatum,
                $tn->geburtsort,
            ];
        }

        $fh = fopen('php://temp', 'r+');
        foreach ($rows as $row) {
            fputcsv($fh, $row);
        }
        rewind($fh);
        $csv = stream_get_contents($fh);
        fclose($fh);

        $filename = sprintf('moodle_export_kurs_%d.csv', $kurs->id);

        return [
            200,
            [
                'Content-Type' => 'text/csv; charset=utf-8',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ],
            $csv,
        ];
    }

    private static function findCourse(array $params): ?\RedBeanPHP\OODBBean
    {
        $id = isset($params['id']) ? (int) $params['id'] : 0;
        $kurs = R::load('kurs', $id);

        return $kurs->id ? $kurs : null;
    }

    private static function participantsForCourse(int $kursId): array
    {
        return R::findAll('teilnehmer', 'kurs_id = ? AND (deleted IS NULL OR deleted = 0) ORDER BY nachname, vorname', [$kursId]);
    }

    private static function handleCsvImport(\RedBeanPHP\OODBBean $kurs): void
    {
        if (empty($_FILES['csv']['tmp_name'])) {
            return;
        }

        $handle = fopen($_FILES['csv']['tmp_name'], 'r');
        if ($handle === false) {
            return;
        }

        $header = fgetcsv($handle);
        if (!$header) {
            fclose($handle);
            return;
        }

        while (($data = fgetcsv($handle)) !== false) {
            $row = array_combine($header, $data);
            if (!$row) {
                continue;
            }

            $teilnehmer = R::dispense('teilnehmer');
            $teilnehmer->vorname = trim($row['Vorname'] ?? '');
            $teilnehmer->nachname = trim($row['Nachname'] ?? '');
            $teilnehmer->geburtsdatum = trim($row['Geburtsdatum'] ?? '');
            $teilnehmer->geburtsort = trim($row['Geburtsort'] ?? '');
            $teilnehmer->benutzername = generate_username($teilnehmer->vorname, $teilnehmer->nachname);
            $teilnehmer->passwort = generate_password();
            $teilnehmer->email = generate_email($teilnehmer->benutzername);
            $teilnehmer->kurs = $kurs;
            $teilnehmer->deleted = 0;

            R::store($teilnehmer);
        }

        fclose($handle);
    }

    private static function handleManualImport(\RedBeanPHP\OODBBean $kurs): void
    {
        if (empty($_POST['manuell']) || !is_array($_POST['manuell'])) {
            return;
        }

        foreach ($_POST['manuell'] as $eintrag) {
            $vorname = trim($eintrag['vorname'] ?? '');
            $nachname = trim($eintrag['nachname'] ?? '');
            $geburtsdatum = trim($eintrag['geburtsdatum'] ?? '');

            if ($vorname === '' || $nachname === '' || $geburtsdatum === '') {
                continue;
            }

            $teilnehmer = R::dispense('teilnehmer');
            $teilnehmer->vorname = $vorname;
            $teilnehmer->nachname = $nachname;
            $teilnehmer->geburtsdatum = $geburtsdatum;
            $teilnehmer->geburtsort = trim($eintrag['geburtsort'] ?? '');
            $teilnehmer->benutzername = generate_username($teilnehmer->vorname, $teilnehmer->nachname);
            $teilnehmer->passwort = generate_password();
            $teilnehmer->email = generate_email($teilnehmer->benutzername);
            $teilnehmer->kurs = $kurs;
            $teilnehmer->deleted = 0;

            R::store($teilnehmer);
        }
    }

    private static function notFoundResponse(): array
    {
        return [404, [], '<h1>404 – Kurs nicht gefunden</h1>'];
    }
}
