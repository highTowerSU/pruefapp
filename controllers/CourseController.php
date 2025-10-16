<?php

use \RedBeanPHP\R as R;

class CourseController
{
    public static function showSettings(array $params, bool $isHx): array
    {
        $kurs = self::findCourse((int)($params['id'] ?? 0));
        if ($kurs === null) {
            return [404, [], '<h1>404 – Kurs nicht gefunden</h1>'];
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $kurs->feld_email_aktiv = isset($_POST['feld_email_aktiv']) ? 1 : 0;
            $kurs->feld_geburtsort_aktiv = isset($_POST['feld_geburtsort_aktiv']) ? 1 : 0;
            R::store($kurs);

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
        $kurs = self::findCourse((int)($params['id'] ?? 0));
        if ($kurs === null) {
            return [404, [], '<h1>404 – Kurs nicht gefunden</h1>'];
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $action = $_POST['action'] ?? '';

            if ($action === 'toggle') {
                $kurs->uebermittlung_aktiv = $kurs->uebermittlung_aktiv ? 0 : 1;
                $_SESSION['meldung'] = $kurs->uebermittlung_aktiv
                    ? 'Übermittlungslink aktiviert.'
                    : 'Übermittlungslink deaktiviert.';
            } elseif ($action === 'regenerate') {
                $kurs->token = bin2hex(random_bytes(8));
                $kurs->uebermittlung_aktiv = 1;
                $_SESSION['meldung'] = 'Neuer Link generiert.';
            }

            R::store($kurs);

            return [303, ['Location' => url_for('kurse/' . $kurs->id . '/link')], ''];
        }

        if (!$kurs->token) {
            $kurs->token = bin2hex(random_bytes(8));
            $kurs->uebermittlung_aktiv = 1;
            R::store($kurs);
        }

        $link = absolute_url_for('uebermitteln/' . $kurs->token);

        $content = render_template('link_erzeugen.php', [
            'kurs' => $kurs,
            'link' => $link,
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
        $content = render_template('kurs_liste.php', [
            'kurse' => $kurse,
            'message' => null,
            'error' => null,
        ]);

        $body = render_template('layout.php', [
            'title' => 'Kursverwaltung',
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
        $kursname = isset($_POST['kursname']) ? trim($_POST['kursname']) : '';

        if ($kursname === '') {
            $error = 'Bitte gib einen Kursnamen an.';
            if ($isHx) {
                return self::tableResponse(null, $error, 422);
            }

            $_SESSION['fehlermeldung'] = $error;
            return [303, ['Location' => url_for('kurse')], ''];
        }

        $kurs = R::dispense('kurs');
        $kurs->name = $kursname;
        R::store($kurs);

        $successMessage = sprintf('Kurs "%s" wurde angelegt.', $kursname);
        if ($isHx) {
            return self::tableResponse($successMessage);
        }

        $_SESSION['meldung'] = $successMessage;
        return [303, ['Location' => url_for('kurse')], ''];
    }

    public static function delete(array $params, bool $isHx): array
    {
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
        $message = sprintf('Kurs "%s" wurde gelöscht.', $kurs->name);

        if ($isHx) {
            return self::tableResponse($message);
        }

        $_SESSION['meldung'] = $message;
        return [303, ['Location' => url_for('kurse')], ''];
    }

    private static function allCourses(): array
    {
        return R::find('kurs', ' ORDER BY name ');
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
