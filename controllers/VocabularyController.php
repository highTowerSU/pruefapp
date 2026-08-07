<?php

declare(strict_types=1);

use RedBeanPHP\R;

final class VocabularyController
{
    public static function index(array $params, bool $isHx): array
    {
        if (!current_user_is_superadmin()) return forbidden_response();
        $message = '';
        $error = '';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $action = (string) ($_POST['action'] ?? '');
            if ($action === 'scan') {
                try {
                    $job = DeviceVocabularyService::enqueueHistoricalReview((int) current_user()->id);
                    audit_log('stammdaten_ki_pruefung_gestartet', ['job_id' => (string) ($job['id'] ?? '')]);
                    $message = 'Die Prüfung der vorhandenen Stammdaten wurde im Hintergrund gestartet.';
                } catch (Throwable $exception) {
                    $error = $exception->getMessage();
                }
            } else {
                $reviewId = (int) ($_POST['review_id'] ?? 0);
                $review = R::getRow('SELECT * FROM device_vocabulary_review WHERE id = ? LIMIT 1', [$reviewId]);
                if ($review === []) {
                    $error = 'Der Stammdatenvorschlag wurde nicht gefunden.';
                } elseif ($action === 'keep') {
                    R::exec("UPDATE device_vocabulary_review SET status = 'kept', decided_by = ?, updated_at = ? WHERE id = ?", [(int) current_user()->id, date(DATE_ATOM), $reviewId]);
                    audit_log('stammdaten_vorschlag_beibehalten', ['review_id' => $reviewId, 'field' => (string) $review['field_name'], 'value' => (string) $review['source_value']]);
                    $message = 'Der Wert bleibt eigenständig.';
                } else {
                    $target = trim((string) ($_POST['canonical_value'] ?? $review['suggested_value']));
                    try {
                        $result = DeviceVocabularyService::applyAlias((string) $review['field_name'], (string) $review['source_value'], $target, (int) current_user()->id);
                        R::exec("UPDATE device_vocabulary_review SET status = 'approved', suggested_value = ?, decided_by = ?, updated_at = ? WHERE id = ?", [$target, (int) current_user()->id, date(DATE_ATOM), $reviewId]);
                        $message = 'Stammdaten zusammengeführt: ' . $result['updated_devices'] . ' Geräte und ' . $result['updated_inspections'] . ' Prüfungs-Snapshots aktualisiert.';
                    } catch (Throwable $exception) {
                        $error = $exception->getMessage();
                    }
                }
            }
            if (!$isHx) {
                $_SESSION[$error !== '' ? 'fehlermeldung' : 'meldung'] = $error !== '' ? $error : $message;
                return [303, ['Location' => url_for('admin/stammdaten')], ''];
            }
        }
        $reviews = R::getAll("SELECT * FROM device_vocabulary_review WHERE status = 'pending' ORDER BY field_name, confidence DESC, id DESC LIMIT 250");
        $jobs = array_values(array_filter(BackgroundJobService::latest(20), static fn(array $job): bool => in_array((string) ($job['type'] ?? ''), ['vocabulary_review_scan', 'vocabulary_suggestion'], true)));
        $content = render_template('vocabulary.php', ['reviews' => $reviews, 'options' => DeviceVocabularyService::options(), 'jobs' => $jobs, 'canSuggest' => DeviceVocabularyService::canSuggest(), 'message' => $message, 'error' => $error]);
        if ($isHx) return [200, ['Content-Type' => 'text/html; charset=utf-8'], $content];
        return [200, [], render_template('layout.php', ['title' => 'Stammdaten bereinigen', 'content' => $content])];
    }
}
