<?php

declare(strict_types=1);

/** Generates QR images on the application server, independent of browser JS. */
final class ServerQrCodeService
{
    public static function svg(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            throw new RuntimeException('Kein Inhalt für den QR-Code vorhanden.');
        }

        $script = dirname(__DIR__) . '/bin/render-qrcode-svg.js';
        if (!is_file($script)) {
            throw new RuntimeException('Der serverseitige QR-Code-Renderer ist nicht installiert.');
        }

        $pipes = [];
        $process = proc_open(
            ['/usr/bin/node', $script],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            dirname(__DIR__)
        );
        if (!is_resource($process)) {
            throw new RuntimeException('Der serverseitige QR-Code-Renderer konnte nicht gestartet werden.');
        }

        fwrite($pipes[0], $value);
        fclose($pipes[0]);
        $svg = stream_get_contents($pipes[1]);
        $error = trim((string) stream_get_contents($pipes[2]));
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        if ($exitCode !== 0 || !is_string($svg) || !str_contains($svg, '<svg')) {
            throw new RuntimeException($error !== '' ? 'QR-Code konnte nicht erzeugt werden: ' . $error : 'QR-Code konnte nicht erzeugt werden.');
        }

        return $svg;
    }
}
