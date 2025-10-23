<?php

require_once __DIR__ . '/../lib/MoodleImportService.php';
require_once __DIR__ . '/../lib/MoodleCourseService.php';

use \RedBeanPHP\R as R;

class ParticipantController
{
    public static function index(array $params, bool $isHx): array
    {
        $kurs = self::findCourse($params);
        if ($kurs === null) {
            return self::notFoundResponse();
        }

        $canManageParticipants = current_user_can_manage_participants();
        $teilnehmer = self::participantsForCourse($kurs->id);

        $content = render_template('teilnehmer_table.php', [
            'kurs' => $kurs,
            'teilnehmer' => $teilnehmer,
            'canManageParticipants' => $canManageParticipants,
        ]);

        $scripts = render_template('teilnehmer_scripts.php');

        $body = render_template('layout.php', [
            'title' => 'Teilnehmer – ' . $kurs->name,
            'content' => $content,
            'scripts' => $scripts,
        ]);

        return [200, [], $body];
    }

    public static function newRow(array $params, bool $isHx): array
    {
        $kurs = self::findCourse($params);
        if ($kurs === null) {
            return [404, [], ''];
        }

        if (!$isHx) {
            return [400, [], ''];
        }

        if (!current_user_can_manage_participants()) {
            return [403, [], ''];
        }

        $values = self::participantFormDefaults();
        $html = self::renderParticipantEditRow($kurs, $values, true, [], null, true);

        return [200, [], $html];
    }

    public static function row(array $params, bool $isHx): array
    {
        $kurs = self::findCourse($params);
        if ($kurs === null) {
            return [404, [], ''];
        }

        $teilnehmer = self::findParticipantInCourse($kurs, $params);
        if ($teilnehmer === null) {
            return [404, [], ''];
        }

        $canManageParticipants = current_user_can_manage_participants();
        $html = self::renderParticipantRow($kurs, $teilnehmer, $canManageParticipants);

        return [200, [], $html];
    }

    public static function edit(array $params, bool $isHx): array
    {
        $kurs = self::findCourse($params);
        if ($kurs === null) {
            return [404, [], ''];
        }

        if (!$isHx) {
            return [400, [], ''];
        }

        if (!current_user_can_manage_participants()) {
            return [403, [], ''];
        }

        $teilnehmer = self::findParticipantInCourse($kurs, $params);
        if ($teilnehmer === null) {
            return [404, [], ''];
        }

        $values = self::participantFormDefaults($teilnehmer);
        $html = self::renderParticipantEditRow($kurs, $values, true, [], $teilnehmer, false);

        return [200, [], $html];
    }

    public static function store(array $params, bool $isHx): array
    {
        $kurs = self::findCourse($params);
        if ($kurs === null) {
            return [404, [], ''];
        }

        if (!current_user_can_manage_participants()) {
            return [403, [], ''];
        }

        if (!$isHx) {
            return [303, ['Location' => url_for('kurse/' . $kurs->id . '/teilnehmer')], ''];
        }

        $form = self::processParticipantForm($kurs, $_POST);
        if ($form['errors'] !== []) {
            $html = self::renderParticipantEditRow($kurs, $form['values'], true, $form['errors'], null, true);

            return [422, [], $html];
        }

        $teilnehmer = R::dispense('teilnehmer');
        $teilnehmer->kurs = $kurs;
        $teilnehmer->benutzername = '';
        $teilnehmer->passwort = '';

        self::applyParticipantForm($teilnehmer, $form['normalized']);

        try {
            R::store($teilnehmer);
        } catch (\InvalidArgumentException $exception) {
            $form['errors']['general'] = $exception->getMessage();
            $html = self::renderParticipantEditRow($kurs, $form['values'], true, $form['errors'], null, true);

            return [422, [], $html];
        }

        $afterState = self::participantToArray($teilnehmer);
        audit_log('teilnehmer_angelegt', [
            'kurs_id' => (int) $kurs->id,
            'kurs_name' => (string) $kurs->name,
            'teilnehmer' => $afterState,
        ]);

        $html = self::renderParticipantRow($kurs, $teilnehmer, true);

        return [200, [], $html];
    }

    public static function update(array $params, bool $isHx): array
    {
        $kurs = self::findCourse($params);
        if ($kurs === null) {
            return [404, [], ''];
        }

        if (!current_user_can_manage_participants()) {
            return [403, [], ''];
        }

        $teilnehmer = self::findParticipantInCourse($kurs, $params);
        if ($teilnehmer === null) {
            return [404, [], ''];
        }

        if (!$isHx) {
            return [303, ['Location' => url_for('kurse/' . $kurs->id . '/teilnehmer')], ''];
        }

        $beforeState = self::participantToArray($teilnehmer);

        $form = self::processParticipantForm($kurs, $_POST);
        if ($form['errors'] !== []) {
            $html = self::renderParticipantEditRow($kurs, $form['values'], true, $form['errors'], $teilnehmer, false);

            return [422, [], $html];
        }

        self::applyParticipantForm($teilnehmer, $form['normalized']);

        try {
            R::store($teilnehmer);
        } catch (\InvalidArgumentException $exception) {
            $form['errors']['general'] = $exception->getMessage();
            $html = self::renderParticipantEditRow($kurs, $form['values'], true, $form['errors'], $teilnehmer, false);

            return [422, [], $html];
        }

        $afterState = self::participantToArray($teilnehmer);
        $changes = [];
        foreach ($afterState as $field => $value) {
            if ($field === 'id') {
                continue;
            }

            $beforeValue = $beforeState[$field] ?? null;
            if ($beforeValue !== $value) {
                $changes[$field] = [
                    'alt' => $beforeValue,
                    'neu' => $value,
                ];
            }
        }

        if ($changes !== []) {
            audit_log('teilnehmer_aktualisiert', [
                'kurs_id' => (int) $kurs->id,
                'kurs_name' => (string) $kurs->name,
                'teilnehmer_id' => $afterState['id'],
                'aenderungen' => $changes,
            ]);
        }

        $html = self::renderParticipantRow($kurs, $teilnehmer, true);

        return [200, [], $html];
    }

    public static function delete(array $params, bool $isHx): array
    {
        $kurs = self::findCourse($params);
        if ($kurs === null) {
            return [404, [], ''];
        }

        if (!current_user_can_manage_participants()) {
            return [403, [], ''];
        }

        $teilnehmer = self::findParticipantInCourse($kurs, $params);
        if ($teilnehmer === null) {
            return [404, [], ''];
        }

        $participantData = self::participantToArray($teilnehmer);
        R::trash($teilnehmer);

        audit_log('teilnehmer_geloescht', [
            'kurs_id' => (int) $kurs->id,
            'kurs_name' => (string) $kurs->name,
            'teilnehmer' => $participantData,
        ]);

        if ($isHx) {
            return [200, [], ''];
        }

        return [303, ['Location' => url_for('kurse/' . $kurs->id . '/teilnehmer')], ''];
    }

    public static function import(array $params, bool $isHx): array
    {
        $kurs = self::findCourse($params);
        if ($kurs === null) {
            return self::notFoundResponse();
        }

        if (!current_user_can_manage_participants()) {
            return forbidden_response();
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (isset($_POST['mapping_submitted'])) {
                $header = self::decodeHeaderPayload((string) ($_POST['header_payload'] ?? ''));
                $rows = self::decodeRowsPayload((string) ($_POST['rows_payload'] ?? ''));

                if ($header === null || $rows === null) {
                    $_SESSION['fehlermeldung'] = 'Zuordnung konnte nicht verarbeitet werden.';


                    return [303, ['Location' => url_for('kurse/' . $kurs->id . '/teilnehmer/import')], ''];
                }

                $mapping = self::sanitizeMapping($_POST['mapping'] ?? [], $header);
                $result = self::importRows($kurs, $rows, $mapping);

                $mappedColumns = [];
                foreach ($mapping as $field => $column) {
                    if ($column !== '') {
                        $mappedColumns[$field] = $column;
                    }
                }

                audit_log('teilnehmer_import', [
                    'kurs_id' => (int) $kurs->id,
                    'kurs_name' => (string) $kurs->name,
                    'anzahl_importiert' => (int) $result['imported'],
                    'anzahl_fehlgeschlagen' => (int) $result['failed'],
                    'zugeordnete_spalten' => $mappedColumns,
                ]);

                if ($result['imported'] > 0) {
                    $_SESSION['meldung'] = $result['imported'] === 1
                        ? '1 Teilnehmer wurde importiert.'
                        : sprintf('%d Teilnehmer wurden importiert.', $result['imported']);
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

            $selectedEncoding = self::sanitizeEncoding((string) ($_POST['encoding'] ?? ''));

            $parsed = self::parseUploadedCsv($_FILES['csv'] ?? null, $selectedEncoding);

            if ($parsed === null) {
                $_SESSION['import_selected_encoding'] = $selectedEncoding;
                $_SESSION['fehlermeldung'] = 'CSV-Datei konnte nicht gelesen werden.';

                return [303, ['Location' => url_for('kurse/' . $kurs->id . '/teilnehmer/import')], ''];
            }

            unset($_SESSION['import_selected_encoding']);

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

        $availableEncodings = self::availableEncodings();
        $selectedEncoding = $_SESSION['import_selected_encoding'] ?? 'utf-8';

        $content = render_template('import_form.php', [
            'kurs' => $kurs,
            'availableEncodings' => $availableEncodings,
            'selectedEncoding' => $selectedEncoding,
        ]);
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
        $importService = new MoodleImportService();
        $status = $importService->getStatus();
        $courseService = new MoodleCourseService();
        $webserviceStatus = $courseService->getWebserviceStatus();
        $commandPreview = null;
        $moodleCourseId = (int) ($kurs->moodle_course_id ?? 0);
        $moodleLookupError = null;

        if ($courseService->isWebserviceConfigured() && $courseShortname !== '') {
            try {
                $resolvedId = self::resolveMoodleCourseId($kurs, $courseService, false);
                if ($resolvedId !== null) {
                    $moodleCourseId = $resolvedId;
                }
            } catch (\Throwable $exception) {
                $moodleLookupError = $exception->getMessage();
            }
        }

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $action = $_POST['action'] ?? 'cli_import';

            if ($action !== 'cli_import') {
                return [400, [], ''];
            }

            if (!$importService->canImport()) {
                $_SESSION['fehlermeldung'] = 'Der Moodle-Import ist nicht korrekt konfiguriert. Bitte überprüfe den Pfad zum Moodle-Upload-Skript.';
            } elseif (count($teilnehmer) === 0) {
                $_SESSION['meldung'] = 'Es sind keine Teilnehmer für den Moodle-Import vorhanden.';
                unset($_SESSION['fehlermeldung']);
            } else {
                try {
                    $result = $importService->importParticipants(
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

        if ($importService->canImport() && count($teilnehmer) > 0) {
            $commandPreview = $importService->getCommandPreview();
        }

        $content = render_template('moodle_import.php', [
            'kurs' => $kurs,
            'teilnehmer' => $teilnehmer,
            'status' => $status,
            'canImport' => $importService->canImport(),
            'commandPreview' => $commandPreview,
            'webserviceStatus' => $webserviceStatus,
            'canFetchFromMoodle' => $courseService->isWebserviceConfigured() && $courseShortname !== '',
            'moodleCourseId' => $moodleCourseId > 0 ? $moodleCourseId : null,
            'moodleLookupError' => $moodleLookupError,
        ]);

        $body = render_template('layout.php', [
            'title' => 'Moodle-Import – ' . $kurs->name,
            'content' => $content,
        ]);

        return [200, [], $body];
    }

    public static function moodleFetch(array $params, bool $isHx): array
    {
        $kurs = self::findCourse($params);
        if ($kurs === null) {
            return self::notFoundResponse();
        }

        if (!current_user_can_manage_participants()) {
            return forbidden_response();
        }

        $service = new MoodleCourseService();
        if (!$service->isWebserviceConfigured()) {
            $_SESSION['fehlermeldung'] = 'Der Moodle-Webservice ist nicht konfiguriert. Bitte hinterlege eine Basis-URL und ein Token in den Einstellungen.';

            return [303, ['Location' => url_for('kurse/' . $kurs->id . '/teilnehmer/moodle')], ''];
        }

        try {
            $courseId = self::resolveMoodleCourseId($kurs, $service);
        } catch (\Throwable $exception) {
            $_SESSION['fehlermeldung'] = 'Moodle-Kurs konnte nicht ermittelt werden: ' . $exception->getMessage();

            return [303, ['Location' => url_for('kurse/' . $kurs->id . '/teilnehmer/moodle')], ''];
        }

        if ($courseId === null) {
            $_SESSION['fehlermeldung'] = 'Für diesen Kurs ist kein Moodle-Shortname hinterlegt. Bitte trage den Shortname in den Kurseinstellungen ein.';

            return [303, ['Location' => url_for('kurse/' . $kurs->id . '/teilnehmer/moodle')], ''];
        }

        try {
            $stats = self::synchronizeParticipantsFromMoodle($kurs, $service, $courseId, true);
            $_SESSION['meldung'] = 'Teilnehmer aus Moodle übernommen. ' . self::buildMoodleSyncSummary($stats);
            unset($_SESSION['fehlermeldung']);

            audit_log('moodle_teilnehmer_von_moodle_abgerufen', [
                'kurs_id' => (int) $kurs->id,
                'kurs_name' => (string) $kurs->name,
                'moodle_course_id' => $courseId,
                'moodle_course_shortname' => $kurs->moodle_course_shortname ?? '',
                'statistik' => $stats,
            ]);
        } catch (\Throwable $exception) {
            $_SESSION['fehlermeldung'] = 'Teilnehmer konnten nicht aus Moodle geladen werden: ' . $exception->getMessage();

            audit_log('moodle_teilnehmer_von_moodle_abruf_fehlgeschlagen', [
                'kurs_id' => (int) $kurs->id,
                'kurs_name' => (string) $kurs->name,
                'moodle_course_id' => $courseId,
                'fehlermeldung' => $exception->getMessage(),
            ]);
        }

        return [303, ['Location' => url_for('kurse/' . $kurs->id . '/teilnehmer/moodle')], ''];
    }

    public static function moodleSync(array $params, bool $isHx): array
    {
        $kurs = self::findCourse($params);
        if ($kurs === null) {
            return self::notFoundResponse();
        }

        if (!current_user_can_manage_participants()) {
            return forbidden_response();
        }

        $teilnehmer = self::participantsForCourse($kurs->id);
        if (count($teilnehmer) === 0) {
            $_SESSION['meldung'] = 'Es sind keine Teilnehmer hinterlegt. Es gibt nichts zu synchronisieren.';

            return [303, ['Location' => url_for('kurse/' . $kurs->id . '/teilnehmer/moodle')], ''];
        }

        $courseShortname = trim((string) ($kurs->moodle_course_shortname ?? ''));
        $courseRole = trim((string) ($kurs->moodle_course_role ?? 'student'));
        if ($courseRole === '') {
            $courseRole = 'student';
        }

        $importService = new MoodleImportService();
        if (!$importService->canImport()) {
            $_SESSION['fehlermeldung'] = 'Der Moodle-Import ist nicht korrekt konfiguriert. Bitte überprüfe den Pfad zum Upload-Skript in den Einstellungen.';

            return [303, ['Location' => url_for('kurse/' . $kurs->id . '/teilnehmer/moodle')], ''];
        }

        $courseService = new MoodleCourseService();
        if (!$courseService->isWebserviceConfigured()) {
            $_SESSION['fehlermeldung'] = 'Der Moodle-Webservice ist nicht konfiguriert. Bitte hinterlege eine Basis-URL und ein Token in den Einstellungen.';

            return [303, ['Location' => url_for('kurse/' . $kurs->id . '/teilnehmer/moodle')], ''];
        }

        try {
            $result = $importService->importParticipants(
                $teilnehmer,
                $courseShortname !== '' ? $courseShortname : null,
                $courseShortname !== '' ? $courseRole : null
            );
        } catch (\Throwable $exception) {
            $_SESSION['fehlermeldung'] = 'Moodle-Synchronisation fehlgeschlagen: ' . $exception->getMessage();

            audit_log('moodle_teilnehmer_sync_fehlgeschlagen', [
                'kurs_id' => (int) $kurs->id,
                'kurs_name' => (string) $kurs->name,
                'fehlermeldung' => $exception->getMessage(),
            ]);

            return [303, ['Location' => url_for('kurse/' . $kurs->id . '/teilnehmer/moodle')], ''];
        }

        if ((int) ($result['exit_code'] ?? 1) !== 0) {
            $message = 'Moodle-Synchronisation fehlgeschlagen. Rückgabecode: ' . $result['exit_code'] . '.';
            if (!empty($result['output'])) {
                $message .= ' Ausgabe: ' . implode(' ', array_map('trim', $result['output']));
            }
            $_SESSION['fehlermeldung'] = $message;

            audit_log('moodle_teilnehmer_sync_fehlgeschlagen', [
                'kurs_id' => (int) $kurs->id,
                'kurs_name' => (string) $kurs->name,
                'exit_code' => $result['exit_code'],
                'ausgabe' => array_map('trim', $result['output'] ?? []),
            ]);

            return [303, ['Location' => url_for('kurse/' . $kurs->id . '/teilnehmer/moodle')], ''];
        }

        $count = count($teilnehmer);
        $baseMessage = $count === 1
            ? '1 Teilnehmer wurde an Moodle übergeben.'
            : sprintf('%d Teilnehmer wurden an Moodle übergeben.', $count);
        if ($courseShortname !== '') {
            $baseMessage .= ' Zielkurs: ' . $courseShortname . '.';
        }
        if (!empty($result['output'])) {
            $baseMessage .= ' ' . implode(' ', array_map('trim', $result['output']));
        }

        $courseId = null;
        $syncStats = null;
        try {
            $courseId = self::resolveMoodleCourseId($kurs, $courseService);
            if ($courseId !== null) {
                $syncStats = self::synchronizeParticipantsFromMoodle($kurs, $courseService, $courseId, true);
                $baseMessage .= ' Synchronisierung: ' . self::buildMoodleSyncSummary($syncStats);
            }
        } catch (\Throwable $exception) {
            $_SESSION['fehlermeldung'] = 'Teilnehmer wurden nach Moodle übertragen, aber der Abgleich der Rückmeldungen ist fehlgeschlagen: ' . $exception->getMessage();

            audit_log('moodle_teilnehmer_sync_warnung', [
                'kurs_id' => (int) $kurs->id,
                'kurs_name' => (string) $kurs->name,
                'moodle_course_id' => $courseId,
                'warnung' => $exception->getMessage(),
            ]);

            $_SESSION['meldung'] = $baseMessage;

            return [303, ['Location' => url_for('kurse/' . $kurs->id . '/teilnehmer/moodle')], ''];
        }

        $_SESSION['meldung'] = $baseMessage;
        unset($_SESSION['fehlermeldung']);

        audit_log('moodle_teilnehmer_sync_erfolgreich', [
            'kurs_id' => (int) $kurs->id,
            'kurs_name' => (string) $kurs->name,
            'moodle_course_id' => $courseId,
            'moodle_course_shortname' => $kurs->moodle_course_shortname ?? '',
            'statistik' => $syncStats,
            'anzahl_teilnehmer' => $count,
        ]);

        return [303, ['Location' => url_for('kurse/' . $kurs->id . '/teilnehmer/moodle')], ''];
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

        if (!current_user_can_manage_participants()) {
            return forbidden_response();
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

        if (!current_user_can_manage_participants()) {
            return self::jsonResponse(403, ['error' => 'Aktion nicht erlaubt']);
        }

        if ($method === 'POST' && isset($_GET['delete'])) {
            $id = (int) $_GET['delete'];
            if ($id > 0) {
                $teilnehmer = R::load('teilnehmer', $id);
                if ($teilnehmer->id && (int) $teilnehmer->kurs_id === (int) $kurs->id) {
                    $participantData = self::participantToArray($teilnehmer);
                    R::trash($teilnehmer);
                    audit_log('teilnehmer_geloescht', [
                        'kurs_id' => (int) $kurs->id,
                        'kurs_name' => (string) $kurs->name,
                        'teilnehmer' => $participantData,
                    ]);
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

            $beforeState = $isNew ? [] : self::participantToArray($teilnehmer);

            $teilnehmer->vorname = trim((string) ($data['vorname'] ?? ''));
            $teilnehmer->nachname = trim((string) ($data['nachname'] ?? ''));
            $teilnehmer->geburtsdatum = normalize_birthdate((string) ($data['geburtsdatum'] ?? ''));
            $teilnehmer->geburtsort = trim((string) ($data['geburtsort'] ?? ''));

            if (array_key_exists('firma', $data)) {
                $teilnehmer->firma = trim((string) $data['firma']);
            } elseif (!isset($teilnehmer->firma)) {
                $teilnehmer->firma = '';
            }

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

            $afterState = self::participantToArray($teilnehmer);

            if ($isNew) {
                audit_log('teilnehmer_angelegt', [
                    'kurs_id' => (int) $kurs->id,
                    'kurs_name' => (string) $kurs->name,
                    'teilnehmer' => $afterState,
                ]);
            } else {
                $changes = [];
                foreach ($afterState as $field => $value) {
                    if ($field === 'id') {
                        continue;
                    }

                    $beforeValue = $beforeState[$field] ?? null;
                    if ($beforeValue !== $value) {
                        $changes[$field] = [
                            'alt' => $beforeValue,
                            'neu' => $value,
                        ];
                    }
                }

                if ($changes !== []) {
                    audit_log('teilnehmer_aktualisiert', [
                        'kurs_id' => (int) $kurs->id,
                        'kurs_name' => (string) $kurs->name,
                        'teilnehmer_id' => $afterState['id'],
                        'aenderungen' => $changes,
                    ]);
                }
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

    private static function findParticipantInCourse(\RedBeanPHP\OODBBean $kurs, array $params): ?\RedBeanPHP\OODBBean
    {
        $participantId = isset($params['participantId']) ? (int) $params['participantId'] : 0;
        if ($participantId <= 0) {
            return null;
        }

        $teilnehmer = R::load('teilnehmer', $participantId);
        if (!$teilnehmer->id || (int) $teilnehmer->kurs_id !== (int) $kurs->id) {
            return null;
        }

        return $teilnehmer;
    }

    private static function participantFormDefaults(?\RedBeanPHP\OODBBean $teilnehmer = null): array
    {
        if ($teilnehmer === null) {
            return [
                'vorname' => '',
                'nachname' => '',
                'firma' => '',
                'geburtsdatum' => '',
                'geburtsort' => '',
                'email' => '',
            ];
        }

        return [
            'vorname' => (string) ($teilnehmer->vorname ?? ''),
            'nachname' => (string) ($teilnehmer->nachname ?? ''),
            'firma' => (string) ($teilnehmer->firma ?? ''),
            'geburtsdatum' => (string) ($teilnehmer->geburtsdatum ?? ''),
            'geburtsort' => (string) ($teilnehmer->geburtsort ?? ''),
            'email' => (string) ($teilnehmer->email ?? ''),
        ];
    }

    private static function processParticipantForm(\RedBeanPHP\OODBBean $kurs, array $source): array
    {
        $values = [
            'vorname' => trim((string) ($source['vorname'] ?? '')),
            'nachname' => trim((string) ($source['nachname'] ?? '')),
            'firma' => trim((string) ($source['firma'] ?? '')),
            'geburtsdatum' => trim((string) ($source['geburtsdatum'] ?? '')),
            'geburtsort' => trim((string) ($source['geburtsort'] ?? '')),
            'email' => trim((string) ($source['email'] ?? '')),
        ];

        $normalized = [
            'vorname' => $values['vorname'],
            'nachname' => $values['nachname'],
            'firma' => $values['firma'],
            'geburtsdatum' => normalize_birthdate($values['geburtsdatum']),
            'geburtsort' => $values['geburtsort'],
            'email' => normalize_email_address($values['email']),
        ];

        $errors = [];

        if ($values['vorname'] === '') {
            $errors['vorname'] = 'Bitte gib einen Vornamen an.';
        }

        if ($values['nachname'] === '') {
            $errors['nachname'] = 'Bitte gib einen Nachnamen an.';
        }

        if ($normalized['geburtsdatum'] === '') {
            $errors['geburtsdatum'] = 'Bitte gib ein Geburtsdatum an.';
        } elseif (create_strict_date('Y-m-d', $normalized['geburtsdatum']) === null) {
            $errors['geburtsdatum'] = 'Bitte gib ein gültiges Geburtsdatum an.';
        }

        $requiresBirthplace = ((int) ($kurs->feld_geburtsort_aktiv ?? 0)) === 1;
        if ($requiresBirthplace && $values['geburtsort'] === '') {
            $errors['geburtsort'] = 'Bitte gib einen Geburtsort an.';
        }

        if ($values['email'] !== '' && $normalized['email'] === '') {
            $errors['email'] = 'Bitte gib eine gültige E-Mail-Adresse an.';
        }

        if (!isset($errors['geburtsdatum'])) {
            $values['geburtsdatum'] = $normalized['geburtsdatum'];
        }

        if (!isset($errors['email'])) {
            $values['email'] = $normalized['email'];
        }

        return [
            'values' => $values,
            'normalized' => $normalized,
            'errors' => $errors,
        ];
    }

    private static function applyParticipantForm(\RedBeanPHP\OODBBean $teilnehmer, array $normalized): void
    {
        $teilnehmer->vorname = $normalized['vorname'] ?? '';
        $teilnehmer->nachname = $normalized['nachname'] ?? '';
        $teilnehmer->firma = $normalized['firma'] ?? '';
        $teilnehmer->geburtsdatum = $normalized['geburtsdatum'] ?? '';
        $teilnehmer->geburtsort = $normalized['geburtsort'] ?? '';
        $teilnehmer->email = $normalized['email'] ?? '';
    }

    private static function renderParticipantRow(\RedBeanPHP\OODBBean $kurs, \RedBeanPHP\OODBBean $teilnehmer, bool $canManage): string
    {
        return render_template('teilnehmer_table_row.php', [
            'kurs' => $kurs,
            'teilnehmer' => $teilnehmer,
            'canManageParticipants' => $canManage,
        ]);
    }

    private static function renderParticipantEditRow(
        \RedBeanPHP\OODBBean $kurs,
        array $values,
        bool $canManage,
        array $errors,
        ?\RedBeanPHP\OODBBean $teilnehmer,
        bool $isNew
    ): string
    {
        return render_template('teilnehmer_table_edit_row.php', [
            'kurs' => $kurs,
            'values' => $values,
            'errors' => $errors,
            'teilnehmer' => $teilnehmer,
            'isNew' => $isNew,
            'canManageParticipants' => $canManage,
        ]);
    }

    private static function parseUploadedCsv(?array $file, string $encoding): ?array
    {
        if ($file === null || !is_array($file)) {
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

        $delimiter = self::detectCsvDelimiter($handle);
        $rawHeader = fgetcsv($handle, 0, $delimiter);
        if ($rawHeader === false) {
            fclose($handle);
            return null;
        }

        $convertedHeader = self::convertRowEncoding($rawHeader, $encoding);
        $header = self::normalizeHeader($convertedHeader);
        $rows = [];

        while (($data = fgetcsv($handle, 0, $delimiter)) !== false) {
            if ($data === [null] || $data === false) {
                continue;
            }

            $convertedRow = self::convertRowEncoding($data, $encoding);
            $assoc = self::combineRow($header, $convertedRow);
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

    private static function resolveMoodleCourseId(\RedBeanPHP\OODBBean $kurs, MoodleCourseService $service, bool $persist = true): ?int
    {
        $existingId = (int) ($kurs->moodle_course_id ?? 0);
        if ($existingId > 0) {
            return $existingId;
        }

        $shortname = trim((string) ($kurs->moodle_course_shortname ?? ''));
        if ($shortname === '') {
            return null;
        }

        $course = $service->findCourseByShortname($shortname);
        if ($course === null || empty($course['id'])) {
            return null;
        }

        $resolvedId = (int) $course['id'];

        if ($persist && $resolvedId > 0) {
            $previousId = (int) ($kurs->moodle_course_id ?? 0);
            if ($previousId !== $resolvedId) {
                $kurs->moodle_course_id = $resolvedId;
                R::store($kurs);

                audit_log('kurs_moodle_id_automatisch_gesetzt', [
                    'kurs_id' => (int) $kurs->id,
                    'kurs_name' => (string) $kurs->name,
                    'shortname' => $shortname,
                    'kurs_id_alt' => $previousId > 0 ? $previousId : null,
                    'kurs_id_neu' => $resolvedId,
                ]);
            }
        }

        return $resolvedId > 0 ? $resolvedId : null;
    }

    private static function synchronizeParticipantsFromMoodle(
        \RedBeanPHP\OODBBean $kurs,
        MoodleCourseService $service,
        int $courseId,
        bool $createMissing
    ): array {
        if ($courseId <= 0) {
            return [
                'created' => 0,
                'updated' => 0,
                'unchanged' => 0,
                'skipped' => 0,
                'matched' => ['id' => 0, 'username' => 0, 'email' => 0],
            ];
        }

        $remoteUsers = $service->fetchEnrolments($courseId);
        $stats = [
            'created' => 0,
            'updated' => 0,
            'unchanged' => 0,
            'skipped' => 0,
            'matched' => ['id' => 0, 'username' => 0, 'email' => 0],
        ];

        foreach ($remoteUsers as $user) {
            $moodleId = isset($user['id']) ? (int) $user['id'] : 0;
            if ($moodleId <= 0) {
                $stats['skipped']++;
                continue;
            }

            $participant = self::findParticipantByMoodleId((int) $kurs->id, $moodleId);
            $matchedBy = 'id';

            if ($participant === null) {
                $username = sanitize_username((string) ($user['username'] ?? ''));
                if ($username !== '') {
                    $participant = self::findParticipantByUsername((int) $kurs->id, $username);
                    if ($participant !== null) {
                        $matchedBy = 'username';
                        $stats['matched']['username']++;
                    }
                }
            } else {
                $stats['matched']['id']++;
            }

            if ($participant === null) {
                $email = normalize_email_address((string) ($user['email'] ?? ''));
                if ($email !== '') {
                    $participant = self::findParticipantByEmail((int) $kurs->id, $email);
                    if ($participant !== null) {
                        $matchedBy = 'email';
                        $stats['matched']['email']++;
                    }
                }
            }

            $isNew = false;
            if ($participant === null) {
                if (!$createMissing) {
                    $stats['skipped']++;
                    continue;
                }

                $participant = R::dispense('teilnehmer');
                $participant->kurs = $kurs;
                $participant->passwort = generate_password();
                $participant->benutzername = '';
                $participant->firma = '';
                $isNew = true;
            }

            $before = self::participantStateForSync($participant);

            self::applyMoodleUserData($participant, $user, $matchedBy);

            R::store($participant);

            $after = self::participantStateForSync($participant);
            $changes = self::detectParticipantChanges($before, $after);

            if ($isNew) {
                $stats['created']++;
                audit_log('teilnehmer_von_moodle_importiert', [
                    'kurs_id' => (int) $kurs->id,
                    'kurs_name' => (string) $kurs->name,
                    'teilnehmer' => $after,
                    'moodle_user' => self::sanitizeMoodleUserForLog($user),
                ]);
            } elseif ($changes !== []) {
                $stats['updated']++;
                audit_log('teilnehmer_von_moodle_aktualisiert', [
                    'kurs_id' => (int) $kurs->id,
                    'kurs_name' => (string) $kurs->name,
                    'teilnehmer_id' => (int) $participant->id,
                    'aenderungen' => $changes,
                ]);
            } else {
                $stats['unchanged']++;
            }
        }

        return $stats;
    }

    private static function findParticipantByMoodleId(int $kursId, int $moodleId): ?\RedBeanPHP\OODBBean
    {
        if ($kursId <= 0 || $moodleId <= 0) {
            return null;
        }

        $teilnehmer = R::findOne('teilnehmer', ' kurs_id = ? AND moodle_user_id = ? ', [$kursId, $moodleId]);

        return $teilnehmer instanceof \RedBeanPHP\OODBBean ? $teilnehmer : null;
    }

    private static function findParticipantByUsername(int $kursId, string $username): ?\RedBeanPHP\OODBBean
    {
        $username = sanitize_username($username);
        if ($kursId <= 0 || $username === '') {
            return null;
        }

        $teilnehmer = R::findOne('teilnehmer', ' kurs_id = ? AND benutzername = ? ', [$kursId, $username]);

        return $teilnehmer instanceof \RedBeanPHP\OODBBean ? $teilnehmer : null;
    }

    private static function findParticipantByEmail(int $kursId, string $email): ?\RedBeanPHP\OODBBean
    {
        $email = normalize_email_address($email);
        if ($kursId <= 0 || $email === '') {
            return null;
        }

        $teilnehmer = R::findOne('teilnehmer', ' kurs_id = ? AND email = ? ', [$kursId, $email]);

        return $teilnehmer instanceof \RedBeanPHP\OODBBean ? $teilnehmer : null;
    }

    private static function applyMoodleUserData(\RedBeanPHP\OODBBean $teilnehmer, array $user, string $matchedBy): void
    {
        $firstname = trim((string) ($user['firstname'] ?? ''));
        $lastname = trim((string) ($user['lastname'] ?? ''));
        $email = normalize_email_address((string) ($user['email'] ?? ''));
        $username = sanitize_username((string) ($user['username'] ?? ''));
        $idnumber = trim((string) ($user['idnumber'] ?? ''));
        $customFields = isset($user['customfields']) && is_array($user['customfields']) ? $user['customfields'] : [];
        $birthdate = isset($customFields['birthdate']) ? normalize_birthdate((string) $customFields['birthdate']) : '';
        $birthplace = isset($customFields['birthplace']) ? trim((string) $customFields['birthplace']) : '';
        $moodleId = isset($user['id']) ? (int) $user['id'] : null;

        if ($firstname !== '') {
            $teilnehmer->vorname = $firstname;
        }

        if ($lastname !== '') {
            $teilnehmer->nachname = $lastname;
        }

        if ($birthdate !== '') {
            $teilnehmer->geburtsdatum = $birthdate;
        }

        if ($birthplace !== '') {
            $teilnehmer->geburtsort = $birthplace;
        }

        if ($email !== '') {
            $teilnehmer->email = $email;
        }

        if ($username !== '') {
            $teilnehmer->moodle_username = $username;
            $shouldUpdateUsername = trim((string) ($teilnehmer->benutzername ?? '')) === '' || $matchedBy === 'username';
            if ($shouldUpdateUsername) {
                $teilnehmer->benutzername = $username;
            }
        }

        if ($idnumber !== '') {
            $teilnehmer->moodle_idnumber = $idnumber;
        }

        if ($moodleId !== null && $moodleId > 0) {
            $teilnehmer->moodle_user_id = $moodleId;
        }

        $teilnehmer->moodle_last_sync_at = date(DATE_ATOM);
    }

    private static function participantStateForSync(\RedBeanPHP\OODBBean $teilnehmer): array
    {
        return [
            'id' => (int) $teilnehmer->id,
            'vorname' => (string) ($teilnehmer->vorname ?? ''),
            'nachname' => (string) ($teilnehmer->nachname ?? ''),
            'firma' => (string) ($teilnehmer->firma ?? ''),
            'geburtsdatum' => (string) ($teilnehmer->geburtsdatum ?? ''),
            'geburtsort' => (string) ($teilnehmer->geburtsort ?? ''),
            'benutzername' => (string) ($teilnehmer->benutzername ?? ''),
            'email' => (string) ($teilnehmer->email ?? ''),
            'moodle_user_id' => isset($teilnehmer->moodle_user_id) ? (int) $teilnehmer->moodle_user_id : null,
            'moodle_username' => (string) ($teilnehmer->moodle_username ?? ''),
            'moodle_idnumber' => (string) ($teilnehmer->moodle_idnumber ?? ''),
            'moodle_last_sync_at' => (string) ($teilnehmer->moodle_last_sync_at ?? ''),
        ];
    }

    private static function detectParticipantChanges(array $before, array $after): array
    {
        $changes = [];
        foreach ($after as $field => $value) {
            if ($field === 'id') {
                continue;
            }

            if ($field === 'moodle_last_sync_at') {
                continue;
            }

            $previous = $before[$field] ?? null;
            if ($previous !== $value) {
                $changes[$field] = [
                    'alt' => $previous,
                    'neu' => $value,
                ];
            }
        }

        return $changes;
    }

    private static function sanitizeMoodleUserForLog(array $user): array
    {
        $customFields = [];
        if (isset($user['customfields']) && is_array($user['customfields'])) {
            foreach ($user['customfields'] as $key => $value) {
                $customFields[(string) $key] = (string) $value;
            }
        }

        return [
            'id' => isset($user['id']) ? (int) $user['id'] : null,
            'username' => (string) ($user['username'] ?? ''),
            'email' => (string) ($user['email'] ?? ''),
            'idnumber' => (string) ($user['idnumber'] ?? ''),
            'customfields' => array_intersect_key($customFields, ['birthdate' => true, 'birthplace' => true]),
        ];
    }

    private static function buildMoodleSyncSummary(array $stats): string
    {
        $parts = [];
        $labels = [
            'created' => 'neu',
            'updated' => 'aktualisiert',
            'unchanged' => 'unverändert',
            'skipped' => 'übersprungen',
        ];

        foreach ($labels as $key => $label) {
            $value = (int) ($stats[$key] ?? 0);
            if ($value > 0) {
                $parts[] = sprintf('%d %s', $value, $label);
            }
        }

        if (!empty($stats['matched']) && is_array($stats['matched'])) {
            $matchParts = [];
            $matchLabels = ['id' => 'ID', 'username' => 'Benutzername', 'email' => 'E-Mail'];
            foreach ($matchLabels as $field => $label) {
                $count = (int) ($stats['matched'][$field] ?? 0);
                if ($count > 0) {
                    $matchParts[] = sprintf('%s: %d', $label, $count);
                }
            }
            if ($matchParts !== []) {
                $parts[] = 'Abgleich (' . implode(', ', $matchParts) . ')';
            }
        }

        return $parts === [] ? 'Keine Änderungen' : implode(', ', $parts);
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

    private static function detectCsvDelimiter($handle): string
    {
        if (!is_resource($handle)) {
            return ',';
        }

        $delimiters = [',', ';', "\t"];
        $counts = array_fill_keys($delimiters, 0);

        $initialPosition = ftell($handle);
        if ($initialPosition === false) {
            $initialPosition = 0;
        }

        for ($i = 0; $i < 5 && !feof($handle); $i++) {
            $line = fgets($handle);
            if ($line === false) {
                break;
            }

            foreach ($delimiters as $delimiter) {
                $counts[$delimiter] += substr_count($line, $delimiter);
            }
        }

        if ($initialPosition >= 0) {
            fseek($handle, $initialPosition);
        }

        $bestDelimiter = ',';
        $maxCount = $counts[$bestDelimiter] ?? 0;
        foreach ($counts as $delimiter => $count) {
            if ($count > $maxCount) {
                $maxCount = $count;
                $bestDelimiter = $delimiter;
            }
        }

        if ($maxCount === 0) {
            return ',';
        }

        return $bestDelimiter;
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

    private static function convertRowEncoding(array $values, string $encoding): array
    {
        $converted = [];
        foreach ($values as $value) {
            if (!is_string($value)) {
                $converted[] = $value;
                continue;
            }

            $convertedValue = mb_convert_encoding($value, 'UTF-8', $encoding);
            if ($convertedValue === false) {
                $convertedValue = $value;
            }

            if (strpos($convertedValue, "\xEF\xBB\xBF") === 0) {
                $convertedValue = substr($convertedValue, 3);
            }

            $converted[] = $convertedValue;
        }

        return $converted;
    }

    private static function availableEncodings(): array
    {
        return [
            'utf-8' => 'UTF-8 (Standard)',
            'iso-8859-1' => 'ISO-8859-1 (Westeuropa)',
            'windows-1252' => 'Windows-1252 (Westeuropa)',
        ];
    }

    private static function sanitizeEncoding(string $encoding): string
    {
        $encoding = strtolower(trim($encoding));
        if ($encoding === '') {
            return 'utf-8';
        }

        if (!array_key_exists($encoding, self::availableEncodings())) {
            return 'utf-8';
        }

        return $encoding;
    }

    private static function encodeRowsPayload(array $rows): string
    {
        return self::encodePayload($rows);
    }

    private static function encodeHeaderPayload(array $header): string
    {
        return self::encodePayload($header);
    }

    private static function encodePayload($data): string
    {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);

        if (!is_string($json)) {
            return '';
        }

        return base64_encode($json);
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
            'firma' => 'Firma',
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
            'firma' => ['firma', 'unternehmen', 'company', 'organisation', 'organization', 'betrieb', 'employer'],
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

            if (isset($values['firma'])) {
                $teilnehmer->firma = $values['firma'];
            }

            $teilnehmer->vorname = $values['vorname'] ?? '';
            $teilnehmer->nachname = $values['nachname'] ?? '';
            $teilnehmer->geburtsdatum = normalize_birthdate((string) ($values['geburtsdatum'] ?? ''));
            $teilnehmer->geburtsort = $values['geburtsort'] ?? '';
            $teilnehmer->firma = $values['firma'] ?? '';

            $username = sanitize_username($values['benutzername'] ?? '');
            if ($username === '' && $teilnehmer->vorname !== '' && $teilnehmer->nachname !== '') {
                $username = generate_username($teilnehmer->vorname, $teilnehmer->nachname);
            } elseif ($username !== '') {
                $username = ensure_unique_username($username);
            }
            $teilnehmer->benutzername = $username;

            $password = $values['passwort'] ?? '';
            if ($password === '') {
                $password = generate_password();
            }
            $teilnehmer->passwort = $password;

            $email = normalize_email_address($values['email'] ?? '');
            if ($email === '' && $teilnehmer->benutzername !== '') {
                $email = generate_email($teilnehmer->benutzername);
            }
            $teilnehmer->email = $email;

            try {
                R::store($teilnehmer);
                $result['imported']++;
            } catch (\Throwable $exception) {
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
            'firma' => (string) ($teilnehmer->firma ?? ''),
            'geburtsdatum' => format_birthdate_for_display((string) $teilnehmer->geburtsdatum),
            'geburtsort' => (string) $teilnehmer->geburtsort,
            'benutzername' => (string) $teilnehmer->benutzername,
            'email' => (string) ($teilnehmer->email ?? ''),
            'moodle_user_id' => isset($teilnehmer->moodle_user_id) ? (int) $teilnehmer->moodle_user_id : null,
            'moodle_username' => (string) ($teilnehmer->moodle_username ?? ''),
            'moodle_last_sync_at' => format_datetime_for_display($teilnehmer->moodle_last_sync_at ?? ''),
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
