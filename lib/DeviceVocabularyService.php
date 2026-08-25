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

        $alias = self::aliasFor($field, self::normalizeKey($value));
        return $alias !== null ? trim((string) $alias['canonical_value']) : $value;
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
            if (self::aliasFor($field, $key) !== null) continue;
            if (self::reviewFor($field, $key) !== null) continue;
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

    /** Stops only automatic one-value jobs; an explicitly started scan remains intact. */
    public static function cancelPendingSuggestions(): int
    {
        $cancelled = 0;
        foreach (BackgroundJobService::pending(1000) as $job) {
            if ((string) ($job['type'] ?? '') !== 'vocabulary_suggestion') continue;
            if (BackgroundJobService::requestCancellation((string) ($job['id'] ?? ''))) $cancelled++;
        }
        return $cancelled;
    }

    public static function storeSuggestion(string $field, string $value, array $proposal): int
    {
        self::assertField($field);
        $review = R::getRow("SELECT id FROM device_vocabulary_review WHERE field_name = ? AND source_value = ? AND status = 'pending' LIMIT 1", [$field, $value]);
        $now = date(DATE_ATOM);
        $suggested = trim((string) ($proposal['canonical_value'] ?? ''));
        $exact = $suggested !== '' && self::normalizeKey($suggested) === self::normalizeKey($value) && (float) ($proposal['confidence'] ?? 0) >= 0.99;
        $reason = trim((string) ($proposal['reason'] ?? ''));
        if ($exact) $reason = $reason !== '' ? $reason : 'Exakter Treffer; keine Änderung erforderlich.';
        $params = [$suggested, (float) ($proposal['confidence'] ?? 0), $reason, (string) ($proposal['provider_model'] ?? '')];
        if ($review !== []) {
            R::exec('UPDATE device_vocabulary_review SET suggested_value = ?, confidence = ?, reason = ?, provider_model = ?, status = ?, updated_at = ? WHERE id = ?', [...$params, $exact ? 'kept' : 'pending', $now, (int) $review['id']]);
            return (int) $review['id'];
        }
        R::exec("INSERT INTO device_vocabulary_review (field_name, source_value, suggested_value, confidence, reason, provider_model, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)", [$field, $value, ...$params, $exact ? 'kept' : 'pending', $now, $now]);
        return (int) R::getInsertID();
    }

    /** Removes historic no-op proposals from the review queue without changing device data. */
    public static function autoKeepExactSuggestions(): int
    {
        $rows = R::getAll("SELECT id, source_value, suggested_value FROM device_vocabulary_review WHERE status = 'pending' AND confidence >= 0.99");
        $ids = [];
        foreach ($rows as $row) {
            if (self::normalizeKey((string) $row['source_value']) === self::normalizeKey((string) $row['suggested_value'])) $ids[] = (int) $row['id'];
        }
        if ($ids === []) return 0;
        R::exec('UPDATE device_vocabulary_review SET status = ?, reason = CASE WHEN reason = ? THEN ? ELSE reason END, updated_at = ? WHERE id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')', array_merge(['kept', '', 'Exakter Treffer; keine Änderung erforderlich.', date(DATE_ATOM)], $ids));
        return count($ids);
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
            $now = date(DATE_ATOM);
            $alias = R::getRow('SELECT id FROM device_vocabulary_alias WHERE field_name = ? AND source_key = ? LIMIT 1', [$field, $sourceKey]);
            if ($alias !== []) {
                R::exec('UPDATE device_vocabulary_alias SET canonical_value = ?, active = 1, approved_by = ?, updated_at = ? WHERE id = ?', [$canonical, $userId, $now, (int) $alias['id']]);
            } else {
                R::exec('INSERT INTO device_vocabulary_alias (field_name, source_key, canonical_value, active, approved_by, created_at, updated_at) VALUES (?, ?, ?, 1, ?, ?, ?)', [$field, $sourceKey, $canonical, $userId, $now, $now]);
            }
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
            ['role' => 'system', 'content' => 'Du prüfst ausschließlich deutsche technische Stammdaten anhand deines eigenen Fachwissens. Antworte nur als JSON mit canonical_value, confidence (0 bis 1) und reason. Schlage nur eine eindeutig bessere Schreibweise vor; bei Unsicherheit canonical_value leer lassen. Wenn der Wert bereits korrekt ist, gib exakt denselben Wert mit confidence 1 zurück. Lokale Bestandsdaten sind keine Quelle und dürfen nicht als Grundlage verwendet werden.'],
            ['role' => 'user', 'content' => json_encode(['field' => $field, 'value' => $value], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)],
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

    /** @return array<string,mixed>|null */
    public static function aliasFor(string $field, string $sourceKey): ?array
    {
        $row = R::getRow('SELECT id, canonical_value FROM device_vocabulary_alias WHERE field_name = ? AND source_key = ? AND active = 1 LIMIT 1', [$field, $sourceKey]);
        return $row === [] ? null : $row;
    }

    /** @return array<string,mixed>|null */
    public static function reviewFor(string $field, string $sourceKey): ?array
    {
        $row = R::getRow('SELECT id FROM device_vocabulary_review WHERE field_name = ? AND LOWER(TRIM(source_value)) = ? LIMIT 1', [$field, $sourceKey]);
        return $row === [] ? null : $row;
    }
}
