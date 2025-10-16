<?php

use \RedBeanPHP\R as R;

class CourseController
{
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
            return [303, ['Location' => '/kurse'], ''];
        }

        $kurs = R::dispense('kurs');
        $kurs->name = $kursname;
        R::store($kurs);

        $successMessage = sprintf('Kurs "%s" wurde angelegt.', $kursname);
        if ($isHx) {
            return self::tableResponse($successMessage);
        }

        $_SESSION['meldung'] = $successMessage;
        return [303, ['Location' => '/kurse'], ''];
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
            return [303, ['Location' => '/kurse'], ''];
        }

        $teilnehmerAnzahl = R::count('teilnehmer', 'kurs_id = ?', [$kurs->id]);
        if ($teilnehmerAnzahl > 0) {
            $error = 'Kurs enthält noch Teilnehmer und kann nicht gelöscht werden.';
            if ($isHx) {
                return self::tableResponse(null, $error, 409);
            }

            $_SESSION['fehlermeldung'] = $error;
            return [303, ['Location' => '/kurse'], ''];
        }

        R::trash($kurs);
        $message = sprintf('Kurs "%s" wurde gelöscht.', $kurs->name);

        if ($isHx) {
            return self::tableResponse($message);
        }

        $_SESSION['meldung'] = $message;
        return [303, ['Location' => '/kurse'], ''];
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
}
