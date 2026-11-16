<?php

declare(strict_types=1);

// No database: simulate rejected tokens and render the actual recovery template.
final class InspectionCompanionService
{
    public static function byToken(string $token): array
    {
        return [];
    }
}

function url_for(string $path = ''): string
{
    return '/' . $path;
}

function render_template(string $template, array $variables = []): string
{
    if ($template === 'layout.php') {
        return '<html><title>' . $variables['title'] . '</title>' . $variables['content'] . '</html>';
    }
    extract($variables);
    ob_start();
    require dirname(__DIR__) . '/templates/' . $template;
    return (string) ob_get_clean();
}

require dirname(__DIR__) . '/controllers/InspectionCompanionController.php';

$token = str_repeat('a', 48);
foreach (['open', 'barcode', 'barcodePhoto', 'photo'] as $method) {
    foreach ([false, true] as $hx) {
        [$status, $headers, $body] = InspectionCompanionController::$method(['token' => $token], $hx);
        if ($status !== 410 || !str_contains($headers['Cache-Control'] ?? '', 'no-store')) {
            throw new RuntimeException($method . ': expired requests must remain non-cacheable HTTP 410.');
        }
        if ($hx) {
            if (($headers['HX-Redirect'] ?? '') !== '/companion/' . $token) {
                throw new RuntimeException($method . ': mobile submissions must open the recovery page.');
            }
        } elseif (!str_contains($body, '<html>') || !str_contains($body, 'Scanne den neuen QR-Code') || str_contains($body, $token)) {
            throw new RuntimeException($method . ': missing recovery page or leaked pairing token.');
        }
    }
}
echo "PASS: Companion expiry recovery for page, barcode and photo requests\n";
