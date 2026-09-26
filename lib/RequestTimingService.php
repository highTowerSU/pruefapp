<?php

declare(strict_types=1);

/** Lightweight, request-local timings exposed through the standard Server-Timing header. */
final class RequestTimingService
{
    private static float $requestStartedAt = 0.0;
    /** @var array<string,float> */
    private static array $startedAt = [];
    /** @var array<string,float> */
    private static array $durations = [];

    public static function start(string $name): void
    {
        if (self::$requestStartedAt === 0.0) {
            self::$requestStartedAt = (float) ($_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true));
        }
        self::$startedAt[$name] = microtime(true);
    }

    public static function stop(string $name): void
    {
        if (!isset(self::$startedAt[$name])) {
            return;
        }
        self::$durations[$name] = (microtime(true) - self::$startedAt[$name]) * 1000;
        unset(self::$startedAt[$name]);
    }

    public static function header(): string
    {
        $now = microtime(true);
        $started = self::$requestStartedAt ?: (float) ($_SERVER['REQUEST_TIME_FLOAT'] ?? $now);
        $parts = ['total;dur=' . self::milliseconds(($now - $started) * 1000)];
        foreach (self::$durations as $name => $duration) {
            $parts[] = $name . ';dur=' . self::milliseconds($duration);
        }
        foreach (self::$startedAt as $name => $since) {
            $parts[] = $name . ';dur=' . self::milliseconds(($now - $since) * 1000);
        }
        return implode(', ', $parts);
    }

    public static function registerShutdownHeader(): void
    {
        register_shutdown_function(static function (): void {
            if (headers_sent()) {
                return;
            }
            foreach (headers_list() as $header) {
                if (str_starts_with(strtolower($header), 'server-timing:')) {
                    return;
                }
            }
            header('Server-Timing: ' . self::header());
        });
    }

    private static function milliseconds(float $value): string
    {
        return number_format(max(0, $value), 1, '.', '');
    }
}
