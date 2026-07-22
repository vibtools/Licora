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

// Optional private local override. Keep this file outside public web root where possible.
$localConfig = __DIR__ . '/config.local.php';
if (file_exists($localConfig)) {
    require_once $localConfig;
}

if (!function_exists('env_value')) {
    function env_value($key, $default = '') {
        $value = getenv($key);
        return ($value === false || $value === '') ? $default : $value;
    }
}

// ডেটাবেস কনফিগারেশন
if (!defined('DB_HOST')) define('DB_HOST', env_value('LICENSE_DB_HOST', env_value('DB_HOST', 'localhost')));
if (!defined('DB_NAME')) define('DB_NAME', env_value('LICENSE_DB_NAME', env_value('DB_NAME', '')));
if (!defined('DB_USER')) define('DB_USER', env_value('LICENSE_DB_USER', env_value('DB_USER', '')));
if (!defined('DB_PASS')) define('DB_PASS', env_value('LICENSE_DB_PASS', env_value('DB_PASS', '')));

// এপ্লিকেশন সেটিংস
if (!defined('APP_NAME')) define('APP_NAME', env_value('APP_NAME', 'License System'));
if (!defined('APP_URL')) define('APP_URL', env_value('APP_URL', 'http://localhost'));
if (!defined('APP_VERSION')) define('APP_VERSION', env_value('APP_VERSION', '2.0'));
if (!defined('ENVIRONMENT')) define('ENVIRONMENT', env_value('APP_ENV', 'production'));

// সিকিউরিটি সেটিংস
if (!defined('ENCRYPTION_KEY')) define('ENCRYPTION_KEY', env_value('LICENSE_ENCRYPTION_KEY', ''));
if (!defined('CSRF_SECRET')) define('CSRF_SECRET', env_value('LICENSE_CSRF_SECRET', ''));
if (!defined('JWT_SECRET')) define('JWT_SECRET', env_value('LICENSE_JWT_SECRET', ''));

// API সেটিংস
if (!defined('API_RATE_LIMIT')) define('API_RATE_LIMIT', (int)env_value('API_RATE_LIMIT', 1000));
if (!defined('API_VERSION')) define('API_VERSION', env_value('API_VERSION', 'v1'));

date_default_timezone_set('Asia/Dhaka');

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
    error_log('Uncaught exception: ' . $exception->getMessage() . ' in ' . $exception->getFile() . ':' . $exception->getLine());
    header('Content-Type: application/json; charset=utf-8');
    if (ENVIRONMENT === 'production') {
        echo json_encode(['error' => 'Internal Server Error']);
    } else {
        echo json_encode([
            'error' => 'Internal Server Error',
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine()
        ]);
    }
    exit;
});
?>
