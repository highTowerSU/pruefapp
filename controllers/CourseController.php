<?php

require_once __DIR__ . '/../lib/MoodleCourseService.php';

use \RedBeanPHP\R as R;

class CourseController
{
    public static function showSettings(array $params, bool $isHx): array
    {
        if (!current_user_can_manage_courses()) {
            return forbidden_response();
        }

        $kurs = self::findCourse((int)($params['id'] ?? 0));
        if ($kurs === null) {
            return [404, [], '<h1>404 – Kurs nicht gefunden</h1>'];
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $previousEmail = (bool) ($kurs->feld_email_aktiv ?? false);
            $previousGeburtsort = (bool) ($kurs->feld_geburtsort_aktiv ?? false);
            $previousFirma = (bool) ($kurs->feld_firma_aktiv ?? false);
            $previousShortname = trim((string) ($kurs->moodle_course_shortname ?? ''));
            $previousFullname = trim((string) ($kurs->moodle_course_fullname ?? ''));

            $kurs->feld_email_aktiv = isset($_POST['feld_email_aktiv']) ? 1 : 0;
            $kurs->feld_geburtsort_aktiv = isset($_POST['feld_geburtsort_aktiv']) ? 1 : 0;
            $kurs->feld_firma_aktiv = isset($_POST['feld_firma_aktiv']) ? 1 : 0;

            $newShortname = trim((string) ($_POST['moodle_course_shortname'] ?? ''));
            $newFullname = trim((string) ($_POST['moodle_course_fullname'] ?? ''));

            $kurs->moodle_course_shortname = $newShortname !== '' ? $newShortname : null;
            $kurs->moodle_course_fullname = $newFullname !== '' ? $newFullname : null;

            R::store($kurs);

            $changes = [];
            if ($previousEmail !== (bool) $kurs->feld_email_aktiv) {
                $changes['feld_email_aktiv_alt'] = $previousEmail;
                $changes['feld_email_aktiv_neu'] = (bool) $kurs->feld_email_aktiv;
            }

            if ($previousGeburtsort !== (bool) $kurs->feld_geburtsort_aktiv) {
                $changes['feld_geburtsort_aktiv_alt'] = $previousGeburtsort;
                $changes['feld_geburtsort_aktiv_neu'] = (bool) $kurs->feld_geburtsort_aktiv;
            }

            if ($previousFirma !== (bool) $kurs->feld_firma_aktiv) {
                $changes['feld_firma_aktiv_alt'] = $previousFirma;
                $changes['feld_firma_aktiv_neu'] = (bool) $kurs->feld_firma_aktiv;
            }

            if ($previousShortname !== $newShortname) {
                $changes['moodle_course_shortname_alt'] = $previousShortname;
                $changes['moodle_course_shortname_neu'] = $newShortname;
            }

            if ($previousFullname !== $newFullname) {
                $changes['moodle_course_fullname_alt'] = $previousFullname;
                $changes['moodle_course_fullname_neu'] = $newFullname;
            }

            if ($changes !== []) {
                audit_log('kurseinstellungen_aktualisiert', array_merge([
                    'kurs_id' => (int) $kurs->id,
                    'kurs_name' => (string) $kurs->name,
                ], $changes));
            }

            $_SESSION['meldung'] = 'Einstellungen gespeichert.';

            return [303, ['Location' => url_for('kurse')], ''];
        }

        $content = render_template('kurseinstellungen_form.php', ['kurs' => $kurs]);
        $body = render_template('layout.php', [
            'title' => 'Kurseinstellungen – ' . $kurs->name,
            'content' => $content,
        ]);

        return [200, [], $body];
    }

    public static function linkSettings(array $params, bool $isHx): array
    {
        if (!current_user_can_manage_courses()) {
            return forbidden_response();
        }

        $kurs = self::findCourse((int)($params['id'] ?? 0));
        if ($kurs === null) {
            return [404, [], '<h1>404 – Kurs nicht gefunden</h1>'];
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $action = $_POST['action'] ?? '';

            if ($action === 'create') {
                $bezeichnung = trim($_POST['name'] ?? '');

                $link = R::dispense('uebermittlungslink');
                $link->token = bin2hex(random_bytes(8));
                $link->bezeichnung = $bezeichnung;
                $link->aktiv = 1;
                $link->kurs = $kurs;
                R::store($link);

                audit_log('uebermittlungslink_erstellt', [
                    'kurs_id' => (int) $kurs->id,
                    'kurs_name' => (string) $kurs->name,
                    'link_id' => (int) $link->id,
                    'bezeichnung' => $bezeichnung,
                    'token_vorschau' => audit_log_mask_token((string) $link->token),
                ]);

                $_SESSION['meldung'] = 'Neuer Übermittlungslink wurde erstellt.';
            } else {
                $linkId = isset($_POST['link_id']) ? (int) $_POST['link_id'] : 0;
                $link = $linkId > 0 ? R::load('uebermittlungslink', $linkId) : null;

                if (!$link || (int) $link->kurs_id !== (int) $kurs->id) {
                    $_SESSION['fehlermeldung'] = 'Der ausgewählte Übermittlungslink wurde nicht gefunden.';
                    return [303, ['Location' => url_for('kurse/' . $kurs->id . '/link')], ''];
                }

                if ($action === 'toggle') {
                    $previousState = (bool) $link->aktiv;
                    $link->aktiv = $link->aktiv ? 0 : 1;
                    $_SESSION['meldung'] = $link->aktiv
                        ? 'Übermittlungslink wurde aktiviert.'
                        : 'Übermittlungslink wurde deaktiviert.';
                    R::store($link);

                    audit_log($link->aktiv ? 'uebermittlungslink_aktiviert' : 'uebermittlungslink_deaktiviert', [
                        'kurs_id' => (int) $kurs->id,
                        'kurs_name' => (string) $kurs->name,
                        'link_id' => (int) $link->id,
                        'bezeichnung' => (string) $link->bezeichnung,
                        'vorher_aktiv' => $previousState,
                        'aktiv' => (bool) $link->aktiv,
                    ]);
                } elseif ($action === 'regenerate') {
                    $previousToken = (string) $link->token;
                    $link->token = bin2hex(random_bytes(8));
                    $link->aktiv = 1;
                    R::store($link);
                    $_SESSION['meldung'] = 'Der Link wurde neu erzeugt und aktiviert.';

                    audit_log('uebermittlungslink_regeneriert', [
                        'kurs_id' => (int) $kurs->id,
                        'kurs_name' => (string) $kurs->name,
                        'link_id' => (int) $link->id,
                        'bezeichnung' => (string) $link->bezeichnung,
                        'token_alt' => audit_log_mask_token($previousToken),
                        'token_neu' => audit_log_mask_token((string) $link->token),
                    ]);
                } elseif ($action === 'delete') {
                    $previousToken = (string) $link->token;
                    $previousName = (string) $link->bezeichnung;

                    R::trash($link);
                    $_SESSION['meldung'] = 'Übermittlungslink wurde gelöscht.';

                    audit_log('uebermittlungslink_geloescht', [
                        'kurs_id' => (int) $kurs->id,
                        'kurs_name' => (string) $kurs->name,
                        'link_id' => (int) $linkId,
                        'bezeichnung' => $previousName,
                        'token_vorschau' => audit_log_mask_token($previousToken),
                    ]);
                } elseif ($action === 'rename') {
                    $bezeichnung = trim($_POST['name'] ?? '');
                    $previousName = (string) $link->bezeichnung;
                    $link->bezeichnung = $bezeichnung;
                    R::store($link);
                    $_SESSION['meldung'] = 'Bezeichnung gespeichert.';

                    if ($previousName !== $bezeichnung) {
                        audit_log('uebermittlungslink_umbenannt', [
                            'kurs_id' => (int) $kurs->id,
                            'kurs_name' => (string) $kurs->name,
                            'link_id' => (int) $link->id,
                            'bezeichnung_alt' => $previousName,
                            'bezeichnung_neu' => $bezeichnung,
                        ]);
                    }
                }
            }

            return [303, ['Location' => url_for('kurse/' . $kurs->id . '/link')], ''];
        }

        $links = R::findAll('uebermittlungslink', ' kurs_id = ? ORDER BY id ', [$kurs->id]);

        if (count($links) === 0 && $kurs->token) {
            $link = R::dispense('uebermittlungslink');
            $link->token = $kurs->token;
            $link->bezeichnung = '';
            $link->aktiv = $kurs->uebermittlung_aktiv ? 1 : 0;
            $link->kurs = $kurs;
            R::store($link);

            $kurs->token = null;
            $kurs->uebermittlung_aktiv = 0;
            R::store($kurs);

            $links = R::findAll('uebermittlungslink', ' kurs_id = ? ORDER BY id ', [$kurs->id]);
        }

        $content = render_template('link_erzeugen.php', [
            'kurs' => $kurs,
            'links' => array_values($links),
        ]);

        $body = render_template('layout.php', [
            'title' => 'Link zur Teilnehmerdateneingabe – ' . $kurs->name,
            'content' => $content,
        ]);

        return [200, [], $body];
    }

    public static function index(array $params, bool $isHx): array
    {
        if ($isHx) {
            return self::tableResponse();
        }

        $kurse = self::allCourses();
        $moodleOptions = self::moodleCourseOptions();
        $content = render_template('kurs_liste.php', [
            'kurse' => $kurse,
            'message' => null,
            'error' => null,
            'moodleCourseOptions' => $moodleOptions['options'],
            'moodleCourseError' => $moodleOptions['error'],
        ]);

        $body = render_template('layout.php', [
            'title' => 'Prüfauftragsverwaltung',
            'content' => $content,
        ]);

        return [200, [], $body];
    }

    public static function table(array $params, bool $isHx): array
    {
        return self::tableResponse();
    }

    public static function create(array $params, bool $isHx): array
    {
        if (!current_user_can_manage_courses()) {
            return forbidden_response();
        }

        $kursname = isset($_POST['kursname']) ? trim($_POST['kursname']) : '';

        if ($kursname === '') {
            $error = 'Bitte gib einen Kursnamen an.';
            if ($isHx) {
                return self::tableResponse(null, $error, 422);
            }

            $_SESSION['fehlermeldung'] = $error;
            return [303, ['Location' => url_for('kurse')], ''];
        }

        $moodleCopyRequested = isset($_POST['moodle_copy']) && $_POST['moodle_copy'] !== '';
        $moodleTemplateShortname = trim((string) ($_POST['moodle_template_shortname'] ?? ''));
        $moodleTargetShortname = trim((string) ($_POST['moodle_new_shortname'] ?? ''));
        $moodleTargetFullname = trim((string) ($_POST['moodle_new_fullname'] ?? ''));
        $moodleVisible = isset($_POST['moodle_visible']) ? 1 : 0;

        if ($moodleCopyRequested && $moodleTargetFullname === '') {
            $moodleTargetFullname = $kursname;
        }

        $moodleCopyMessage = '';
        $moodleCourseId = null;

        if ($moodleCopyRequested) {
            if ($moodleTemplateShortname === '' || $moodleTargetShortname === '') {
                $error = 'Bitte gib sowohl den Quellkurs als auch den neuen Moodle-Shortname an.';
                if ($isHx) {
                    return self::tableResponse(null, $error, 422);
                }

                $_SESSION['fehlermeldung'] = $error;
                return [303, ['Location' => url_for('kurse')], ''];
            }

            $moodleCourseService = new MoodleCourseService();

            if (!$moodleCourseService->canDuplicate()) {
                $error = 'Der Moodle-Kurskopie-Assistent ist nicht korrekt konfiguriert. Bitte prüfe den Moodle-Pfad (duplicate_course.php oder admin/cli/import.php) sowie ggf. die Webservice-Konfiguration.';
                if ($isHx) {
                    return self::tableResponse(null, $error, 422);
                }

                $_SESSION['fehlermeldung'] = $error;
                return [303, ['Location' => url_for('kurse')], ''];
            }

            try {
                $result = $moodleCourseService->duplicateCourse(
                    $moodleTemplateShortname,
                    $moodleTargetFullname,
                    $moodleTargetShortname,
                    ['visible' => $moodleVisible ? 1 : 0]
                );
            } catch (\Throwable $exception) {
                $error = 'Moodle-Kurs konnte nicht kopiert werden: ' . $exception->getMessage();
                if ($isHx) {
                    return self::tableResponse(null, $error, 422);
                }

                $_SESSION['fehlermeldung'] = $error;
                return [303, ['Location' => url_for('kurse')], ''];
            }

            if ((int) $result['exit_code'] !== 0) {
                $output = !empty($result['output']) ? ' Ausgabe: ' . implode(' ', array_map('trim', $result['output'])) : '';
                $error = 'Moodle-Kurskopie fehlgeschlagen. Rückgabecode: ' . $result['exit_code'] . '.' . $output;
                if ($isHx) {
                    return self::tableResponse(null, $error, 422);
                }

                $_SESSION['fehlermeldung'] = $error;
                return [303, ['Location' => url_for('kurse')], ''];
            }

            $moodleCourseId = $result['course_id'] ?? null;
            $moodleCopyMessage = sprintf(
                ' Moodle-Kurs "%s" wurde aus "%s" kopiert.',
                $moodleTargetShortname,
                $moodleTemplateShortname
            );

            if (!empty($result['output'])) {
                $trimmed = trim(implode(' ', array_map('trim', $result['output'])));
                if ($trimmed !== '') {
                    $moodleCopyMessage .= ' ' . $trimmed;
                }
            }
        }

        $kurs = R::dispense('kurs');
        $kurs->name = $kursname;
        if ($moodleTargetShortname !== '') {
            $kurs->moodle_course_shortname = $moodleTargetShortname;
        }
        if ($moodleCopyRequested && $moodleTemplateShortname !== '') {
            $kurs->moodle_template_shortname = $moodleTemplateShortname;
        }
        if ($moodleTargetFullname !== '') {
            $kurs->moodle_course_fullname = $moodleTargetFullname;
        }
        if ($moodleCourseId !== null) {
            $kurs->moodle_course_id = (int) $moodleCourseId;
        }
        R::store($kurs);

        audit_log('kurs_angelegt', [
            'kurs_id' => (int) $kurs->id,
            'kurs_name' => $kursname,
        ]);

        $successMessage = sprintf('Kurs "%s" wurde angelegt.', $kursname) . $moodleCopyMessage;
        if ($isHx) {
            return self::tableResponse($successMessage);
        }

        $_SESSION['meldung'] = $successMessage;
        return [303, ['Location' => url_for('kurse')], ''];
    }

    public static function delete(array $params, bool $isHx): array
    {
        if (!current_user_can_manage_courses()) {
            return forbidden_response();
        }

        $id = isset($params['id']) ? (int) $params['id'] : 0;
        $kurs = R::load('kurs', $id);

        if (!$kurs->id) {
            $error = 'Der ausgewählte Kurs wurde nicht gefunden.';
            if ($isHx) {
                return self::tableResponse(null, $error, 404);
            }

            $_SESSION['fehlermeldung'] = $error;
            return [303, ['Location' => url_for('kurse')], ''];
        }

        $teilnehmerAnzahl = R::count('teilnehmer', 'kurs_id = ?', [$kurs->id]);
        if ($teilnehmerAnzahl > 0) {
            $error = 'Kurs enthält noch Teilnehmer und kann nicht gelöscht werden.';
            if ($isHx) {
                return self::tableResponse(null, $error, 409);
            }

            $_SESSION['fehlermeldung'] = $error;
            return [303, ['Location' => url_for('kurse')], ''];

        }

        R::trash($kurs);
        audit_log('kurs_geloescht', [
            'kurs_id' => $id,
            'kurs_name' => (string) $kurs->name,
        ]);
        $message = sprintf('Kurs "%s" wurde gelöscht.', $kurs->name);

        if ($isHx) {
            return self::tableResponse($message);
        }

        $_SESSION['meldung'] = $message;
        return [303, ['Location' => url_for('kurse')], ''];
    }

    private static function allCourses(): array
    {
        $courses = R::find('kurs', ' ORDER BY name ');

        if ($courses === []) {
            return [];
        }

        $courseIds = array_map(
            static function (\RedBeanPHP\OODBBean $course): int {
                return (int) $course->id;
            },
            array_values($courses)
        );

        $companiesByCourse = self::companiesForCourses($courseIds);

        foreach ($courses as $course) {
            $courseId = (int) $course->id;
            $course->auftraggeber = $companiesByCourse[$courseId] ?? [];
        }

        return $courses;
    }

    /**
     * @param array<int> $courseIds
     * @return array<int, array<int, string>>
     */
    private static function companiesForCourses(array $courseIds): array
    {
        if ($courseIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($courseIds), '?'));
        $participantRows = R::getAll(
            'SELECT DISTINCT kurs_id, TRIM(firma) AS firma'
            . ' FROM teilnehmer'
            . ' WHERE kurs_id IN (' . $placeholders . ')'
            . '   AND TRIM(COALESCE(firma, "")) <> ""'
            . ' ORDER BY kurs_id, TRIM(firma)',
            $courseIds
        );

        $linkRows = R::getAll(
            'SELECT kurs_id, TRIM(bezeichnung) AS bezeichnung'
            . ' FROM uebermittlungslink'
            . ' WHERE kurs_id IN (' . $placeholders . ')'
            . '   AND TRIM(COALESCE(bezeichnung, "")) <> ""'
            . ' ORDER BY kurs_id, TRIM(bezeichnung)',
            $courseIds
        );

        $result = [];
        $addCompany = static function (int $courseId, string $company) use (&$result): void {
            if ($courseId === 0 || $company === '') {
                return;
            }

            if (!array_key_exists($courseId, $result)) {
                $result[$courseId] = [];
            }

            if (!in_array($company, $result[$courseId], true)) {
                $result[$courseId][] = $company;
            }
        };

        foreach ($participantRows as $row) {
            $courseId = (int) ($row['kurs_id'] ?? 0);
            $company = trim((string) ($row['firma'] ?? ''));
            $addCompany($courseId, $company);
        }

        foreach ($linkRows as $row) {
            $courseId = (int) ($row['kurs_id'] ?? 0);
            $company = trim((string) ($row['bezeichnung'] ?? ''));
            $addCompany($courseId, $company);
        }

        foreach ($result as &$companies) {
            $companies = array_values($companies);
        }
        unset($companies);

        return $result;
    }

    private static function moodleCourseOptions(): array
    {
        $options = [];
        $error = null;

        $appendOption = static function (array $candidate) use (&$options): void {
            $shortname = trim((string) ($candidate['shortname'] ?? ''));
            $fullname = trim((string) ($candidate['fullname'] ?? ''));
            $identifier = strtolower($shortname !== '' ? $shortname : $fullname);

            if ($identifier === '') {
                $identifier = isset($candidate['id']) ? 'id:' . (int) $candidate['id'] : spl_object_id((object) $candidate);
            }

            if (isset($options[$identifier])) {
                return;
            }

            $displayParts = array_filter([
                $shortname !== '' ? $shortname : null,
                $fullname !== '' ? $fullname : null,
            ]);

            if ($displayParts === []) {
                $displayParts[] = trim((string) ($candidate['name'] ?? ''));
            }

            $candidate['display'] = implode(' · ', array_filter($displayParts));
            if ($candidate['display'] === '') {
                $candidate['display'] = $shortname !== '' ? $shortname : ($fullname !== '' ? $fullname : 'Kurs');
            }

            $options[$identifier] = $candidate;
        };

        try {
            $service = new MoodleCourseService();
            if ($service->isWebserviceConfigured()) {
                foreach ($service->fetchCourses() as $course) {
                    $id = isset($course['id']) ? (int) $course['id'] : 0;
                    if ($id === 1) { // Moodle-Frontpage auslassen
                        continue;
                    }

                    $appendOption([
                        'id' => $id,
                        'name' => (string) ($course['fullname'] ?? ''),
                        'shortname' => (string) ($course['shortname'] ?? ''),
                        'fullname' => (string) ($course['fullname'] ?? ''),
                        'origin' => 'remote',
                    ]);
                }
            }
        } catch (\Throwable $exception) {
            $error = $exception->getMessage();
        }

        $rows = R::getAll(
            'SELECT id, name, moodle_course_shortname, moodle_course_fullname'
            . ' FROM kurs WHERE TRIM(COALESCE(moodle_course_shortname, "")) <> "" ORDER BY name'
        );

        foreach ($rows as $row) {
            $appendOption([
                'id' => (int) ($row['id'] ?? 0),
                'name' => (string) ($row['name'] ?? ''),
                'shortname' => (string) ($row['moodle_course_shortname'] ?? ''),
                'fullname' => (string) ($row['moodle_course_fullname'] ?? ''),
                'origin' => 'local',
            ]);
        }

        $list = array_values($options);

        usort($list, static function (array $left, array $right): int {
            return strcasecmp((string) ($left['display'] ?? ''), (string) ($right['display'] ?? ''));
        });

        return [
            'options' => $list,
            'error' => $error,
        ];
    }

    private static function tableResponse(?string $message = null, ?string $error = null, int $status = 200): array
    {
        $kurse = self::allCourses();

        return [
            $status,
            [],
            render_template('kurs_table.php', [
                'kurse' => $kurse,
                'message' => $message,
                'error' => $error,
            ]),
        ];
    }

    private static function findCourse(int $id): ?\RedBeanPHP\OODBBean
    {
        $kurs = R::load('kurs', $id);

        return $kurs->id ? $kurs : null;
    }
}
