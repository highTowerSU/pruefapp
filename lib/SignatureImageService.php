<?php

declare(strict_types=1);

/** Removes light neutral paper without redrawing or replacing signature strokes. */
final class SignatureImageService
{
    private const VERSION = 'paper-v1';

    public static function dataUri(string $uri, string $cacheDirectory = ''): string
    {
        if (!function_exists('imagecreatefromstring')
            || !preg_match('#^data:image/(?:png|jpeg);base64,(.+)$#s', $uri, $match)) {
            return $uri;
        }
        $bytes = base64_decode($match[1], true);
        if (!is_string($bytes) || strlen($bytes) > 8 * 1024 * 1024) {
            return $uri;
        }
        $path = $cacheDirectory !== ''
            ? $cacheDirectory . '/' . hash('sha256', self::VERSION . $bytes) . '.png'
            : '';
        if ($path !== '' && is_file($path)) {
            $cached = file_get_contents($path);
            if (is_string($cached) && $cached !== '') {
                return 'data:image/png;base64,' . base64_encode($cached);
            }
        }
        $size = @getimagesizefromstring($bytes);
        if ($size === false || $size[0] * $size[1] > 8000000) {
            return $uri;
        }
        $image = @imagecreatefromstring($bytes);
        if ($image === false) {
            return $uri;
        }
        imagepalettetotruecolor($image);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        for ($y = 0; $y < imagesy($image); $y++) {
            for ($x = 0; $x < imagesx($image); $x++) {
                $color = imagecolorat($image, $x, $y);
                $alpha = ($color >> 24) & 127;
                $red = ($color >> 16) & 255;
                $green = ($color >> 8) & 255;
                $blue = $color & 255;
                $min = min($red, $green, $blue);
                if ($alpha === 127 || $min < 180 || max($red, $green, $blue) - $min > 40) {
                    continue;
                }
                $opacity = max(0.0, min(1.0, (245 - $min) / 65));
                $newAlpha = 127 - (int) round((127 - $alpha) * $opacity);
                // Unmatte the paper color to avoid a white fringe around the ink.
                $rgb = static fn(int $channel): int => $opacity > 0
                    ? (int) max(0, min(255, round(255 - (255 - $channel) / $opacity)))
                    : 0;
                imagesetpixel($image, $x, $y, imagecolorallocatealpha($image, $rgb($red), $rgb($green), $rgb($blue), $newAlpha));
            }
        }
        ob_start();
        imagepng($image);
        $png = ob_get_clean();
        imagedestroy($image);
        if (!is_string($png) || $png === '') {
            return $uri;
        }
        if ($path !== '' && (is_dir($cacheDirectory) || @mkdir($cacheDirectory, 0770, true))) {
            // Atomic rename keeps concurrent report workers from reading partial images.
            $temporary = tempnam($cacheDirectory, '.signature-');
            if ($temporary !== false) {
                if (file_put_contents($temporary, $png, LOCK_EX) !== false) {
                    @chmod($temporary, 0660);
                    @rename($temporary, $path);
                }
                if (is_file($temporary)) {
                    @unlink($temporary);
                }
            }
        }
        return 'data:image/png;base64,' . base64_encode($png);
    }
}
