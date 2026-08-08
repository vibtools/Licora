<?php
declare(strict_types=1);
$root = dirname(__DIR__);
$required = [
 'api/v2/activate.php','api/v2/refresh.php','api/v2/status.php','api/v2/deactivate.php',
 'includes/v2/V2KeyManager.php','includes/v2/V2TokenService.php','includes/v2/V2DeviceProof.php',
 'includes/v2/V2Repository.php','includes/v2/ApiV2.php','migration-v5.2.0-api-v2.sql'
];
foreach ($required as $rel) { if (!is_file($root.'/'.$rel)) { fwrite(STDERR,"Missing API v2 file: {$rel}\n"); exit(1); } }
foreach (glob($root.'/api/v2/*.php') as $path) {
    $text = (string)file_get_contents($path);
    if (stripos($text, 'X-API-Key') !== false || preg_match('/\bapi_key\b/i', $text)) { fwrite(STDERR,'Client master API-key marker in '.basename($path)."\n"); exit(1); }
}
$migration = (string)file_get_contents($root.'/migration-v5.2.0-api-v2.sql');
foreach (['v2_client_apps','v2_device_credentials','v2_refresh_tokens','v2_used_nonces','v2_audit_logs'] as $table) {
    if (strpos($migration, 'CREATE TABLE IF NOT EXISTS '.$table) === false) { fwrite(STDERR,"Missing migration table {$table}\n"); exit(1); }
}
if (preg_match('/\b(?:DROP|RENAME)\s+(?:TABLE|COLUMN)\b/i', $migration)) { fwrite(STDERR,"Destructive migration statement detected.\n"); exit(1); }
foreach (['includes/.licora-v2-signing-private.pem','includes/.licora-v2-signing-public.pem'] as $rel) {
    if (is_file($root.'/'.$rel)) { fwrite(STDERR,"Runtime signing key must not be in repository: {$rel}\n"); exit(1); }
}

$bootstrap = (string)file_get_contents($root.'/includes/v2/bootstrap.php');
$verifyPos = strpos($bootstrap, "V2DeviceProof::verify((string)\$context['public_key'], \$signature, \$canonical);");
$noncePos = strpos($bootstrap, "\$repository->rememberNonce((int)\$context['id'], \$nonce, \$skew * 2);");
if ($verifyPos === false || $noncePos === false || $verifyPos > $noncePos) { fwrite(STDERR,"Access-request nonce must be stored only after proof verification.\n"); exit(1); }
foreach (['activate','status','deactivate'] as $endpoint) {
    $text = (string)file_get_contents($root.'/api/v2/'.$endpoint.'.php');
    if (strpos($text, "v2.{$endpoint}.ip") === false) { fwrite(STDERR,"Pre-auth IP rate limit missing for {$endpoint}.\n"); exit(1); }
}
$refresh = (string)file_get_contents($root.'/api/v2/refresh.php');
if (strpos($refresh, "['refresh_token','app_version']") === false) { fwrite(STDERR,"Refresh must require current app_version.\n"); exit(1); }
$refreshVerify = strpos($refresh, "V2DeviceProof::verify((string)\$context['public_key'], \$signature, \$canonical);");
$refreshNonce = strpos($refresh, "\$repository->rememberNonce((int)\$context['device_credential_id'], \$nonce, \$skew * 2);");
if ($refreshVerify === false || $refreshNonce === false || $refreshVerify > $refreshNonce) { fwrite(STDERR,"Refresh nonce must be stored only after proof verification.\n"); exit(1); }

$bootText = (string)file_get_contents($root.'/includes/v2/bootstrap.php');
if (strpos($bootText, 'cleanupExpiredNonces') === false) { fwrite(STDERR,"Expired nonce cleanup is not connected to API v2 runtime.\n"); exit(1); }

echo "API v2 static checks passed.\n";
