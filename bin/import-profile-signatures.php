<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__).'/lib/lib.inc.php';

use Ceneos\PhpBase\Evidence\ProfileSignature;
use RedBeanPHP\R;

$profiles = new ProfileSignature();
$apply = in_array('--apply', $argv ?? [], true);
$rows = R::getAll("SELECT ceneos_identity_id,report_signature_path FROM oauthuser WHERE ceneos_identity_id IS NOT NULL AND ceneos_identity_id>0 AND report_signature_path<>''");
$counts = ['eligible'=>0,'imported'=>0,'existing'=>0,'skipped'=>0];
$groups = [];
foreach ($rows as $row) $groups[(int)$row['ceneos_identity_id']][] = $row['report_signature_path'];
foreach ($groups as $identity => $paths) {
    if (count(array_unique($paths)) !== 1) { $counts['skipped']++; continue; }
    try {
        if ($profiles->image($identity) !== '') { $counts['existing']++; continue; }
        $counts['eligible']++;
        if ($apply) {
            $profiles->importFile($identity,$paths[0],app_data_root().'/user-signatures');
            $counts['imported']++;
        }
    } catch (Throwable $error) {
        $counts['skipped']++;
        error_log('[profile-signature import] '.get_class($error));
    }
}
echo json_encode($counts,JSON_THROW_ON_ERROR),PHP_EOL;
