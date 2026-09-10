<?php
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/includes/updater/UpdateException.php';
require_once $root . '/includes/updater/UpdateRuntime.php';
require_once $root . '/includes/updater/UpdateService.php';

function us_ok($value, string $message): void
{
    if (!$value) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$expected = [
    'fetch_manifest', 'preflight', 'download', 'stage_archive', 'backup_source',
    'backup_database', 'lock_update', 'migrate', 'apply_files', 'post_verify',
    'cleanup', 'rollback_migrations', 'rollback_source', 'rollback_finalize',
];
foreach ($expected as $stage) {
    us_ok(in_array($stage, UpdateService::STAGES, true), 'missing state ' . $stage);
}

$lock = (string)file_get_contents($root . '/includes/updater/UpdateLock.php');
foreach (['Retry-After: 5', 'UPDATE_IN_PROGRESS', 'updates\\.php', 'login\\.php', 'update-[A-Za-z0-9_-]+\\.php'] as $marker) {
    us_ok(strpos($lock, $marker) !== false, 'update-lock recovery contract missing ' . $marker);
}

$spec = json_decode((string)file_get_contents($root . '/update/release-spec.json'), true);
us_ok(($spec['version'] ?? '') === '5.8.4', 'release spec version');
us_ok(($spec['channel'] ?? '') === 'stable', 'release spec stable channel');
us_ok(
    ($spec['upgrade_from'] ?? []) === ['5.8.2', '5.8.3'],
    'v5.8.4 release spec accepts published v5.8.2 and frozen v5.8.3 baselines'
);

$migrations = $spec['migrations'] ?? [];
us_ok(count($migrations) === 1, 'v5.8.4 release spec carries one bridge migration');
$migration = $migrations[0] ?? [];
us_ok(($migration['id'] ?? '') === 'v5.8.3.scoped-admin-license-api', 'bridge migration ID');
us_ok(($migration['path'] ?? '') === 'migration-v5.8.3-admin-license-api.sql', 'bridge migration path');
us_ok(($migration['destructive'] ?? true) === false, 'bridge migration is non-destructive');
us_ok(($migration['idempotent'] ?? false) === true, 'bridge migration is idempotent');
us_ok(($migration['rollback_path'] ?? 'invalid') === null, 'additive bridge migration has no destructive rollback');

echo "Updater state-machine checks passed.\n";
