<?php

class Htmx extends \Ceneos\PhpBase\Compatibility\ArrayHtmx
{
    public static function handle(callable $next): callable
    {
        return static function () use ($next): never {
            $isHtmx = ($_SERVER['HTTP_HX_REQUEST'] ?? '') === 'true';
            [$status, $headers, $body] = $next($isHtmx);
            (new \Ceneos\PhpBase\Http\Response((int) $status, (array) $headers, (string) $body))->emit();
        };
    }
}
