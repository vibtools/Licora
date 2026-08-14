<?php
if (!defined('DB_HOST')) define('DB_HOST', 'localhost');
if (!defined('DB_PORT')) define('DB_PORT', 3306);
if (!defined('DB_NAME')) define('DB_NAME', 'license_system');
if (!defined('DB_USER')) define('DB_USER', 'root');
if (!defined('DB_PASS')) define('DB_PASS', '');
if (!defined('APP_NAME')) define('APP_NAME', 'Licora');
if (!defined('APP_URL')) define('APP_URL', 'http://localhost/licora');
if (!defined('APP_VERSION')) define('APP_VERSION', '5.3.0');
if (!defined('APP_TIMEZONE')) define('APP_TIMEZONE', 'Asia/Dhaka');
if (!defined('APP_LOCALE')) define('APP_LOCALE', 'en');
if (!defined('MAIL_FROM_NAME')) define('MAIL_FROM_NAME', 'Licora');
if (!defined('APP_KEY')) define('APP_KEY', 'replace-me-with-a-random-64-character-secret');
if (!defined('ENCRYPTION_KEY')) define('ENCRYPTION_KEY', 'your-32-byte-encryption-key-here-change-this');
if (!defined('CSRF_SECRET')) define('CSRF_SECRET', 'your-csrf-secret-key-change-this');
if (!defined('JWT_SECRET')) define('JWT_SECRET', 'your-jwt-secret-key-change-this');
if (!defined('LICENSE_V2_REQUIRE_HTTPS')) define('LICENSE_V2_REQUIRE_HTTPS', '1');
if (!defined('LICENSE_TRUST_PROXY_HEADERS')) define('LICENSE_TRUST_PROXY_HEADERS', '0');
if (!defined('LICENSE_V2_MAX_BODY_BYTES')) define('LICENSE_V2_MAX_BODY_BYTES', 32768);
if (!defined('LICENSE_V2_RATE_LIMIT')) define('LICENSE_V2_RATE_LIMIT', 300);
if (!defined('LICENSE_V2_CLOCK_SKEW')) define('LICENSE_V2_CLOCK_SKEW', 300);
if (!defined('LICENSE_V2_SIGNING_KEY_ID')) define('LICENSE_V2_SIGNING_KEY_ID', 'primary-v1');
if (!defined('LICENSE_V2_SIGNING_PRIVATE_KEY_PATH')) define('LICENSE_V2_SIGNING_PRIVATE_KEY_PATH', __DIR__ . '/.licora-v2-signing-private.pem');
if (!defined('LICENSE_V2_SIGNING_PUBLIC_KEY_PATH')) define('LICENSE_V2_SIGNING_PUBLIC_KEY_PATH', __DIR__ . '/.licora-v2-signing-public.pem');
// Security boundary: the updater rejects any value other than the official repository.
if (!defined('LICORA_UPDATE_REPOSITORY')) define('LICORA_UPDATE_REPOSITORY', 'vibtools/Licora');
if (!defined('LICORA_UPDATE_CHECK_INTERVAL')) define('LICORA_UPDATE_CHECK_INTERVAL', 21600);
if (!defined('LICORA_UPDATE_HTTP_TIMEOUT')) define('LICORA_UPDATE_HTTP_TIMEOUT', 120);
if (!defined('LICORA_UPDATE_MAX_PACKAGE_BYTES')) define('LICORA_UPDATE_MAX_PACKAGE_BYTES', 104857600);
if (!defined('LICORA_UPDATE_PUBLIC_KEY_PATH')) define('LICORA_UPDATE_PUBLIC_KEY_PATH', __DIR__ . '/updater/update-signing-public.pem');
?>
