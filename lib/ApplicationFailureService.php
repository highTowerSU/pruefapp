<?php

declare(strict_types=1);

use RedBeanPHP\R;

/** Stores safe server-side diagnostics for the short request ID shown to users. */
final class ApplicationFailureService
{
    public static function record(string $requestId, Throwable $error, bool $fatal = false): void
    {
        try {
            $failure = R::dispense('appfailure');
            $failure->request_id = $requestId;
            $failure->kind = $fatal ? 'fatal' : 'exception';
            $failure->exception_class = get_class($error);
            $failure->message = mb_substr($error->getMessage(), 0, 4000);
            $failure->source_file = mb_substr($error->getFile(), 0, 1000);
            $failure->source_line = $error->getLine();
            $failure->trace = mb_substr($error->getTraceAsString(), 0, 30000);
            $failure->request_method = mb_substr((string) ($_SERVER['REQUEST_METHOD'] ?? ''), 0, 16);
            $failure->request_path = mb_substr((string) (parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '/'), 0, 2000);
            $failure->query_keys_json = json_encode(array_values(array_map('strval', array_keys($_GET))), JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
            $failure->user_id = (int) ($_SESSION['user']['id'] ?? 0);
            $failure->created_at = date(DATE_ATOM);
            R::store($failure);
        } catch (Throwable $recordingError) {
            error_log('[pruefapp][' . $requestId . '] Fehlerdiagnose konnte nicht gespeichert werden: ' . $recordingError->getMessage());
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
