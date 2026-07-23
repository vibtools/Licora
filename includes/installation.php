<?php

declare(strict_types=1);

if (!function_exists('licora_installation_root')) {
    function licora_installation_root(?string $root = null): string
    {
        return $root !== null ? rtrim($root, '/\\') : dirname(__DIR__);
    }
}

if (!function_exists('licora_installation_flag_path')) {
    function licora_installation_flag_path(?string $root = null): string
    {
        return licora_installation_root($root) . '/includes/.licora-installed';
    }
}

if (!function_exists('licora_installation_config_path')) {
    function licora_installation_config_path(?string $root = null): string
    {
        return licora_installation_root($root) . '/includes/config.local.php';
    }
}

if (!function_exists('licora_installation_required_tables')) {
    function licora_installation_required_tables(): array
    {
        return [
            'admin_users',
            'api_keys',
            'api_logs',
            'audit_trail',
            'blacklist',
            'devices',
            'failed_logins',
            'licenses',
            'logs',
            'rate_limits',
            'settings',
        ];
    }
}

if (!function_exists('licora_installation_secret_is_usable')) {
    function licora_installation_secret_is_usable($value): bool
    {
        $value = trim((string)$value);
        if ($value === '') {
            return false;
        }

        $lower = strtolower($value);
        return strpos($lower, 'your-') === false
            && strpos($lower, 'change-this') === false
            && strpos($lower, 'replace-me') === false;
    }
}

if (!function_exists('licora_installation_environment_configured')) {
    function licora_installation_environment_configured(): bool
    {
        foreach (['LICENSE_DB_NAME', 'DB_NAME'] as $key) {
            $value = getenv($key);
            if ($value !== false && trim((string)$value) !== '') {
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('licora_installation_is_installer_request')) {
    function licora_installation_is_installer_request(): bool
    {
        $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
        $filename = str_replace('\\', '/', (string)($_SERVER['SCRIPT_FILENAME'] ?? ''));
        $uri = (string)($_SERVER['REQUEST_URI'] ?? '');

        return basename($script) === 'install.php'
            || strpos($script, '/install/') !== false
            || basename($filename) === 'install.php'
            || preg_match('#/install(?:/|\?|$)#', $uri) === 1;
    }
}

if (!function_exists('licora_installation_base_path')) {
    function licora_installation_base_path(): string
    {
        $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
        foreach (['/admin/', '/api/', '/cron/', '/install/'] as $marker) {
            $position = strpos($script, $marker);
            if ($position !== false) {
                $prefix = substr($script, 0, $position);
                return $prefix === '' ? '' : rtrim($prefix, '/');
            }
        }

        $directory = str_replace('\\', '/', dirname($script));
        return $directory === '/' || $directory === '.' ? '' : rtrim($directory, '/');
    }
}

if (!function_exists('licora_installation_redirect')) {
    function licora_installation_redirect(): void
    {
        if (PHP_SAPI === 'cli' || headers_sent()) {
            return;
        }

        header('Location: ' . licora_installation_base_path() . '/install', true, 302);
        exit;
    }
}

if (!function_exists('licora_installation_parse_host')) {
    function licora_installation_parse_host(string $host, int $port = 3306): array
    {
        $host = trim($host);
        if ($host !== '' && substr_count($host, ':') === 1) {
            [$candidateHost, $candidatePort] = explode(':', $host, 2);
            if ($candidateHost !== '' && ctype_digit($candidatePort)) {
                $host = $candidateHost;
                $port = (int)$candidatePort;
            }
        }

        return [$host, $port];
    }
}

if (!function_exists('licora_installation_dsn')) {
    function licora_installation_dsn(string $host, int $port, string $database = '', string $charset = 'utf8mb4'): string
    {
        [$host, $port] = licora_installation_parse_host($host, $port);
        $dsn = 'mysql:host=' . $host . ';port=' . $port;
        if ($database !== '') {
            $dsn .= ';dbname=' . $database;
        }
        return $dsn . ';charset=' . $charset;
    }
}

if (!function_exists('licora_installation_database_state')) {
    function licora_installation_database_state(): array
    {
        if (!defined('DB_HOST') || !defined('DB_NAME') || !defined('DB_USER')) {
            return ['connected' => false, 'tables_valid' => false];
        }

        $database = trim((string)DB_NAME);
        if ($database === '') {
            return ['connected' => false, 'tables_valid' => false];
        }

        $port = defined('DB_PORT') ? (int)DB_PORT : 3306;
        try {
            $pdo = new PDO(
                licora_installation_dsn((string)DB_HOST, $port, $database),
                (string)DB_USER,
                defined('DB_PASS') ? (string)DB_PASS : '',
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::ATTR_TIMEOUT => 3,
                ]
            );

            $statement = $pdo->query('SHOW TABLES');
            $tables = array_map('strval', $statement->fetchAll(PDO::FETCH_COLUMN));
            $missing = array_diff(licora_installation_required_tables(), $tables);
            return ['connected' => true, 'tables_valid' => $missing === []];
        } catch (Throwable $e) {
            return ['connected' => false, 'tables_valid' => false];
        }
    }
}

if (!function_exists('licora_installation_write_flag')) {
    function licora_installation_write_flag(?string $root = null, ?string $version = null): bool
    {
        $root = licora_installation_root($root);
        $path = licora_installation_flag_path($root);
        $directory = dirname($path);
        if (!is_dir($directory) || !is_writable($directory)) {
            return false;
        }

        $payload = [
            'product' => 'Licora',
            'version' => $version ?? (defined('APP_VERSION') ? (string)APP_VERSION : '5.1.0'),
            'installed_at' => gmdate('c'),
        ];
        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return false;
        }

        $temporary = $path . '.tmp.' . bin2hex(random_bytes(6));
        if (file_put_contents($temporary, $json . PHP_EOL, LOCK_EX) === false) {
            return false;
        }
        @chmod($temporary, 0600);
        if (!@rename($temporary, $path)) {
            @unlink($temporary);
            return false;
        }
        @chmod($path, 0600);
        return true;
    }
}

if (!function_exists('licora_enforce_installation_guard')) {
    function licora_enforce_installation_guard(?string $root = null): void
    {
        if (PHP_SAPI === 'cli' || licora_installation_is_installer_request()) {
            return;
        }

        $root = licora_installation_root($root);
        $configured = is_file(licora_installation_config_path($root)) || licora_installation_environment_configured();
        if (!$configured) {
            licora_installation_redirect();
            return;
        }

        $secretsValid = false;
        foreach (['APP_KEY', 'ENCRYPTION_KEY', 'CSRF_SECRET', 'JWT_SECRET'] as $constant) {
            if (defined($constant) && licora_installation_secret_is_usable(constant($constant))) {
                $secretsValid = true;
                break;
            }
        }
        if (!$secretsValid) {
            licora_installation_redirect();
            return;
        }

        if (is_file(licora_installation_flag_path($root))) {
            return;
        }

        $state = licora_installation_database_state();
        if ($state['connected'] && $state['tables_valid']) {
            licora_installation_write_flag($root);
            return;
        }

        if ($state['connected'] && !$state['tables_valid']) {
            licora_installation_redirect();
            return;
        }

        // Preserve the existing database-error flow during a temporary outage.
        // A configured deployment must never reopen the installer because its DB is unavailable.
    }
}

if (!function_exists('licora_installer_is_locked')) {
    function licora_installer_is_locked(?string $root = null): bool
    {
        $root = licora_installation_root($root);
        if (is_file(licora_installation_flag_path($root))
            || is_file(licora_installation_config_path($root))
            || file_exists($root . '/config.php')) {
            return true;
        }

        if (!licora_installation_environment_configured()) {
            return false;
        }

        foreach (['LICENSE_APP_KEY', 'APP_KEY', 'LICENSE_ENCRYPTION_KEY', 'LICENSE_CSRF_SECRET', 'LICENSE_JWT_SECRET'] as $key) {
            $value = getenv($key);
            if ($value !== false && licora_installation_secret_is_usable($value)) {
                return true;
            }
        }

        // Environment database settings without any usable secret are treated as
        // incomplete first-run configuration and may continue into the installer.
        return false;
    }
}

if (!function_exists('licora_installer_requirements')) {
    function licora_installer_requirements(?string $root = null): array
    {
        $root = licora_installation_root($root);
        $requirements = [
            ['label' => 'PHP 8.0 or newer', 'status' => version_compare(PHP_VERSION, '8.0.0', '>='), 'required' => true, 'detail' => PHP_VERSION],
            ['label' => 'PDO extension', 'status' => extension_loaded('pdo'), 'required' => true, 'detail' => extension_loaded('pdo') ? 'Loaded' : 'Missing'],
            ['label' => 'PDO MySQL extension', 'status' => extension_loaded('pdo_mysql'), 'required' => true, 'detail' => extension_loaded('pdo_mysql') ? 'Loaded' : 'Missing'],
            ['label' => 'OpenSSL extension', 'status' => extension_loaded('openssl'), 'required' => true, 'detail' => extension_loaded('openssl') ? 'Loaded' : 'Missing'],
            ['label' => 'JSON extension', 'status' => extension_loaded('json'), 'required' => true, 'detail' => extension_loaded('json') ? 'Loaded' : 'Missing'],
            ['label' => 'Writable includes directory', 'status' => is_writable($root . '/includes'), 'required' => true, 'detail' => $root . '/includes'],
            ['label' => 'Readable database schema', 'status' => is_readable($root . '/database.sql'), 'required' => true, 'detail' => 'database.sql'],
            ['label' => 'HTTPS transport', 'status' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', 'required' => false, 'detail' => 'Required for production'],
        ];
        return $requirements;
    }
}

if (!function_exists('licora_installer_requirements_pass')) {
    function licora_installer_requirements_pass(array $requirements): bool
    {
        foreach ($requirements as $requirement) {
            if (!empty($requirement['required']) && empty($requirement['status'])) {
                return false;
            }
        }
        return true;
    }
}

if (!function_exists('licora_installer_validate_database')) {
    function licora_installer_validate_database(array $input): array
    {
        $errors = [];
        $host = trim((string)($input['host'] ?? ''));
        $port = (int)($input['port'] ?? 3306);
        $name = trim((string)($input['name'] ?? ''));
        $user = trim((string)($input['user'] ?? ''));
        $prefix = trim((string)($input['table_prefix'] ?? ''));
        $charset = trim((string)($input['charset'] ?? 'utf8mb4'));
        $collation = trim((string)($input['collation'] ?? 'utf8mb4_unicode_ci'));

        if ($host === '' || strlen($host) > 255 || !preg_match('/^[A-Za-z0-9_.\-:\[\]]+$/', $host)) {
            $errors[] = 'Database host is required and may contain only hostname or IP-address characters.';
        }
        if ($port < 1 || $port > 65535) {
            $errors[] = 'Database port must be between 1 and 65535.';
        }
        if (!preg_match('/^[A-Za-z0-9_]+$/', $name)) {
            $errors[] = 'Database name may contain only letters, numbers, and underscores.';
        }
        if ($user === '' || strlen($user) > 128) {
            $errors[] = 'Database username is required.';
        }
        if ($prefix !== '') {
            $errors[] = 'Table prefixes are not supported by the frozen Licora database contract. Leave this field blank.';
        }
        if ($charset !== 'utf8mb4') {
            $errors[] = 'Licora requires the utf8mb4 database charset.';
        }
        if (!in_array($collation, ['utf8mb4_unicode_ci', 'utf8mb4_general_ci'], true)) {
            $errors[] = 'Unsupported database collation.';
        }
        return $errors;
    }
}

if (!function_exists('licora_installer_test_database')) {
    function licora_installer_test_database(array $input): array
    {
        $errors = licora_installer_validate_database($input);
        if ($errors !== []) {
            return ['success' => false, 'message' => $errors[0]];
        }

        try {
            $pdo = new PDO(
                licora_installation_dsn((string)$input['host'], (int)$input['port'], '', (string)$input['charset']),
                (string)$input['user'],
                (string)($input['pass'] ?? ''),
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::ATTR_TIMEOUT => 5,
                ]
            );
            $pdo->query('SELECT 1');
            return ['success' => true, 'message' => 'Database server connection verified.'];
        } catch (Throwable $e) {
            return ['success' => false, 'message' => 'Unable to connect to the database server with the supplied credentials.'];
        }
    }
}

if (!function_exists('licora_installer_validate_admin')) {
    function licora_installer_validate_admin(array $input): array
    {
        $errors = [];
        $name = trim((string)($input['admin_name'] ?? ''));
        $email = trim((string)($input['admin_email'] ?? ''));
        $username = trim((string)($input['admin_username'] ?? ''));
        $password = (string)($input['admin_password'] ?? '');
        $confirm = (string)($input['admin_password_confirm'] ?? '');

        if (strlen($name) < 2 || strlen($name) > 120) {
            $errors[] = 'Administrator name must be between 2 and 120 characters.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 100) {
            $errors[] = 'Enter a valid administrator email address.';
        }
        if (!preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $username)) {
            $errors[] = 'Administrator username must be 3-50 characters and may use letters, numbers, dot, underscore, and hyphen.';
        }
        if (strlen($password) < 12
            || !preg_match('/[A-Z]/', $password)
            || !preg_match('/[a-z]/', $password)
            || !preg_match('/[0-9]/', $password)
            || !preg_match('/[^A-Za-z0-9]/', $password)) {
            $errors[] = 'Password must be at least 12 characters and include uppercase, lowercase, number, and symbol.';
        }
        if (!hash_equals($password, $confirm)) {
            $errors[] = 'Password confirmation does not match.';
        }
        return $errors;
    }
}

if (!function_exists('licora_installer_validate_application')) {
    function licora_installer_validate_application(array $input): array
    {
        $errors = [];
        $name = trim((string)($input['app_name'] ?? ''));
        $timezone = trim((string)($input['timezone'] ?? ''));
        $locale = trim((string)($input['locale'] ?? ''));
        $url = rtrim(trim((string)($input['base_url'] ?? '')), '/');
        $mailFrom = trim((string)($input['mail_from_name'] ?? ''));

        if (strlen($name) < 2 || strlen($name) > 120) {
            $errors[] = 'Application name must be between 2 and 120 characters.';
        }
        if (!in_array($timezone, timezone_identifiers_list(), true)) {
            $errors[] = 'Select a valid PHP timezone.';
        }
        if (!preg_match('/^[A-Za-z]{2,3}(?:[_-][A-Za-z]{2})?$/', $locale)) {
            $errors[] = 'Locale must use a value such as en or en_US.';
        }
        if (!filter_var($url, FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $url)) {
            $errors[] = 'Base URL must be a valid HTTP or HTTPS URL.';
        }
        if (strlen($mailFrom) < 2 || strlen($mailFrom) > 120) {
            $errors[] = 'Mail From Name must be between 2 and 120 characters.';
        }
        return $errors;
    }
}

if (!function_exists('licora_installer_detect_base_url')) {
    function licora_installer_detect_base_url(): string
    {
        $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        $scheme = $secure ? 'https' : 'http';
        $host = preg_replace('/[^A-Za-z0-9.\-:\[\]]/', '', (string)($_SERVER['HTTP_HOST'] ?? 'localhost'));
        return $scheme . '://' . ($host !== '' ? $host : 'localhost') . licora_installation_base_path();
    }
}

if (!function_exists('licora_installer_sql_statements')) {
    function licora_installer_sql_statements(string $sql): array
    {
        $sql = preg_replace('/^\xEF\xBB\xBF/', '', $sql) ?? $sql;
        $delimiter = ';';
        $buffer = '';
        $statements = [];
        foreach (preg_split('/\R/', $sql) ?: [] as $line) {
            if (preg_match('/^\s*DELIMITER\s+(.+)\s*$/i', $line, $matches)) {
                $delimiter = trim($matches[1]);
                continue;
            }
            $buffer .= $line . "\n";
            $trimmed = rtrim($buffer);
            if ($trimmed !== '' && substr($trimmed, -strlen($delimiter)) === $delimiter) {
                $statement = trim(substr($trimmed, 0, -strlen($delimiter)));
                if ($statement !== '') {
                    $statements[] = $statement;
                }
                $buffer = '';
            }
        }
        if (trim($buffer) !== '') {
            $statements[] = trim($buffer);
        }
        return $statements;
    }
}

if (!function_exists('licora_installer_execute_schema')) {
    function licora_installer_execute_schema(PDO $pdo, string $path): void
    {
        $sql = file_get_contents($path);
        if ($sql === false) {
            throw new RuntimeException('Database schema is unavailable.');
        }
        foreach (licora_installer_sql_statements($sql) as $statement) {
            $pdo->exec($statement);
        }
    }
}

if (!function_exists('licora_installer_snapshot_tables')) {
    function licora_installer_snapshot_tables(PDO $pdo): array
    {
        return array_map('strval', $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN));
    }
}

if (!function_exists('licora_installer_cleanup_new_tables')) {
    function licora_installer_cleanup_new_tables(PDO $pdo, array $before): void
    {
        try {
            $after = licora_installer_snapshot_tables($pdo);
            $newTables = array_values(array_intersect(
                array_diff($after, $before),
                licora_installation_required_tables()
            ));
            if ($newTables === []) {
                return;
            }
            $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
            foreach ($newTables as $table) {
                if (preg_match('/^[A-Za-z0-9_]+$/', $table)) {
                    $pdo->exec('DROP TABLE IF EXISTS `' . $table . '`');
                }
            }
            $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
        } catch (Throwable $e) {
            error_log('Licora installer cleanup could not complete.');
        }
    }
}

if (!function_exists('licora_installer_build_config')) {
    function licora_installer_build_config(array $data): string
    {
        $values = [
            'DB_HOST' => (string)$data['db']['host'],
            'DB_PORT' => (int)$data['db']['port'],
            'DB_NAME' => (string)$data['db']['name'],
            'DB_USER' => (string)$data['db']['user'],
            'DB_PASS' => (string)$data['db']['pass'],
            'APP_NAME' => (string)$data['app']['app_name'],
            'APP_URL' => rtrim((string)$data['app']['base_url'], '/'),
            'APP_VERSION' => '5.1.0',
            'APP_TIMEZONE' => (string)$data['app']['timezone'],
            'APP_LOCALE' => (string)$data['app']['locale'],
            'MAIL_FROM_NAME' => (string)$data['app']['mail_from_name'],
            'APP_KEY' => (string)$data['secrets']['app_key'],
            'ENCRYPTION_KEY' => (string)$data['secrets']['encryption_key'],
            'CSRF_SECRET' => (string)$data['secrets']['csrf_secret'],
            'JWT_SECRET' => (string)$data['secrets']['jwt_secret'],
        ];

        $lines = ["<?php", '// Generated by the Licora v5.1.0 first-run installer.'];
        foreach ($values as $name => $value) {
            $lines[] = "if (!defined('{$name}')) define('{$name}', " . var_export($value, true) . ');';
        }
        $lines[] = '?>';
        return implode(PHP_EOL, $lines) . PHP_EOL;
    }
}

if (!function_exists('licora_installer_encrypt')) {
    function licora_installer_encrypt(string $value, string $secret): string
    {
        $master = hash('sha256', $secret, true);
        $encryptionKey = hash_hmac('sha256', 'licora-encryption-v2', $master, true);
        $authenticationKey = hash_hmac('sha256', 'licora-authentication-v2', $master, true);
        $iv = random_bytes(16);
        $ciphertext = openssl_encrypt($value, 'AES-256-CBC', $encryptionKey, OPENSSL_RAW_DATA, $iv);
        if ($ciphertext === false) {
            throw new RuntimeException('Unable to encrypt installer data.');
        }
        $mac = hash_hmac('sha256', $iv . $ciphertext, $authenticationKey, true);
        return 'v2:' . base64_encode($iv . $mac . $ciphertext);
    }
}

if (!function_exists('licora_installer_generate_license_key')) {
    function licora_installer_generate_license_key(): string
    {
        $segments = [];
        for ($i = 0; $i < 4; $i++) {
            $segments[] = strtoupper(bin2hex(random_bytes(4)));
        }
        return implode('-', $segments);
    }
}

if (!function_exists('licora_installer_seed_demo')) {
    function licora_installer_seed_demo(PDO $pdo, int $adminId, array $data): array
    {
        $apiKey = bin2hex(random_bytes(32));
        $apiKeyHash = hash('sha256', $apiKey);
        $encryptedApiKey = licora_installer_encrypt($apiKey, (string)$data['secrets']['encryption_key']);
        $apiStmt = $pdo->prepare(
            'INSERT INTO api_keys (api_key_hash, api_key_encrypted, name, app_name, scope_label, user_id, is_active, rate_limit_per_hour) '
            . 'VALUES (:hash, :encrypted, :name, :app_name, :scope, :user_id, 1, 1000)'
        );
        $apiStmt->execute([
            ':hash' => $apiKeyHash,
            ':encrypted' => $encryptedApiKey,
            ':name' => '[DEMO] Installer API Credential',
            ':app_name' => 'DEMO PRODUCT',
            ':scope' => 'DEMO',
            ':user_id' => $adminId,
        ]);
        $apiKeyId = (int)$pdo->lastInsertId();

        $licenseKey = licora_installer_generate_license_key();
        $licenseStmt = $pdo->prepare(
            'INSERT INTO licenses (license_key, encrypted_key, created_by, notes, app_scope, api_key_id, expires_at, device_limit, status) '
            . 'VALUES (:license_key, :encrypted_key, :created_by, :notes, :app_scope, :api_key_id, :expires_at, 1, \'active\')'
        );
        $licenseStmt->execute([
            ':license_key' => $licenseKey,
            ':encrypted_key' => licora_installer_encrypt($licenseKey, (string)$data['secrets']['encryption_key']),
            ':created_by' => $adminId,
            ':notes' => '[DEMO CUSTOMER] Optional installer demonstration record. Safe to remove.',
            ':app_scope' => 'DEMO PRODUCT',
            ':api_key_id' => $apiKeyId,
            ':expires_at' => gmdate('Y-m-d H:i:s', time() + 30 * 86400),
        ]);
        $licenseId = (int)$pdo->lastInsertId();

        $setting = $pdo->prepare(
            'INSERT INTO settings (setting_key, setting_value) VALUES (:key, :value) '
            . 'ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
        );
        foreach ([
            'demo_data_installed' => '1',
            'demo_api_key_id' => (string)$apiKeyId,
            'demo_license_id' => (string)$licenseId,
        ] as $key => $value) {
            $setting->execute([':key' => $key, ':value' => $value]);
        }

        // Raw credentials are intentionally never returned or displayed by the installer.
        $apiKey = null;
        $licenseKey = null;
        return ['api_key_id' => $apiKeyId, 'license_id' => $licenseId];
    }
}

if (!function_exists('licora_installer_finalize')) {
    function licora_installer_finalize(?string $root, array $data): array
    {
        $root = licora_installation_root($root);
        $db = $data['db'];
        $databaseCreated = false;
        $snapshot = [];
        $pdo = null;
        $configPath = licora_installation_config_path($root);
        $configTemporary = $configPath . '.installing.' . bin2hex(random_bytes(6));
        $flagPath = licora_installation_flag_path($root);
        $flagTemporary = $flagPath . '.installing.' . bin2hex(random_bytes(6));
        $configActivated = false;

        if (is_file($configPath) || is_file($flagPath)) {
            throw new RuntimeException('Licora is already installed.');
        }

        try {
            $server = new PDO(
                licora_installation_dsn((string)$db['host'], (int)$db['port'], '', (string)$db['charset']),
                (string)$db['user'],
                (string)$db['pass'],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]
            );
            $existsStmt = $server->prepare('SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = :name');
            $existsStmt->execute([':name' => (string)$db['name']]);
            $databaseCreated = !$existsStmt->fetchColumn();
            if ($databaseCreated) {
                $server->exec(
                    'CREATE DATABASE `' . $db['name'] . '` CHARACTER SET ' . $db['charset'] . ' COLLATE ' . $db['collation']
                );
            }

            $pdo = new PDO(
                licora_installation_dsn((string)$db['host'], (int)$db['port'], (string)$db['name'], (string)$db['charset']),
                (string)$db['user'],
                (string)$db['pass'],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]
            );
            $snapshot = licora_installer_snapshot_tables($pdo);
            if (array_intersect(licora_installation_required_tables(), $snapshot) !== []) {
                throw new RuntimeException('The target database already contains Licora tables.');
            }

            $configContent = licora_installer_build_config($data);
            if (file_put_contents($configTemporary, $configContent, LOCK_EX) === false) {
                throw new RuntimeException('Unable to write the temporary configuration file.');
            }
            @chmod($configTemporary, 0600);

            $flagPayload = json_encode([
                'product' => 'Licora',
                'version' => '5.1.0',
                'installed_at' => gmdate('c'),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            if ($flagPayload === false || file_put_contents($flagTemporary, $flagPayload . PHP_EOL, LOCK_EX) === false) {
                throw new RuntimeException('Unable to prepare the installation lock.');
            }
            @chmod($flagTemporary, 0600);

            licora_installer_execute_schema($pdo, $root . '/database.sql');

            $pdo->beginTransaction();
            try {
                $pdo->exec("DELETE FROM admin_users WHERE username = 'admin' AND email = 'admin@example.invalid'");
                $adminInsert = $pdo->prepare(
                    'INSERT INTO admin_users (username, password, email, role, failed_attempts, two_factor_enabled, created_at) '
                    . "VALUES (:username, :password, :email, 'super_admin', 0, 0, NOW())"
                );
                $adminInsert->execute([
                    ':username' => (string)$data['admin']['username'],
                    ':password' => (string)$data['admin']['password_hash'],
                    ':email' => (string)$data['admin']['email'],
                ]);
                $adminId = (int)$pdo->lastInsertId();

                $settings = [
                    'administrator_name' => (string)$data['admin']['name'],
                    'system_name' => (string)$data['app']['app_name'],
                    'timezone' => (string)$data['app']['timezone'],
                    'locale' => (string)$data['app']['locale'],
                    'mail_from_name' => (string)$data['app']['mail_from_name'],
                    'api_base_url' => rtrim((string)$data['app']['base_url'], '/') . '/api/verify.php',
                    'installed_version' => '5.1.0',
                    'demo_data_installed' => '0',
                ];
                $settingStmt = $pdo->prepare(
                    'INSERT INTO settings (setting_key, setting_value) VALUES (:key, :value) '
                    . 'ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
                );
                foreach ($settings as $key => $value) {
                    $settingStmt->execute([':key' => $key, ':value' => $value]);
                }

                if (!empty($data['install_demo'])) {
                    licora_installer_seed_demo($pdo, $adminId, $data);
                }
                $pdo->commit();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $e;
            }

            if (!@rename($configTemporary, $configPath)) {
                throw new RuntimeException('Unable to activate the configuration file.');
            }
            $configActivated = true;
            @chmod($configPath, 0600);
            if (!@rename($flagTemporary, $flagPath)) {
                @unlink($configPath);
                $configActivated = false;
                throw new RuntimeException('Unable to activate the installation lock.');
            }
            @chmod($flagPath, 0600);

            return [
                'version' => '5.1.0',
                'username' => (string)$data['admin']['username'],
                'application_url' => rtrim((string)$data['app']['base_url'], '/'),
                'admin_url' => rtrim((string)$data['app']['base_url'], '/') . '/admin/login.php',
                'api_url' => rtrim((string)$data['app']['base_url'], '/') . '/api/verify.php',
                'demo_installed' => !empty($data['install_demo']),
            ];
        } catch (Throwable $e) {
            @unlink($configTemporary);
            @unlink($flagTemporary);
            if (!$configActivated && $pdo instanceof PDO) {
                if ($databaseCreated) {
                    try {
                        $server->exec('DROP DATABASE IF EXISTS `' . $db['name'] . '`');
                    } catch (Throwable $cleanupError) {
                        error_log('Licora installer database cleanup could not complete.');
                    }
                } else {
                    licora_installer_cleanup_new_tables($pdo, $snapshot);
                }
            }
            error_log('Licora installer finalization failed [' . get_class($e) . '].');
            throw new RuntimeException('Installation could not be completed. Verify database permissions and writable directories, then try again.');
        }
    }
}
