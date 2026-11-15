<?php

declare(strict_types=1);

final class CentralBillingModule
{
    public static function root(): string
    {
        $source = dirname(__DIR__);
        $paths=[getenv('CENEOS_BILLING_MODULE_ROOT')?:'',dirname($source).'/kb-abrechnung',dirname($source).'/sevdesk/kb-abrechnung',dirname($source,2).'/sevdesk/kb-abrechnung'];
        foreach ($paths as $path) {
            if ($path !== '' && is_file($path . '/lib/OutgoingBilling.php')) {
                return $path;
            }
        }
        throw new \RuntimeException('Das zentrale Abrechnungsmodul ist nicht verfügbar.');
    }
    public static function url(string $path): ?string
    {
        require_once self::root().'/lib/OutgoingRoutes.php';
        if (defined('CENEOS_BILLING_HOSTED') && PHP_SAPI === 'cli') {
            return ($GLOBALS['billing_url'])($path);
        }
        if(\OutgoingRoutes::suffix('pruef',$path)===null)return null;
        $tenant=get_branding();
        $prefix=trim((string)(($tenant['url_prefix']??'')?:($tenant['slug']??'')),'/');
        $base='https://abrechnung.koenigsbl.au'.($prefix!==''?'/'.$prefix:'');
        return \OutgoingRoutes::url('pruef',$path,$base);
    }
    public static function load(string $file): void
    {
        require_once self::root().'/modules/pruef/'.$file;
    }
}
