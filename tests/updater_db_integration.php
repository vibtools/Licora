<?php
declare(strict_types=1);

if (getenv('LICORA_V2_TEST_ALLOW_SCHEMA_RESET') !== '1') {
    echo "Updater DB integration skipped (dedicated test DB not enabled).\n";
    exit(0);
}

$root = dirname(__DIR__);
require_once $root . '/includes/updater/UpdateException.php';
require_once $root . '/includes/updater/UpdateRuntime.php';
require_once $root . '/includes/updater/UpdateSchema.php';
require_once $root . '/includes/updater/UpdateRepository.php';
require_once $root . '/includes/updater/UpdateLock.php';

function ud_ok($value, string $message): void
{
    if (!$value) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$dsn = getenv('LICORA_TEST_DB_DSN') ?: '';
$user = getenv('LICORA_TEST_DB_USER') ?: '';
$pass = getenv('LICORA_TEST_DB_PASS') ?: '';
ud_ok($dsn !== '', 'test DSN required');

$db = new PDO($dsn, $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
]);

// The API v2 integration test intentionally leaves a minimal database behind. The
// updater integration test owns its own prerequisites and must not depend on test order.
$db->exec('SET FOREIGN_KEY_CHECKS=0');
foreach (['update_events', 'update_jobs', 'app_migrations', 'settings'] as $table) {
    $db->exec('DROP TABLE IF EXISTS `' . $table . '`');
}
$db->exec('SET FOREIGN_KEY_CHECKS=1');

$schema = new UpdateSchema($db, $root . '/migration-v5.3.0-updater.sql');
$missingBaseRejected = false;
try {
    $schema->ensure();
} catch (UpdateException $e) {
    $missingBaseRejected = $e->errorCode() === 'UPDATER_BASE_SCHEMA_MISSING';
}
ud_ok($missingBaseRejected, 'missing core settings schema is rejected with a controlled updater error');

$db->exec("CREATE TABLE settings (
    id INT NOT NULL AUTO_INCREMENT,
    setting_key VARCHAR(100) NOT NULL,
    setting_value TEXT DEFAULT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_settings_key (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$status = $schema->ensure();
ud_ok($status['ready'], 'updater schema ready');
ud_ok($schema->ensure()['ready'], 'updater migration idempotent');

$seeded = $db->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('updater_auto_check','updater_check_interval_seconds','updater_channel') ORDER BY setting_key")->fetchAll(PDO::FETCH_KEY_PAIR);
ud_ok(($seeded['updater_auto_check'] ?? null) === '1', 'updater auto-check setting seeded');
ud_ok(($seeded['updater_check_interval_seconds'] ?? null) === '21600', 'updater check interval seeded');
ud_ok(($seeded['updater_channel'] ?? null) === 'stable', 'updater stable channel marker seeded');

$repo = new UpdateRepository($db);
$coordinatorValue = $repo->withCoordinatorLock(static fn(): string => 'locked');
ud_ok($coordinatorValue === 'locked', 'updater coordinator lock works on the prepared core settings schema');

$job = $repo->createJob([
    'admin_id' => null,
    'from_version' => '5.3.0',
    'target_version' => '5.3.1',
    'release_tag' => 'v5.3.1',
    'release_url' => 'https://github.com/vibtools/Licora/releases/tag/v5.3.1',
    'context' => ['fixture' => true],
]);
ud_ok(strlen((string)$job['job_uuid']) === 36, 'job persisted');

$id = $repo->appendEvent((string)$job['job_uuid'], 'info', 'test', 'TEST_EVENT', 'Updater DB integration event');
ud_ok($id > 0, 'event persisted');
ud_ok(count($repo->eventsSince((string)$job['job_uuid'], 0)) === 1, 'event stream readable');

$repo->startMigration('test-updater-migration', '5.3.1', str_repeat('a', 64));
$repo->finishMigration('test-updater-migration', 5);
$migration = $repo->migration('test-updater-migration');
ud_ok(($migration['status'] ?? '') === 'applied', 'migration ledger persisted');

// A truncated/corrupt filesystem lock must never strand ordinary Licora traffic in 503.
UpdateRuntime::ensure();
file_put_contents(UpdateLock::path(), '{corrupt-lock-json', LOCK_EX);
ud_ok(UpdateLock::recoverOrphaned($repo), 'corrupt critical lock metadata recovered');
ud_ok(!is_file(UpdateLock::path()), 'corrupt critical lock file removed');

$repo->updateJob((string)$job['job_uuid'], [
    'status' => 'success',
    'stage' => 'complete',
    'progress' => 100,
    'finished_at' => date('Y-m-d H:i:s'),
]);

echo "Updater DB integration checks passed.\n";
