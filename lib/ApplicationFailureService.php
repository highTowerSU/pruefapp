<?php

declare(strict_types=1);

use RedBeanPHP\R;

/** Stores safe server-side diagnostics for the short request ID shown to users. */
final class ApplicationFailureService
{
    public static function record(string $requestId, Throwable $error, bool $fatal = false): void
    {
        $values = [
            $requestId,
            $fatal ? 'fatal' : 'exception',
            get_class($error),
            mb_substr($error->getMessage(), 0, 4000),
            mb_substr($error->getFile(), 0, 1000),
            $error->getLine(),
            mb_substr($error->getTraceAsString(), 0, 30000),
            mb_substr((string) ($_SERVER['REQUEST_METHOD'] ?? ''), 0, 16),
            mb_substr((string) (parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '/'), 0, 2000),
            json_encode(array_values(array_map('strval', array_keys($_GET))), JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
            (int) ($_SESSION['auth_user_id'] ?? 0),
            date(DATE_ATOM),
        ];
        try {
            // Do not use a bean here. This code runs while another bean or a
            // transaction may have failed; a parameterised insert has no
            // model hooks and is therefore the most reliable last-resort log.
            R::exec(
                'INSERT INTO appfailure (request_id, kind, exception_class, message, source_file, source_line, trace, request_method, request_path, query_keys_json, user_id, created_at) '
                . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                $values
            );
        } catch (Throwable $recordingError) {
            // The legacy bean path remains a compatibility fallback for
            // installations whose appfailure table is managed externally.
            try {
                $failure = R::dispense('appfailure');
                [$failure->request_id, $failure->kind, $failure->exception_class, $failure->message, $failure->source_file, $failure->source_line, $failure->trace, $failure->request_method, $failure->request_path, $failure->query_keys_json, $failure->user_id, $failure->created_at] = $values;
                R::store($failure);
            } catch (Throwable $fallbackError) {
                error_log('[pruefapp][' . $requestId . '] Fehlerdiagnose konnte nicht gespeichert werden: ' . $recordingError->getMessage() . ' / Fallback: ' . $fallbackError->getMessage());
            }
        }
    }

    /** @return array<string,mixed>|null */
    public static function find(string $requestId): ?array
    {
        $failure = R::findOne('appfailure', ' request_id = ? ', [$requestId]);
        if (!$failure) return null;
        return [
            'request_id' => (string) $failure->request_id,
            'kind' => (string) $failure->kind,
            'exception_class' => (string) $failure->exception_class,
            'message' => (string) $failure->message,
            'source_file' => (string) $failure->source_file,
            'source_line' => (int) $failure->source_line,
            'trace' => (string) $failure->trace,
            'request_method' => (string) $failure->request_method,
            'request_path' => (string) $failure->request_path,
            'query_keys' => json_decode((string) $failure->query_keys_json, true) ?: [],
            'user_id' => (int) $failure->user_id,
            'created_at' => (string) $failure->created_at,
        ];
    }
}
