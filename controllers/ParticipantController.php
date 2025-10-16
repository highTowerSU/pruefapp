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
            'apiUrl' => url_for('kurse/' . $kurs->id . '/teilnehmer/api'),
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

            $_SESSION['meldung'] = 'Teilnehmer wurden importiert.';

            return [303, ['Location' => url_for('kurse/' . $kurs->id . '/teilnehmer')], ''];
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

    public static function api(array $params, bool $isHx): array
    {
        $kurs = self::findCourse($params);
        if ($kurs === null) {
            return self::jsonResponse(404, ['error' => 'Kurs nicht gefunden']);
        }

        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        if ($method === 'GET') {
            $teilnehmer = self::participantsForCourse($kurs->id);
            $payload = array_map([self::class, 'participantToArray'], $teilnehmer);

            return self::jsonResponse(200, array_values($payload));
        }

        if ($method === 'POST' && isset($_GET['delete'])) {
            $id = (int) $_GET['delete'];
            if ($id > 0) {
                $teilnehmer = R::load('teilnehmer', $id);
                if ($teilnehmer->id && (int) $teilnehmer->kurs_id === (int) $kurs->id) {
                    $teilnehmer->deleted = 1;
                    R::store($teilnehmer);
                }
            }

            return [204, [], ''];
        }

        if ($method === 'POST') {
            $input = file_get_contents('php://input') ?: '';
            $data = json_decode($input, true);
            if (!is_array($data)) {
                return self::jsonResponse(400, ['error' => 'Ungültige Daten']);
            }

            $teilnehmerId = isset($data['id']) ? (int) $data['id'] : 0;

            $isNew = $teilnehmerId === 0;

            if (!$isNew) {
                $teilnehmer = R::load('teilnehmer', $teilnehmerId);
                if (!$teilnehmer->id || (int) $teilnehmer->kurs_id !== (int) $kurs->id) {
                    return self::jsonResponse(404, ['error' => 'Teilnehmer nicht gefunden']);
                }
            } else {
                $teilnehmer = R::dispense('teilnehmer');
                $teilnehmer->kurs = $kurs;
                $teilnehmer->deleted = 0;
                $teilnehmer->passwort = generate_password();
                $teilnehmer->benutzername = '';
            }

            $teilnehmer->vorname = trim((string) ($data['vorname'] ?? ''));
            $teilnehmer->nachname = trim((string) ($data['nachname'] ?? ''));
            $teilnehmer->geburtsdatum = trim((string) ($data['geburtsdatum'] ?? ''));
            $teilnehmer->geburtsort = trim((string) ($data['geburtsort'] ?? ''));

            if (array_key_exists('email', $data)) {
                $teilnehmer->email = trim((string) $data['email']);
            } elseif (!isset($teilnehmer->email)) {
                $teilnehmer->email = '';
            }

            $shouldGenerateUsername = $teilnehmer->benutzername === ''
                && $teilnehmer->vorname !== ''
                && $teilnehmer->nachname !== '';

            if ($shouldGenerateUsername) {
                $teilnehmer->benutzername = generate_username($teilnehmer->vorname, $teilnehmer->nachname);
            }

            if ($teilnehmer->email === '' && $teilnehmer->benutzername !== '') {
                $teilnehmer->email = generate_email($teilnehmer->benutzername);
            }

            R::store($teilnehmer);

            return self::jsonResponse(200, self::participantToArray($teilnehmer));
        }

        return self::jsonResponse(405, ['error' => 'Methode nicht erlaubt']);
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

    private static function notFoundResponse(): array
    {
        return [404, [], '<h1>404 – Kurs nicht gefunden</h1>'];
    }

    private static function participantToArray(\RedBeanPHP\OODBBean $teilnehmer): array
    {
        return [
            'id' => (int) $teilnehmer->id,
            'vorname' => (string) $teilnehmer->vorname,
            'nachname' => (string) $teilnehmer->nachname,
            'geburtsdatum' => (string) $teilnehmer->geburtsdatum,
            'geburtsort' => (string) $teilnehmer->geburtsort,
            'benutzername' => (string) $teilnehmer->benutzername,
            'email' => (string) ($teilnehmer->email ?? ''),
        ];
    }

    private static function jsonResponse(int $status, $payload): array
    {
        return [
            $status,
            ['Content-Type' => 'application/json; charset=utf-8'],
            json_encode($payload, JSON_UNESCAPED_UNICODE),
        ];
    }
}
