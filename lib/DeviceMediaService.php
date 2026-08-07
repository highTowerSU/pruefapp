<?php

declare(strict_types=1);

use Ceneos\PhpBase\Integration\OpenAiCompatibleClient;
use RedBeanPHP\R;

/** Secure, reusable photo storage and optional visual type-plate analysis. */
final class DeviceMediaService
{
    /** @return list<array<string,mixed>> */
    public static function forDevice(int $deviceId): array
    {
        return R::getAll('SELECT m.*, a.status AS analysis_status, a.proposal_json, a.error_message, a.provider_model FROM device_media m LEFT JOIN device_media_analysis a ON a.media_id = m.id WHERE m.device_id = ? ORDER BY m.created_at DESC, m.id DESC', [$deviceId]);
    }

    /** @return list<array<string,mixed>> */
    public static function forInspection(int $inspectionId): array
    {
        return R::getAll('SELECT m.*, a.status AS analysis_status, a.proposal_json, a.error_message, a.provider_model FROM device_media m LEFT JOIN device_media_analysis a ON a.media_id = m.id WHERE m.inspection_id = ? ORDER BY m.created_at DESC, m.id DESC', [$inspectionId]);
    }

    public static function storeUpload(int $deviceId, ?int $inspectionId, array $upload, string $type, string $caption, int $userId): int
    {
        $type = in_array($type, ['type_plate', 'condition', 'defect', 'disposal', 'other'], true) ? $type : 'condition';
        if ((int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException('Bitte wähle ein Foto aus.');
        }
        $tmp = (string) ($upload['tmp_name'] ?? '');
        $size = (int) ($upload['size'] ?? 0);
        $mime = $tmp !== '' && is_file($tmp) ? (new finfo(FILEINFO_MIME_TYPE))->file($tmp) : '';
        $dimensions = $tmp !== '' && is_file($tmp) ? @getimagesize($tmp) : false;
        $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        if (!isset($extensions[$mime]) || $dimensions === false || $size < 1 || $size > 12 * 1024 * 1024) {
            throw new InvalidArgumentException('Erlaubt sind JPEG, PNG oder WebP bis 12 MB.');
        }
        if (($dimensions[0] ?? 0) > 8000 || ($dimensions[1] ?? 0) > 8000) {
            throw new InvalidArgumentException('Das Foto ist zu groß. Maximal 8000 × 8000 Pixel sind erlaubt.');
        }
        $directory = app_data_root() . '/device-media/' . $deviceId;
        if (!is_dir($directory) && !mkdir($directory, 0770, true) && !is_dir($directory)) {
            throw new RuntimeException('Das Fotoverzeichnis konnte nicht angelegt werden.');
        }
        $name = bin2hex(random_bytes(12)) . '.' . $extensions[$mime];
        $path = $directory . '/' . $name;
        if (!move_uploaded_file($tmp, $path)) throw new RuntimeException('Das Foto konnte nicht gespeichert werden.');
        @chmod($path, 0660);
        R::exec('INSERT INTO device_media (device_id, inspection_id, media_type, caption, path, original_name, mime, bytes, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [
            $deviceId,
            $inspectionId,
            $type,
            mb_substr(trim($caption), 0, 1000),
            $path,
            mb_substr((string) ($upload['name'] ?? 'foto.' . $extensions[$mime]), 0, 240),
            $mime,
            $size,
            $userId,
            date(DATE_ATOM),
        ]);
        return (int) R::getInsertID();
    }

    /** @return array<string,mixed> */
    public static function analyseTypePlate(int $mediaId): array
    {
        $media = R::getRow('SELECT * FROM device_media WHERE id = ?', [$mediaId]);
        if ($media === [] || !is_file((string) $media['path'])) throw new RuntimeException('Das Typenschildfoto wurde nicht gefunden.');
        if ((string) $media['media_type'] !== 'type_plate') throw new InvalidArgumentException('Nur Typenschildfotos können ausgewertet werden.');
        $provider = AiProviderService::selectedVocabularyProvider();
        if ($provider === null || !DeviceVocabularyService::canSuggest()) {
            throw new RuntimeException('Bitte aktiviere zuerst einen KI-Anbieter und ein Modell für die Stammdatenprüfung.');
        }
        $body = file_get_contents((string) $media['path']);
        if (!is_string($body) || $body === '') throw new RuntimeException('Das Typenschildfoto konnte nicht gelesen werden.');
        $model = trim((string) get_app_config('vocabulary_ai_model', ''));
        $header = (string) $provider->auth_mode === 'oauth' ? 'Authorization' : (trim((string) $provider->header_name) ?: 'Authorization');
        $instruction = 'Lies ein Foto eines technischen Typenschilds. Antworte ausschließlich als JSON mit manufacturer, device_model, name, serial_number, inventory_number, inspection_type_codes (Array mit electrical und/oder ladder), cable_length_m, warming_device sowie confidence. Unleserliche Werte müssen leer bleiben. Erfinde nichts. name ist eine kurze deutsche Gerätebezeichnung. Bei inspection_type_codes nur klar erkennbare Prüfarten nennen.';
        $response = (new OpenAiCompatibleClient((string) $provider->base_url, AiProviderService::accessToken($provider), $header, 45))->chatCompletions([
            'model' => $model,
            'temperature' => 0,
            'messages' => [
                ['role' => 'system', 'content' => $instruction],
                ['role' => 'user', 'content' => [
                    ['type' => 'text', 'text' => 'Bitte dieses Typenschild auswerten.'],
                    ['type' => 'image_url', 'image_url' => ['url' => 'data:' . (string) $media['mime'] . ';base64,' . base64_encode($body)]],
                ]],
            ],
        ]);
        $responseContent = $response['choices'][0]['message']['content'] ?? '';
        if (is_array($responseContent)) {
            $responseContent = implode('', array_map(static function ($part): string {
                return is_array($part) ? (string) ($part['text'] ?? '') : '';
            }, $responseContent));
        }
        $content = trim((string) $responseContent);
        $content = preg_replace('/^```(?:json)?\s*|\s*```$/', '', $content) ?? '';
        $proposal = json_decode($content, true);
        if (!is_array($proposal)) throw new RuntimeException('Die KI-Antwort enthält keinen verwertbaren Typenschildvorschlag.');
        $proposal = [
            'manufacturer' => DeviceVocabularyService::canonicalize('manufacturer', (string) ($proposal['manufacturer'] ?? '')),
            'device_model' => DeviceVocabularyService::canonicalize('device_model', (string) ($proposal['device_model'] ?? '')),
            'name' => DeviceVocabularyService::canonicalize('name', (string) ($proposal['name'] ?? '')),
            'serial_number' => trim((string) ($proposal['serial_number'] ?? '')),
            'inventory_number' => trim((string) ($proposal['inventory_number'] ?? '')),
            'inspection_type_codes' => array_values(array_intersect((array) ($proposal['inspection_type_codes'] ?? []), ['electrical', 'ladder'])),
            'cable_length_m' => is_numeric($proposal['cable_length_m'] ?? null) ? (float) $proposal['cable_length_m'] : null,
            'warming_device' => filter_var($proposal['warming_device'] ?? null, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE),
            'confidence' => max(0, min(1, (float) ($proposal['confidence'] ?? 0))),
        ];
        $actionableValues = array_filter([
            $proposal['manufacturer'],
            $proposal['device_model'],
            $proposal['name'],
            $proposal['serial_number'],
            $proposal['inventory_number'],
        ], static fn($value): bool => trim((string) $value) !== '');
        if ($actionableValues === []) {
            throw new RuntimeException('Auf dem Typenschild wurden keine eindeutigen Stammdaten erkannt.');
        }
        $now = date(DATE_ATOM);
        $existing = R::getRow('SELECT id FROM device_media_analysis WHERE media_id = ?', [$mediaId]);
        if ($existing === []) {
            R::exec('INSERT INTO device_media_analysis (media_id, status, provider_model, proposal_json, error_message, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)', [$mediaId, 'done', (string) ($response['model'] ?? $model), json_encode($proposal, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), '', $now, $now]);
        } else {
            R::exec('UPDATE device_media_analysis SET status = ?, provider_model = ?, proposal_json = ?, error_message = ?, updated_at = ? WHERE id = ?', ['done', (string) ($response['model'] ?? $model), json_encode($proposal, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), '', $now, (int) $existing['id']]);
        }
        return $proposal;
    }

    public static function recordAnalysisError(int $mediaId, string $error): void
    {
        $now = date(DATE_ATOM);
        $existing = R::getRow('SELECT id FROM device_media_analysis WHERE media_id = ?', [$mediaId]);
        if ($existing === []) {
            R::exec('INSERT INTO device_media_analysis (media_id, status, error_message, created_at, updated_at) VALUES (?, ?, ?, ?, ?)', [$mediaId, 'failed', mb_substr($error, 0, 1000), $now, $now]);
        } else {
            R::exec('UPDATE device_media_analysis SET status = ?, error_message = ?, updated_at = ? WHERE id = ?', ['failed', mb_substr($error, 0, 1000), $now, (int) $existing['id']]);
        }
    }
}
