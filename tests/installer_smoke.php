<?php

declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/includes/installation.php';

$failures = [];
$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};

$assert(licora_installation_secret_is_usable(str_repeat('a', 64)), 'usable application secret accepted');
$assert(!licora_installation_secret_is_usable('your-secret-change-this'), 'placeholder secret rejected');

$validDatabase = [
    'host' => 'localhost',
    'port' => 3306,
    'name' => 'license_system',
    'user' => 'license_user',
    'pass' => 'not-logged',
    'table_prefix' => '',
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
];
$assert(licora_installer_validate_database($validDatabase) === [], 'valid database configuration accepted');
$prefixedDatabase = $validDatabase;
$prefixedDatabase['table_prefix'] = 'licora_';
$assert(licora_installer_validate_database($prefixedDatabase) !== [], 'table prefix rejected by frozen schema contract');

$validAdmin = [
    'admin_name' => 'Licora Administrator',
    'admin_email' => 'admin@example.com',
    'admin_username' => 'licora_admin',
    'admin_password' => 'StrongPassword!2026',
    'admin_password_confirm' => 'StrongPassword!2026',
];
$assert(licora_installer_validate_admin($validAdmin) === [], 'strong administrator credentials accepted');
$weakAdmin = $validAdmin;
$weakAdmin['admin_password'] = 'weak';
$weakAdmin['admin_password_confirm'] = 'weak';
$assert(licora_installer_validate_admin($weakAdmin) !== [], 'weak administrator password rejected');

$validApplication = [
    'app_name' => 'Licora',
    'timezone' => 'Asia/Dhaka',
    'locale' => 'en',
    'base_url' => 'https://licenses.example.com',
    'mail_from_name' => 'Licora',
];
$assert(licora_installer_validate_application($validApplication) === [], 'valid application configuration accepted');

$unsafeUrl = $validApplication;
$unsafeUrl['base_url'] = 'https://user:password@licenses.example.com/licora';
$assert(licora_installer_validate_application($unsafeUrl) !== [], 'base URL credentials rejected');
$unsafeUrl['base_url'] = 'https://licenses.example.com/licora?token=secret';
$assert(licora_installer_validate_application($unsafeUrl) !== [], 'base URL query rejected');
$unsafeUrl['base_url'] = 'https://licenses.example.com/licora#fragment';
$assert(licora_installer_validate_application($unsafeUrl) !== [], 'base URL fragment rejected');

$unsafeMail = $validApplication;
$unsafeMail['mail_from_name'] = "Licora\r\nBcc: attacker@example.com";
$assert(licora_installer_validate_application($unsafeMail) !== [], 'mail-from line break rejected');

$assert(licora_installer_generated_secret_is_valid(str_repeat('a', 64)), 'generated secret validation accepted');
$assert(!licora_installer_generated_secret_is_valid('replace-me'), 'invalid generated secret rejected');

$sql = "CREATE TABLE sample (id INT);\nDELIMITER $$\nCREATE TRIGGER sample_trigger BEFORE INSERT ON sample FOR EACH ROW SET NEW.id = 1$$\nDELIMITER ;\nINSERT INTO sample (id) VALUES (1);\n";
$statements = licora_installer_sql_statements($sql);
$assert(count($statements) === 3, 'schema parser handles custom delimiters');
$assert(strpos($statements[1] ?? '', 'CREATE TRIGGER') !== false, 'trigger statement preserved');

$data = [
    'db' => $validDatabase,
    'admin' => [
        'name' => 'Licora Administrator',
        'email' => 'admin@example.com',
        'username' => 'licora_admin',
        'password_hash' => password_hash('StrongPassword!2026', PASSWORD_BCRYPT),
    ],
    'app' => $validApplication,
    'secrets' => [
        'app_key' => str_repeat('1', 64),
        'encryption_key' => str_repeat('2', 64),
        'csrf_secret' => str_repeat('3', 64),
        'jwt_secret' => str_repeat('4', 64),
    ],
];
$config = licora_installer_build_config($data);
$assert(strpos($config, "define('APP_VERSION', '5.1.1')") !== false, 'generated configuration targets v5.1.1');
$assert(strpos($config, "define('DB_PORT', 3306)") !== false, 'generated configuration includes database port');

$encrypted = licora_installer_encrypt('installer-secret-test', str_repeat('2', 64));
$assert(strpos($encrypted, 'v2:') === 0, 'installer demo encryption uses versioned format');
$assert(strpos($encrypted, 'installer-secret-test') === false, 'installer encryption hides plaintext');

$license = licora_installer_generate_license_key();
$assert(preg_match('/^[A-Z0-9]{8}-[A-Z0-9]{8}-[A-Z0-9]{8}-[A-Z0-9]{8}$/', $license) === 1, 'demo license follows existing license format');

$tempRoot = sys_get_temp_dir() . '/licora-installer-test-' . bin2hex(random_bytes(5));
mkdir($tempRoot . '/includes', 0700, true);
$assert(licora_installation_write_flag($tempRoot, '5.1.1'), 'installation flag written atomically');
$flag = json_decode((string)file_get_contents($tempRoot . '/includes/.licora-installed'), true);
$assert(($flag['product'] ?? '') === 'Licora', 'installation flag identifies Licora');
$assert(($flag['version'] ?? '') === '5.1.1', 'installation flag records version');
$assert(!isset($flag['database_password']) && !isset($flag['encryption_key']), 'installation flag contains no secrets');

$assert(
    licora_installer_locked_request_action(false, 1, false, false, false) === 'continue',
    'fresh installer requests continue'
);
$assert(
    licora_installer_locked_request_action(true, 1, true, true, false) === 'locked',
    'same-session root revisit remains locked after installation'
);
$assert(
    licora_installer_locked_request_action(true, 2, true, true, false) === 'locked',
    'same-session wizard steps remain locked after installation'
);
$assert(
    licora_installer_locked_request_action(true, 9, true, true, false) === 'show_success',
    'one pending completion request may show the success screen'
);
$assert(
    licora_installer_locked_request_action(true, 9, true, false, true) === 'locked',
    'success screen cannot be replayed after its pending view is consumed'
);
$assert(
    licora_installer_locked_request_action(true, 10, true, false, true) === 'redirect_login',
    'pending completion flow may redirect to admin login'
);
$assert(
    licora_installer_locked_request_action(true, 10, true, false, false) === 'locked',
    'direct step-ten access without the completion handoff remains locked'
);

@unlink($tempRoot . '/includes/.licora-installed');
@rmdir($tempRoot . '/includes');
@rmdir($tempRoot);

$installer = (string)file_get_contents($root . '/install.php');
foreach ([
    'Step <?php echo $step; ?> of 10',
    'installer_csrf_token',
    'Installation already completed.',
    'Complete Installation',
    'Go to Login',
    'licora_installer_success_view_pending',
    'licora_installer_login_redirect_pending',
] as $marker) {
    $assert(strpos($installer, $marker) !== false, 'installer marker present: ' . $marker);
}
$assert(
    strpos($installer, '&& !is_array($successData)') === false,
    'success-session data no longer bypasses the installation lock'
);

if ($failures !== []) {
    fwrite(STDERR, "Installer smoke test failed:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "Installer smoke test passed.\n";
