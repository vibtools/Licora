<?php
// Secure session cookie settings must be applied before session_start().
if (session_status() === PHP_SESSION_NONE) {
    $secureCookie = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $secureCookie,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}
ob_start();

if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
}

if (!function_exists('env_value')) {
    function env_value($key, $default = '') {
        $value = getenv($key);
        return ($value === false || $value === '') ? $default : $value;
    }
}

// Resolve release identity before private local configuration is loaded.
// This prevents an installer-generated local configuration from pinning
// future source upgrades while retaining the APP_VERSION environment override.
if (!defined('APP_VERSION')) define('APP_VERSION', env_value('APP_VERSION', '5.4.1'));

// Optional private local override. Keep this file outside public web root where possible.
$localConfig = __DIR__ . '/config.local.php';
if (file_exists($localConfig)) {
    require_once $localConfig;
}

// ডেটাবেস কনফিগারেশন
if (!defined('DB_HOST')) define('DB_HOST', env_value('LICENSE_DB_HOST', env_value('DB_HOST', 'localhost')));
if (!defined('DB_PORT')) define('DB_PORT', (int)env_value('LICENSE_DB_PORT', env_value('DB_PORT', 3306)));
if (!defined('DB_NAME')) define('DB_NAME', env_value('LICENSE_DB_NAME', env_value('DB_NAME', '')));
if (!defined('DB_USER')) define('DB_USER', env_value('LICENSE_DB_USER', env_value('DB_USER', '')));
if (!defined('DB_PASS')) define('DB_PASS', env_value('LICENSE_DB_PASS', env_value('DB_PASS', '')));

// এপ্লিকেশন সেটিংস
if (!defined('APP_NAME')) define('APP_NAME', env_value('APP_NAME', 'Licora'));
if (!defined('APP_URL')) define('APP_URL', env_value('APP_URL', 'http://localhost'));
if (!defined('APP_TIMEZONE')) define('APP_TIMEZONE', env_value('APP_TIMEZONE', 'Asia/Dhaka'));
if (!defined('APP_LOCALE')) define('APP_LOCALE', env_value('APP_LOCALE', 'en'));
if (!defined('MAIL_FROM_NAME')) define('MAIL_FROM_NAME', env_value('MAIL_FROM_NAME', APP_NAME));
if (!defined('ENVIRONMENT')) define('ENVIRONMENT', env_value('APP_ENV', 'production'));

// সিকিউরিটি সেটিংস
if (!defined('APP_KEY')) define('APP_KEY', env_value('LICENSE_APP_KEY', env_value('APP_KEY', '')));
if (!defined('ENCRYPTION_KEY')) define('ENCRYPTION_KEY', env_value('LICENSE_ENCRYPTION_KEY', ''));
if (!defined('CSRF_SECRET')) define('CSRF_SECRET', env_value('LICENSE_CSRF_SECRET', ''));
if (!defined('JWT_SECRET')) define('JWT_SECRET', env_value('LICENSE_JWT_SECRET', ''));

// API সেটিংস
if (!defined('API_RATE_LIMIT')) define('API_RATE_LIMIT', (int)env_value('API_RATE_LIMIT', 1000));
if (!defined('API_VERSION')) define('API_VERSION', env_value('API_VERSION', 'v1'));

// Licora API v2 public/runtime configuration.
// No server signing private key or API-v1 credential is embedded here.
if (!defined('LICENSE_V2_REQUIRE_HTTPS')) define('LICENSE_V2_REQUIRE_HTTPS', env_value('LICENSE_V2_REQUIRE_HTTPS', '1'));
if (!defined('LICENSE_TRUST_PROXY_HEADERS')) define('LICENSE_TRUST_PROXY_HEADERS', env_value('LICENSE_TRUST_PROXY_HEADERS', '0'));
if (!defined('LICENSE_V2_MAX_BODY_BYTES')) define('LICENSE_V2_MAX_BODY_BYTES', (int)env_value('LICENSE_V2_MAX_BODY_BYTES', 32768));
if (!defined('LICENSE_V2_RATE_LIMIT')) define('LICENSE_V2_RATE_LIMIT', (int)env_value('LICENSE_V2_RATE_LIMIT', 300));
if (!defined('LICENSE_V2_CLOCK_SKEW')) define('LICENSE_V2_CLOCK_SKEW', (int)env_value('LICENSE_V2_CLOCK_SKEW', 300));
if (!defined('LICENSE_V2_SIGNING_KEY_ID')) define('LICENSE_V2_SIGNING_KEY_ID', env_value('LICENSE_V2_SIGNING_KEY_ID', 'primary-v1'));
if (!defined('LICENSE_V2_SIGNING_PRIVATE_KEY_PATH')) define('LICENSE_V2_SIGNING_PRIVATE_KEY_PATH', env_value('LICENSE_V2_SIGNING_PRIVATE_KEY_PATH', __DIR__ . '/.licora-v2-signing-private.pem'));
if (!defined('LICENSE_V2_SIGNING_PUBLIC_KEY_PATH')) define('LICENSE_V2_SIGNING_PUBLIC_KEY_PATH', env_value('LICENSE_V2_SIGNING_PUBLIC_KEY_PATH', __DIR__ . '/.licora-v2-signing-public.pem'));

// Licora v5.3.0 Secure In-App Updater configuration.
if (!defined('LICORA_UPDATE_REPOSITORY')) define('LICORA_UPDATE_REPOSITORY', env_value('LICORA_UPDATE_REPOSITORY', 'vibtools/Licora'));
if (!defined('LICORA_UPDATE_CHECK_INTERVAL')) define('LICORA_UPDATE_CHECK_INTERVAL', (int)env_value('LICORA_UPDATE_CHECK_INTERVAL', 21600));
if (!defined('LICORA_UPDATE_HTTP_TIMEOUT')) define('LICORA_UPDATE_HTTP_TIMEOUT', (int)env_value('LICORA_UPDATE_HTTP_TIMEOUT', 120));
if (!defined('LICORA_UPDATE_MAX_PACKAGE_BYTES')) define('LICORA_UPDATE_MAX_PACKAGE_BYTES', (int)env_value('LICORA_UPDATE_MAX_PACKAGE_BYTES', 104857600));
if (!defined('LICORA_UPDATE_PUBLIC_KEY_PATH')) define('LICORA_UPDATE_PUBLIC_KEY_PATH', env_value('LICORA_UPDATE_PUBLIC_KEY_PATH', __DIR__ . '/updater/update-signing-public.pem'));


// A critical updater lock is filesystem-only and is enforced before installation/database boot logic.
// This prevents ordinary application traffic from entering a partially applied source/schema state.
$updateLockClass = __DIR__ . '/updater/UpdateLock.php';
$updateRuntimeClass = __DIR__ . '/updater/UpdateRuntime.php';
$updateExceptionClass = __DIR__ . '/updater/UpdateException.php';
if (is_file($updateLockClass) && is_file($updateRuntimeClass) && is_file($updateExceptionClass)) {
    require_once $updateExceptionClass;
    require_once $updateRuntimeClass;
    require_once $updateLockClass;
    UpdateLock::enforceRequest();
}

// The installation guard is additive and only redirects incomplete fresh installations.
// Valid existing installations and temporary database outages retain the previous boot flow.
require_once __DIR__ . '/installation.php';
licora_enforce_installation_guard(dirname(__DIR__));

date_default_timezone_set(APP_TIMEZONE);

// এরর রিপোর্টিং
if (ENVIRONMENT === 'production') {
    error_reporting(0);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
    ini_set('log_errors', '1');
}

spl_autoload_register(function ($class_name) {
    $file = __DIR__ . '/classes/' . basename($class_name) . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

set_error_handler(function($severity, $message, $file, $line) {
    error_log("Error [$severity]: $message in $file:$line");
    return true;
});

set_exception_handler(function($exception) {
    http_response_code(500);
    $reference = substr(hash('sha256', microtime(true) . get_class($exception) . $exception->getLine()), 0, 12);
    error_log(
        'Licora exception [' . $reference . '] [' . get_class($exception) . '] at '
        . basename((string)$exception->getFile()) . ':' . (int)$exception->getLine()
    );
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Internal Server Error']);
    exit;
});
?>
