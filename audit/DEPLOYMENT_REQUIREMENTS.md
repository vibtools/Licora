# Licora Deployment Requirements

## 1. Runtime Environment
- **PHP Version:** PHP 8.0.0 or newer.
- **Web Server:** Nginx or Apache.
- **Document Root:** Must point to the absolute root directory of the project (e.g., `/app` or `/var/www/html`), not a subfolder like `public/`.

## 2. PHP Extensions Required
- `pdo`
- `pdo_mysql`
- `openssl`
- `json`
- `curl` (Recommended for the updater and external requests, though it falls back to `allow_url_fopen`).

## 3. Storage & Permissions
- **Traditional Server:** The `includes/` directory must be writable by the web server user (e.g., `www-data`) so the installer can generate `.licora-encryption.key`, `.licora-v2-signing-private.pem`, and `config.local.php`.
- **Docker/Coolify Environment:** Instead of making directories writable, cryptographic secrets and database connections **must** be provided securely via environment variables to bypass the file generation phase entirely. The in-app updater must be disabled to maintain an immutable container filesystem.

## 4. Environment Variables
Licora natively supports overriding configurations via environment variables (`env_value()` inside `includes/config.php`):
- `LICENSE_DB_HOST` (Database Hostname, e.g., `mariadb`)
- `LICENSE_DB_PORT` (Database Port, e.g., `3306`)
- `LICENSE_DB_NAME` (Database Name)
- `LICENSE_DB_USER` (Database User)
- `LICENSE_DB_PASS` (Database Password)
- `APP_URL` (The full public URL of the application)
- `APP_ENV` (`production` or `development`)
- `LICENSE_APP_KEY` (64-character random string)
- `LICENSE_ENCRYPTION_KEY` (32-byte encryption key for AES-256)
- `LICENSE_CSRF_SECRET` (Random secret for CSRF token generation)
- `LICENSE_JWT_SECRET` (Secret for token signing)
- `LICENSE_V2_SIGNING_PRIVATE_KEY_PATH` (Absolute path to the pre-generated V2 RSA Private Key)
- `LICENSE_V2_SIGNING_PUBLIC_KEY_PATH` (Absolute path to the pre-generated V2 RSA Public Key)

## 5. Background Processes (Cron)
The application requires the execution of two primary cron jobs to maintain system health, clear rate limits, and suspend expired licenses:
- `php /path/to/project/cron/check_expiring.php` (Recommended: every hour)
- `php /path/to/project/cron/cleanup.php` (Recommended: daily)

## 6. Database Requirements
- **Engine:** MySQL 5.7+ or MariaDB 10.2+.
- **Character Set:** `utf8mb4` (Strictly enforced by installer/config).
- **Collation:** `utf8mb4_unicode_ci` or `utf8mb4_general_ci`.
- **Privileges:** The configured database user **MUST** have the `TRIGGER` privilege to execute schema rules like `update_device_last_active`.
