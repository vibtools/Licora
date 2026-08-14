<?php
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/includes/updater/UpdateException.php';
require_once $root . '/includes/updater/ManifestVerifier.php';

function um_ok($value, string $message): void
{
    if (!$value) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
um_ok($key !== false, 'ephemeral RSA key generation');
openssl_pkey_export($key, $private);
$details = openssl_pkey_get_details($key);
$public = $details['key'] ?? '';
um_ok($public !== '', 'ephemeral public key');
$tmp = tempnam(sys_get_temp_dir(), 'licora-updater-pub-');
file_put_contents($tmp, $public);

$manifest = [
    'protocol_version' => 1,
    'application' => 'Licora',
    'version' => '5.3.1',
    'tag' => 'v5.3.1',
    'commit' => str_repeat('a', 40),
    'channel' => 'stable',
    'minimum_updater' => '5.3.0',
    'minimum_php' => '8.0',
    'upgrade_from' => ['5.3.0'],
    'package' => ['name' => 'Licora-5.3.1.zip', 'sha256' => str_repeat('b', 64), 'size' => 1234],
    'migrations' => [],
    'delete_files' => [],
    'files' => ['README.md' => str_repeat('c', 64)],
];
$json = json_encode($manifest, JSON_UNESCAPED_SLASHES);
openssl_sign($json, $sig, $private, OPENSSL_ALGO_SHA256);
$v = new ManifestVerifier($tmp);
$verified = $v->verify($json, $sig, '5.3.1', '5.3.0');
um_ok($verified['version'] === '5.3.1', 'valid signed manifest accepted');

$failed = false;
try { $v->verify($json . ' ', $sig, '5.3.1', '5.3.0'); }
catch (UpdateException $e) { $failed = $e->errorCode() === 'UPDATE_SIGNATURE_INVALID'; }
um_ok($failed, 'tampered manifest rejected');

$unsupported = $manifest;
$unsupported['upgrade_from'] = ['5.2.2'];
$bad = json_encode($unsupported, JSON_UNESCAPED_SLASHES);
openssl_sign($bad, $badSig, $private, OPENSSL_ALGO_SHA256);
$failed = false;
try { $v->verify($bad, $badSig, '5.3.1', '5.3.0'); }
catch (UpdateException $e) { $failed = $e->errorCode() === 'UPDATE_SOURCE_VERSION_UNSUPPORTED'; }
um_ok($failed, 'unsupported direct source-version jump rejected');

$protected = $manifest;
$protected['files']['includes/config.local.php'] = str_repeat('d', 64);
$bad = json_encode($protected, JSON_UNESCAPED_SLASHES);
openssl_sign($bad, $badSig, $private, OPENSSL_ALGO_SHA256);
$failed = false;
try { $v->verify($bad, $badSig, '5.3.1', '5.3.0'); }
catch (UpdateException $e) { $failed = $e->errorCode() === 'UPDATE_PROTECTED_PATH'; }
um_ok($failed, 'protected deployment path rejected');

$invalidSize = $manifest;
$invalidSize['package']['size'] = 0;
$bad = json_encode($invalidSize, JSON_UNESCAPED_SLASHES);
openssl_sign($bad, $badSig, $private, OPENSSL_ALGO_SHA256);
$failed = false;
try { $v->verify($bad, $badSig, '5.3.1', '5.3.0'); }
catch (UpdateException $e) { $failed = $e->errorCode() === 'UPDATE_MANIFEST_INVALID'; }
um_ok($failed, 'non-positive package size rejected');

$overlap = $manifest;
$overlap['delete_files'] = ['README.md'];
$bad = json_encode($overlap, JSON_UNESCAPED_SLASHES);
openssl_sign($bad, $badSig, $private, OPENSSL_ALGO_SHA256);
$failed = false;
try { $v->verify($bad, $badSig, '5.3.1', '5.3.0'); }
catch (UpdateException $e) { $failed = $e->errorCode() === 'UPDATE_MANIFEST_INVALID'; }
um_ok($failed, 'delete/package path overlap rejected');


$controlDelete = $manifest;
$controlDelete['delete_files'] = ['admin/ajax/update-step.php'];
$bad = json_encode($controlDelete, JSON_UNESCAPED_SLASHES);
openssl_sign($bad, $badSig, $private, OPENSSL_ALGO_SHA256);
$failed = false;
try { $v->verify($bad, $badSig, '5.3.1', '5.3.0'); }
catch (UpdateException $e) { $failed = $e->errorCode() === 'UPDATE_CONTROL_DELETE_REJECTED'; }
um_ok($failed, 'protocol v1 control-file deletion rejected');

$badContract = $manifest;
$badContract['minimum_updater'] = 'not-a-version';
$bad = json_encode($badContract, JSON_UNESCAPED_SLASHES);
openssl_sign($bad, $badSig, $private, OPENSSL_ALGO_SHA256);
$failed = false;
try { $v->verify($bad, $badSig, '5.3.1', '5.3.0'); }
catch (UpdateException $e) { $failed = $e->errorCode() === 'UPDATE_MANIFEST_INVALID'; }
um_ok($failed, 'invalid compatibility version rejected');


$caseCollision = $manifest;
$caseCollision['files']['readme.md'] = str_repeat('d', 64);
$bad = json_encode($caseCollision, JSON_UNESCAPED_SLASHES);
openssl_sign($bad, $badSig, $private, OPENSSL_ALGO_SHA256);
$failed = false;
try { $v->verify($bad, $badSig, '5.3.1', '5.3.0'); }
catch (UpdateException $e) { $failed = $e->errorCode() === 'UPDATE_MANIFEST_INVALID'; }
um_ok($failed, 'case-colliding source paths rejected');

$nonIdempotent = $manifest;
$nonIdempotent['files']['migration-test.sql'] = str_repeat('e', 64);
$nonIdempotent['migrations'] = [[
    'id' => 'test.migration', 'path' => 'migration-test.sql', 'checksum' => str_repeat('e',64),
    'destructive' => false, 'idempotent' => false, 'rollback_path' => null,
]];
$bad = json_encode($nonIdempotent, JSON_UNESCAPED_SLASHES);
openssl_sign($bad, $badSig, $private, OPENSSL_ALGO_SHA256);
$failed = false;
try { $v->verify($bad, $badSig, '5.3.1', '5.3.0'); }
catch (UpdateException $e) { $failed = $e->errorCode() === 'UPDATE_MANIFEST_INVALID'; }
um_ok($failed, 'non-destructive migration must be explicitly idempotent');

$duplicateMigration = $manifest;
$duplicateMigration['files']['migration-test.sql'] = str_repeat('e', 64);
$migration = ['id'=>'test.migration','path'=>'migration-test.sql','checksum'=>str_repeat('e',64),'destructive'=>false,'idempotent'=>true,'rollback_path'=>null];
$duplicateMigration['migrations'] = [$migration,$migration];
$bad = json_encode($duplicateMigration, JSON_UNESCAPED_SLASHES);
openssl_sign($bad, $badSig, $private, OPENSSL_ALGO_SHA256);
$failed = false;
try { $v->verify($bad, $badSig, '5.3.1', '5.3.0'); }
catch (UpdateException $e) { $failed = $e->errorCode() === 'UPDATE_MANIFEST_INVALID'; }
um_ok($failed, 'duplicate migration IDs rejected');

$badMigrationList = $manifest;
$badMigrationList['migrations'] = 'not-a-list';
$bad = json_encode($badMigrationList, JSON_UNESCAPED_SLASHES);
openssl_sign($bad, $badSig, $private, OPENSSL_ALGO_SHA256);
$failed = false;
try { $v->verify($bad, $badSig, '5.3.1', '5.3.0'); }
catch (UpdateException $e) { $failed = $e->errorCode() === 'UPDATE_MANIFEST_INVALID'; }
um_ok($failed, 'non-array migration list rejected');

@unlink($tmp);
echo "Updater signed-manifest checks passed.\n";
