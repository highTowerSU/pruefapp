<?php

require_once __DIR__ . '/../lib/MoodleImportService.php';

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

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            if (isset($_POST['mapping_submitted'])) {
                $rows = self::decodeRowsPayload($_POST['rows_payload'] ?? '');
                $header = self::decodeHeaderPayload($_POST['header_payload'] ?? '');

                if ($rows === null || $header === null) {
                    $_SESSION['fehlermeldung'] = 'Importdaten konnten nicht gelesen werden. Bitte starte den Import erneut.';

                    return [303, ['Location' => url_for('kurse/' . $kurs->id . '/teilnehmer/import')], ''];
                }

                $mapping = self::sanitizeMapping($_POST['mapping'] ?? [], $header);
                $result = self::importRows($kurs, $rows, $mapping);

                if ($result['imported'] > 0) {
                    $message = $result['imported'] === 1
                        ? '1 Teilnehmer wurde importiert.'
                        : sprintf('%d Teilnehmer wurden importiert.', $result['imported']);
                    $_SESSION['meldung'] = $message;
                } else {
                    $_SESSION['meldung'] = 'Es wurden keine Teilnehmer importiert.';
                }

                if ($result['failed'] > 0) {
                    $errorMessage = $result['failed'] === 1
                        ? '1 Zeile konnte nicht importiert werden.'
                        : sprintf('%d Zeilen konnten nicht importiert werden.', $result['failed']);

                    if (!empty($result['errors'])) {
                        $errorMessage .= ' ' . implode(' ', $result['errors']);
                    }

                    $_SESSION['fehlermeldung'] = $errorMessage;
                } else {
                    unset($_SESSION['fehlermeldung']);
                }

                return [303, ['Location' => url_for('kurse/' . $kurs->id . '/teilnehmer')], ''];
            }

            $parsed = self::parseUploadedCsv($_FILES['csv'] ?? null);
            if ($parsed === null) {
                $_SESSION['fehlermeldung'] = 'CSV-Datei konnte nicht gelesen werden.';

                return [303, ['Location' => url_for('kurse/' . $kurs->id . '/teilnehmer/import')], ''];
            }

            $fieldLabels = self::fieldLabels();
            $initialMapping = [];
            foreach (array_keys($fieldLabels) as $fieldKey) {
                $guess = self::guessColumn($parsed['header'], $fieldKey);
                if ($guess !== null) {
                    $initialMapping[$fieldKey] = $guess;
                }
            }

            $content = render_template('import_mapping.php', [
                'kurs' => $kurs,
                'headers' => $parsed['header'],
                'previewRows' => array_slice($parsed['rows'], 0, 5),
                'rowCount' => count($parsed['rows']),
                'fieldLabels' => $fieldLabels,
                'initialMapping' => $initialMapping,
                'rowsPayload' => self::encodeRowsPayload($parsed['rows']),
                'headerPayload' => self::encodeHeaderPayload($parsed['header']),
            ]);

            $body = render_template('layout.php', [
                'title' => 'Import – ' . $kurs->name,
                'content' => $content,
            ]);

            return [200, [], $body];
        }

        $content = render_template('import_form.php', ['kurs' => $kurs]);
        $body = render_template('layout.php', [
            'title' => 'Import – ' . $kurs->name,
            'content' => $content,
        ]);

        return [200, [], $body];
    }

    public static function moodleImport(array $params, bool $isHx): array
    {
        $kurs = self::findCourse($params);
        if ($kurs === null) {
            return self::notFoundResponse();
        }

        $teilnehmer = self::participantsForCourse($kurs->id);
        $courseShortname = trim((string) ($kurs->moodle_course_shortname ?? ''));
        $courseRole = trim((string) ($kurs->moodle_course_role ?? 'student'));
        if ($courseRole === '') {
            $courseRole = 'student';
        }
        $service = new MoodleImportService();
        $status = $service->getStatus();

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            if (!$service->canImport()) {
                $_SESSION['fehlermeldung'] = 'Der Moodle-Import ist nicht korrekt konfiguriert. Bitte überprüfe den Pfad zum Moodle-Upload-Skript.';
            } elseif (count($teilnehmer) === 0) {
                $_SESSION['meldung'] = 'Es sind keine Teilnehmer für den Moodle-Import vorhanden.';
                unset($_SESSION['fehlermeldung']);
            } else {
                try {
                    $result = $service->importParticipants(
                        $teilnehmer,
                        $courseShortname !== '' ? $courseShortname : null,
                        $courseShortname !== '' ? $courseRole : null
                    );

                    if ($result['exit_code'] === 0) {
                        $count = count($teilnehmer);
                        $message = $count === 1
                            ? '1 Teilnehmer wurde an Moodle übergeben.'
                            : sprintf('%d Teilnehmer wurden an Moodle übergeben.', $count);
                        if ($courseShortname !== '') {
                            $message .= ' Zielkurs: ' . $courseShortname . '.';
                        }
                        if (!empty($result['output'])) {
                            $message .= ' ' . implode(' ', array_map('trim', $result['output']));
                        }
                        $_SESSION['meldung'] = $message;
                        unset($_SESSION['fehlermeldung']);
                    } else {
                        $errorMessage = 'Moodle-Import fehlgeschlagen. Rückgabecode: ' . $result['exit_code'] . '.';
                        if (!empty($result['output'])) {
                            $errorMessage .= ' Ausgabe: ' . implode(' ', array_map('trim', $result['output']));
                        }
                        $_SESSION['fehlermeldung'] = $errorMessage;
                    }
                } catch (\RuntimeException $exception) {
                    $_SESSION['fehlermeldung'] = $exception->getMessage();
                }
            }

            return [303, ['Location' => url_for('kurse/' . $kurs->id . '/teilnehmer/moodle')], ''];
        }

        $content = render_template('moodle_import.php', [
            'kurs' => $kurs,
            'teilnehmer' => $teilnehmer,
            'status' => $status,
            'canImport' => $service->canImport(),
        ]);

        $body = render_template('layout.php', [
            'title' => 'Moodle-Import – ' . $kurs->name,
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
        $courseShortname = trim((string) ($kurs->moodle_course_shortname ?? ''));
        $courseRole = trim((string) ($kurs->moodle_course_role ?? 'student'));
        if ($courseRole === '') {
            $courseRole = 'student';
        }
        $includeCourse = $courseShortname !== '';

        $rows = [];
        $header = ['username', 'password', 'firstname', 'lastname', 'email', 'profile_field_birthdate', 'profile_field_birthplace'];
        if ($includeCourse) {
            $header[] = 'course1';
            if ($courseRole !== '') {
                $header[] = 'role1';
            }
        }
        $rows[] = $header;
        foreach ($teilnehmer as $tn) {
            $row = [
                $tn->benutzername,
                $tn->passwort,
                $tn->vorname,
                $tn->nachname,
                $tn->email,
                $tn->geburtsdatum,
                $tn->geburtsort,
            ];

            if ($includeCourse) {
                $row[] = $courseShortname;
                if ($courseRole !== '') {
                    $row[] = $courseRole;
                }
            }

            $rows[] = $row;
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
                    R::trash($teilnehmer);
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

            try {
                R::store($teilnehmer);
            } catch (\InvalidArgumentException $exception) {
                return self::jsonResponse(422, ['error' => $exception->getMessage()]);
            }

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
        return R::findAll('teilnehmer', 'kurs_id = ? ORDER BY nachname, vorname', [$kursId]);
    }

    private static function parseUploadedCsv(?array $file): ?array
    {
        if ($file === null) {
            return null;
        }

        $error = isset($file['error']) ? (int) $file['error'] : UPLOAD_ERR_NO_FILE;
        if ($error !== UPLOAD_ERR_OK || empty($file['tmp_name'])) {
            return null;
        }

        $handle = fopen($file['tmp_name'], 'r');
        if ($handle === false) {
            return null;
        }

        $rawHeader = fgetcsv($handle);
        if ($rawHeader === false) {
            fclose($handle);
            return null;
        }

        $header = self::normalizeHeader($rawHeader);
        $rows = [];

        while (($data = fgetcsv($handle)) !== false) {
            if ($data === [null] || $data === false) {
                continue;
            }

            $assoc = self::combineRow($header, $data);
            if ($assoc === null) {
                continue;
            }

            $rows[] = $assoc;
        }

        fclose($handle);

        return [
            'header' => $header,
            'rows' => $rows,
        ];
    }

    private static function normalizeHeader(array $rawHeader): array
    {
        $header = [];

        foreach ($rawHeader as $index => $value) {
            $name = trim((string) $value);
            if ($name === '') {
                $name = 'Spalte ' . ($index + 1);
            }

            $uniqueName = $name;
            $suffix = 2;
            while (in_array($uniqueName, $header, true)) {
                $uniqueName = $name . ' (' . $suffix . ')';
                $suffix++;
            }

            $header[] = $uniqueName;
        }

        return $header;
    }

    private static function combineRow(array $header, array $data): ?array
    {
        $columnCount = count($header);
        if (count($data) < $columnCount) {
            $data = array_pad($data, $columnCount, '');
        } elseif (count($data) > $columnCount) {
            $data = array_slice($data, 0, $columnCount);
        }

        $row = array_combine($header, $data);
        if ($row === false) {
            return null;
        }

        return $row;
    }

    private static function encodeRowsPayload(array $rows): string
    {
        return base64_encode(json_encode($rows, JSON_UNESCAPED_UNICODE));
    }

    private static function encodeHeaderPayload(array $header): string
    {
        return base64_encode(json_encode($header, JSON_UNESCAPED_UNICODE));
    }

    private static function decodeRowsPayload(string $payload): ?array
    {
        if ($payload === '') {
            return null;
        }

        $json = base64_decode($payload, true);
        if ($json === false) {
            return null;
        }

        $data = json_decode($json, true);
        if (!is_array($data)) {
            return null;
        }

        $rows = [];
        foreach ($data as $row) {
            if (is_array($row)) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    private static function decodeHeaderPayload(string $payload): ?array
    {
        if ($payload === '') {
            return null;
        }

        $json = base64_decode($payload, true);
        if ($json === false) {
            return null;
        }

        $data = json_decode($json, true);
        if (!is_array($data)) {
            return null;
        }

        $header = [];
        foreach ($data as $value) {
            if (is_string($value) && $value !== '') {
                $header[] = $value;
            }
        }

        return $header === [] ? null : $header;
    }

    private static function fieldLabels(): array
    {
        return [
            'vorname' => 'Vorname',
            'nachname' => 'Nachname',
            'geburtsdatum' => 'Geburtsdatum',
            'geburtsort' => 'Geburtsort',
            'benutzername' => 'Benutzername',
            'email' => 'E-Mail-Adresse',
            'passwort' => 'Passwort',
        ];
    }

    private static function guessColumn(array $header, string $fieldKey): ?string
    {
        $normalizedHeader = [];
        foreach ($header as $original) {
            $normalizedHeader[$original] = self::normalizeColumnName($original);
        }

        $candidates = self::fieldCandidates();
        $candidateList = $candidates[$fieldKey] ?? [];

        foreach ($candidateList as $candidate) {
            foreach ($normalizedHeader as $original => $normalized) {
                if ($normalized === $candidate) {
                    return $original;
                }
            }
        }

        foreach ($candidateList as $candidate) {
            foreach ($normalizedHeader as $original => $normalized) {
                if ($candidate !== '' && strpos($normalized, $candidate) !== false) {
                    return $original;
                }
            }
        }

        return null;
    }

    private static function normalizeColumnName(string $value): string
    {
        $normalized = strtolower(trim($value));
        $normalized = str_replace(['_', '-'], ' ', $normalized);
        $normalized = preg_replace('/\s+/', ' ', $normalized);

        return $normalized;
    }

    private static function fieldCandidates(): array
    {
        return [
            'vorname' => ['vorname', 'first name', 'firstname', 'given name'],
            'nachname' => ['nachname', 'last name', 'lastname', 'surname', 'family name'],
            'geburtsdatum' => ['geburtsdatum', 'birthdate', 'date of birth', 'geburtstag', 'profile field birthdate'],
            'geburtsort' => ['geburtsort', 'birthplace', 'place of birth', 'profile field birthplace'],
            'benutzername' => ['benutzername', 'username', 'user name', 'login'],
            'email' => ['email', 'e-mail', 'mail'],
            'passwort' => ['passwort', 'password'],
        ];
    }

    private static function sanitizeMapping(array $mapping, array $header): array
    {
        $allowed = array_keys(self::fieldLabels());
        $headerLookup = array_fill_keys($header, true);
        $clean = [];

        foreach ($allowed as $fieldKey) {
            $column = isset($mapping[$fieldKey]) ? (string) $mapping[$fieldKey] : '';
            if ($column !== '' && isset($headerLookup[$column])) {
                $clean[$fieldKey] = $column;
            } else {
                $clean[$fieldKey] = '';
            }
        }

        return $clean;
    }

    private static function importRows(\RedBeanPHP\OODBBean $kurs, array $rows, array $mapping): array
    {
        $result = [
            'imported' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $values = [];
            foreach ($mapping as $field => $column) {
                if ($column === '' || !array_key_exists($column, $row)) {
                    continue;
                }

                $values[$field] = trim((string) ($row[$column] ?? ''));
            }

            $hasData = false;
            foreach ($values as $value) {
                if ($value !== '') {
                    $hasData = true;
                    break;
                }
            }

            if (!$hasData) {
                continue;
            }

            $teilnehmer = R::dispense('teilnehmer');
            $teilnehmer->kurs = $kurs;

            if (isset($values['vorname'])) {
                $teilnehmer->vorname = $values['vorname'];
            }

            if (isset($values['nachname'])) {
                $teilnehmer->nachname = $values['nachname'];
            }

            if (isset($values['geburtsdatum'])) {
                $teilnehmer->geburtsdatum = $values['geburtsdatum'];
            }

            if (isset($values['geburtsort'])) {
                $teilnehmer->geburtsort = $values['geburtsort'];
            }

            if (isset($values['benutzername'])) {
                $teilnehmer->benutzername = $values['benutzername'];
            }

            if (isset($values['email'])) {
                $teilnehmer->email = $values['email'];
            }

            if (isset($values['passwort'])) {
                $teilnehmer->passwort = $values['passwort'];
            }

            try {
                R::store($teilnehmer);
                $result['imported']++;
            } catch (\InvalidArgumentException $exception) {
                $result['failed']++;
                $result['errors'][] = $exception->getMessage();
            }
        }

        $result['errors'] = array_values(array_unique($result['errors']));

        return $result;
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
