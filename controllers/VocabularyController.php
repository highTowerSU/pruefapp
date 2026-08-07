<?php

declare(strict_types=1);

use RedBeanPHP\R;

final class VocabularyController
{
    public static function index(array $params, bool $isHx): array
    {
        if (!current_user_is_superadmin()) return forbidden_response();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $reviewId = (int) ($_POST['review_id'] ?? 0);
            $review = R::load('device_vocabulary_review', $reviewId);
            if (!(int) $review->id) {
                $_SESSION['fehlermeldung'] = 'Der Stammdatenvorschlag wurde nicht gefunden.';
            } elseif ((string) ($_POST['action'] ?? '') === 'keep') {
                $review->status = 'kept'; $review->decided_by = (int) current_user()->id; $review->updated_at = date(DATE_ATOM); R::store($review);
                audit_log('stammdaten_vorschlag_beibehalten', ['review_id' => $reviewId, 'field' => (string) $review->field_name, 'value' => (string) $review->source_value]);
                $_SESSION['meldung'] = 'Der Wert bleibt eigenständig.';
            } else {
                $target = trim((string) ($_POST['canonical_value'] ?? $review->suggested_value));
                try {
                    $result = DeviceVocabularyService::applyAlias((string) $review->field_name, (string) $review->source_value, $target, (int) current_user()->id);
                    $review->status = 'approved'; $review->suggested_value = $target; $review->decided_by = (int) current_user()->id; $review->updated_at = date(DATE_ATOM); R::store($review);
                    $_SESSION['meldung'] = 'Stammdaten zusammengeführt: ' . $result['updated_devices'] . ' Geräte und ' . $result['updated_inspections'] . ' Prüfungs-Snapshots aktualisiert.';
                } catch (Throwable $exception) {
                    $_SESSION['fehlermeldung'] = $exception->getMessage();
                }
            }
            return [303, ['Location' => url_for('admin/stammdaten')], ''];
        }
        $reviews = array_values(R::findAll('device_vocabulary_review', " status = 'pending' ORDER BY field_name, confidence DESC, id DESC LIMIT 250"));
        $content = render_template('vocabulary.php', ['reviews' => $reviews, 'options' => DeviceVocabularyService::options()]);
        if ($isHx) return [200, ['Content-Type' => 'text/html; charset=utf-8'], $content];
        return [200, [], render_template('layout.php', ['title' => 'Stammdaten bereinigen', 'content' => $content])];
    }
}
