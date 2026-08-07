<?php

declare(strict_types=1);

use RedBeanPHP\R;
use Ceneos\PhpBase\Integration\OpenAiCompatibleClient;

/** Central, server-side canonicalisation for device master data. */
final class DeviceVocabularyService
{
    public const FIELDS = ['manufacturer', 'device_model', 'name'];
    public const NOT_RECOGNIZABLE = 'Nicht erkennbar';

    public static function normalizeKey(string $value): string
    {
        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? '');
        $value = str_replace(['–', '—', '‐'], '-', $value);
        return mb_strtolower($value, 'UTF-8');
    }

    public static function canonicalize(string $field, string $value): string
    {
        self::assertField($field);
        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? '');
        if ($value === '') return '';
        if (self::isNotRecognizable($value)) return self::NOT_RECOGNIZABLE;

        $alias = R::findOne('device_vocabulary_alias', ' field_name = ? AND source_key = ? AND active = 1 ', [$field, self::normalizeKey($value)]);
        return $alias ? trim((string) $alias->canonical_value) : $value;
    }

    public static function isNotRecognizable(string $value): bool
    {
        $key = preg_replace('/[^a-z0-9]+/iu', '', mb_strtolower(trim($value), 'UTF-8')) ?? '';
        return in_array($key, ['ne', 'nicht_erkennbar', 'nichtersichtlich', 'unknown', 'unbekannt'], true);
    }

    /** @return array{manufacturer:string,device_model:string,name:string} */
    public static function canonicalizeDeviceValues(array $values): array
    {
        return [
            'manufacturer' => self::canonicalize('manufacturer', (string) ($values['manufacturer'] ?? '')),
            'device_model' => self::canonicalize('device_model', (string) ($values['device_model'] ?? '')),
            'name' => self::canonicalize('name', (string) ($values['name'] ?? '')),
        ];
    }

    /** @return array<string,list<string>> */
    public static function options(): array
    {
        $out = array_fill_keys(self::FIELDS, []);
        foreach (R::getAll("SELECT manufacturer, device_model, name FROM device") as $row) {
            foreach (self::FIELDS as $field) {
                $value = self::canonicalize($field, (string) ($row[$field] ?? ''));
                if ($value !== '') $out[$field][$value] = $value;
            }
        }
        foreach (self::FIELDS as $field) {
            $values = array_values($out[$field]);
            usort($values, static function (string $left, string $right): int {
                if ($left === self::NOT_RECOGNIZABLE) return -1;
                if ($right === self::NOT_RECOGNIZABLE) return 1;
                return strnatcasecmp($left, $right);
            });
            $out[$field] = $values;
        }
        return $out;
    }

    /** @return list<string> */
    public static function contextOptions(string $field, string $manufacturer = '', string $model = ''): array
    {
        self::assertField($field);
        if ($field === 'manufacturer') return self::options()['manufacturer'];
        $manufacturer = self::canonicalize('manufacturer', $manufacturer);
        if ($manufacturer === '') return [];
        $column = $field === 'device_model' ? 'device_model' : 'name';
        $sql = 'SELECT DISTINCT ' . $column . ' AS value FROM device WHERE LOWER(TRIM(COALESCE(manufacturer, \'\'))) = ? AND TRIM(COALESCE(' . $column . ', \'\')) <> \'\'';
        $params = [self::normalizeKey($manufacturer)];
        if ($field === 'name') {
            $model = self::canonicalize('device_model', $model);
            if ($model === '') return [];
            $sql .= ' AND LOWER(TRIM(COALESCE(device_model, \'\'))) = ?';
            $params[] = self::normalizeKey($model);
        }
        $sql .= ' ORDER BY value';
        $values = [];
        foreach (R::getAll($sql, $params) as $row) {
            $value = self::canonicalize($field, (string) ($row['value'] ?? ''));
            if ($value !== '') $values[$value] = $value;
        }
        return array_values($values);
    }

    public static function enqueueReview(array $values, int $ownerUserId = 0): void
    {
        if (get_app_config('vocabulary_ai_enabled', '0') !== '1' || AiProviderService::selectedVocabularyProvider() === null) return;
        foreach (self::FIELDS as $field) {
            $value = trim((string) ($values[$field] ?? ''));
            if ($value === '' || self::isNotRecognizable($value)) continue;
            $key = self::normalizeKey($value);
            if (R::findOne('device_vocabulary_alias', ' field_name = ? AND source_key = ? AND active = 1 ', [$field, $key])) continue;
            if (R::findOne('device_vocabulary_review', ' field_name = ? AND LOWER(TRIM(source_value)) = ? ', [$field, $key])) continue;
            $dedupe = 'vocabulary:' . $field . ':' . hash('sha256', $key);
            BackgroundJobService::enqueue('vocabulary_suggestion', ['type' => 'vocabulary_suggestion', 'field' => $field, 'value' => $value], [
                'owner_user_id' => $ownerUserId,
                'dedupe_key' => $dedupe,
                'cancellable' => true,
            ]);
        }
    }

    public static function canSuggest(): bool
    {
        $provider = AiProviderService::selectedVocabularyProvider();
        if (get_app_config('vocabulary_ai_enabled', '0') !== '1' || $provider === null) return false;
        if (trim((string) $provider->base_url) === '' || trim((string) get_app_config('vocabulary_ai_model', '')) === '') return false;
        return (string) $provider->auth_mode === 'oauth' || trim((string) $provider->api_token) !== '';
    }

    /** Starts one resumable scan without creating a job per historic device. */
    public static function enqueueHistoricalReview(int $ownerUserId): array
    {
        if (!self::canSuggest()) throw new RuntimeException('Bitte zuerst Provider, Modell und Aktivierung der Stammdatenprüfung speichern.');
        return BackgroundJobService::enqueue('vocabulary_review_scan', ['type' => 'vocabulary_review_scan'], [
            'owner_user_id' => $ownerUserId,
            'dedupe_key' => 'vocabulary:historical-review',
            'cancellable' => true,
            'message' => 'Die vorhandenen Hersteller, Modelle und Gerätebezeichnungen werden geprüft.',
        ]);
    }

    public static function storeSuggestion(string $field, string $value, array $proposal): int
    {
        self::assertField($field);
        $review = R::findOne('device_vocabulary_review', ' field_name = ? AND source_value = ? AND status = ? ', [$field, $value, 'pending']) ?: R::dispense('device_vocabulary_review');
        $review->field_name = $field;
        $review->source_value = $value;
        $review->suggested_value = (string) $proposal['canonical_value'];
        $review->confidence = (float) $proposal['confidence'];
        $review->reason = (string) $proposal['reason'];
        $review->provider_model = (string) $proposal['provider_model'];
        $review->status = 'pending';
        $review->updated_at = date(DATE_ATOM);
        if (!$review->created_at) $review->created_at = $review->updated_at;
        return (int) R::store($review);
    }

    /** @return array{updated_devices:int,updated_inspections:int} */
    public static function applyAlias(string $field, string $source, string $canonical, int $userId = 0): array
    {
        self::assertField($field);
        $sourceKey = self::normalizeKey($source);
        $canonical = self::canonicalize($field, $canonical);
        if ($sourceKey === '' || $canonical === '') throw new InvalidArgumentException('Quell- und Zielwert sind erforderlich.');
        R::begin();
        try {
            $alias = R::findOne('device_vocabulary_alias', ' field_name = ? AND source_key = ? ', [$field, $sourceKey]) ?: R::dispense('device_vocabulary_alias');
            $alias->field_name = $field;
            $alias->source_key = $sourceKey;
            $alias->canonical_value = $canonical;
            $alias->active = 1;
            $alias->approved_by = $userId;
            $alias->updated_at = date(DATE_ATOM);
            if (!$alias->created_at) $alias->created_at = $alias->updated_at;
            R::store($alias);
            $deviceCount = R::exec('UPDATE device SET ' . $field . ' = ?, updated_at = ? WHERE LOWER(TRIM(COALESCE(' . $field . ", ''))) = ?", [$canonical, date(DATE_ATOM), $sourceKey]);
            $inspectionColumn = $field === 'name' ? 'device_type' : $field;
            $inspectionCount = R::exec('UPDATE inspection SET ' . $inspectionColumn . ' = ?, updated_at = ? WHERE LOWER(TRIM(COALESCE(' . $inspectionColumn . ", ''))) = ?", [$canonical, date(DATE_ATOM), $sourceKey]);
            R::commit();
        } catch (Throwable $exception) {
            R::rollback();
            throw $exception;
        }
        audit_log('stammdaten_zusammengefuehrt', ['field' => $field, 'source' => $source, 'canonical' => $canonical, 'devices' => $deviceCount, 'inspections' => $inspectionCount]);
        return ['updated_devices' => (int) $deviceCount, 'updated_inspections' => (int) $inspectionCount];
    }

    /** @return array<string,mixed> */
    public static function suggest(string $field, string $value): array
    {
        self::assertField($field);
        $provider = AiProviderService::selectedVocabularyProvider();
        $baseUrl = rtrim((string) ($provider->base_url ?? ''), '/');
        $token = $provider ? AiProviderService::accessToken($provider) : '';
        $model = trim((string) get_app_config('vocabulary_ai_model', ''));
        if ($baseUrl === '' || $token === '' || $model === '') throw new RuntimeException('Die KI-Stammdatenprüfung ist noch nicht vollständig konfiguriert.');
        $headerName = (string) ($provider->auth_mode ?? '') === 'oauth' ? 'Authorization' : (trim((string) ($provider->header_name ?? 'Authorization')) ?: 'Authorization');
        $payload = ['model' => $model, 'temperature' => 0, 'messages' => [
            ['role' => 'system', 'content' => 'Du prüfst ausschließlich deutsche technische Stammdaten. Antworte nur als JSON mit canonical_value, confidence (0 bis 1) und reason. Schlage nur eine bekannte, eindeutig bessere Schreibweise vor; bei Unsicherheit canonical_value leer lassen.'],
            ['role' => 'user', 'content' => json_encode(['field' => $field, 'value' => $value, 'known_values' => array_slice(self::options()[$field], 0, 300)], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)],
        ]];
        $decoded = (new OpenAiCompatibleClient($baseUrl, $token, $headerName))->chatCompletions($payload);
        $content = (string) ($decoded['choices'][0]['message']['content'] ?? '');
        $content = preg_replace('/^```(?:json)?\s*|\s*```$/', '', trim($content)) ?? '';
        $proposal = json_decode($content, true);
        if (!is_array($proposal)) throw new RuntimeException('KI-Antwort enthält keinen verwertbaren Vorschlag.');
        return ['canonical_value' => trim((string) ($proposal['canonical_value'] ?? '')), 'confidence' => max(0, min(1, (float) ($proposal['confidence'] ?? 0))), 'reason' => trim((string) ($proposal['reason'] ?? '')), 'provider_model' => $model];
    }

    /** @return list<string> */
    public static function availableModels(string $baseUrl, string $token, string $headerName = 'Authorization'): array
    {
        $baseUrl = rtrim(trim($baseUrl), '/');
        if ($baseUrl === '' || trim($token) === '') throw new InvalidArgumentException('Basis-URL und Token sind erforderlich.');
        $headerName = trim($headerName) ?: 'Authorization';
        return (new OpenAiCompatibleClient($baseUrl, $token, $headerName, 15))->models();
    }

    private static function assertField(string $field): void
    {
        if (!in_array($field, self::FIELDS, true)) throw new InvalidArgumentException('Unbekanntes Stammdatenfeld.');
    }
}
