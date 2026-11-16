<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/SignatureImageService.php';
if (SignatureImageService::dataUri('invalid') !== 'invalid') {
    throw new RuntimeException('Invalid data changed.');
}
if (!function_exists('imagecreatetruecolor')) {
    echo "PASS: Invalid-image fallback; pixel tests require GD on deployment host\n";
    exit;
}
$image = imagecreatetruecolor(6, 1);
imagealphablending($image, false);
imagesavealpha($image, true);
foreach ([[255,255,255,0],[240,240,240,0],[0,0,0,0],[20,40,160,0],[0,0,0,90],[200,200,200,0]] as $x => $rgba) {
    imagesetpixel($image, $x, 0, imagecolorallocatealpha($image, ...$rgba));
}
ob_start(); imagepng($image); $original = ob_get_clean(); imagedestroy($image);
$uri = 'data:image/png;base64,' . base64_encode($original);
$directory = sys_get_temp_dir() . '/signature-test-' . bin2hex(random_bytes(6));
try {
    $clean = SignatureImageService::dataUri($uri, $directory);
    if ($clean === $uri || SignatureImageService::dataUri($uri, $directory) !== $clean) {
        throw new RuntimeException('Transformation/cache failed.');
    }
    $decoded = imagecreatefromstring(base64_decode(substr($clean, strpos($clean, ',') + 1)));
    $alpha = static fn(int $x): int => (imagecolorat($decoded, $x, 0) >> 24) & 127;
    if ($alpha(0) !== 127 || $alpha(1) <= 0 || $alpha(5) <= 0) {
        throw new RuntimeException('Paper remains opaque.');
    }
    if (imagecolorat($decoded, 2, 0) !== 0 || imagecolorat($decoded, 3, 0) !== ((20 << 16) | (40 << 8) | 160) || $alpha(4) !== 90) {
        throw new RuntimeException('Ink or existing transparency changed.');
    }
    imagedestroy($decoded);
    foreach (glob($directory . '/*') as $path) unlink($path);
    rmdir($directory);
} finally {
    if (is_dir($directory)) {
        foreach (glob($directory . '/*') as $path) unlink($path);
        rmdir($directory);
    }
}
echo "PASS: Transparent paper, preserved black/blue ink and alpha, deterministic cache\n";
