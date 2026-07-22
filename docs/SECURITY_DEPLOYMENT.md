# Security Deployment Guide

## Mandatory controls

1. Use HTTPS and redirect HTTP to HTTPS.
2. Change the temporary admin password before internet exposure.
3. Remove or deny `install.php` after installation.
4. Deny direct access to `includes/`, `database.sql`, local configuration, migrations, backups, and `cron/`.
5. Use a least-privilege database user restricted to one database.
6. Set a random `LICENSE_ENCRYPTION_KEY`; do not rely on the deterministic fallback.
7. Keep `APP_ENV=production`.
8. Restrict the legacy simple API at the reverse proxy if it is not required.
9. Protect backup files and audit access.
10. Review the unresolved audit findings.

## Apache

The repository includes root and directory `.htaccess` rules. Confirm `AllowOverride` permits them and test that protected files return a denial.

## Nginx example

```nginx
location ~ ^/(includes|cron)/ { deny all; }
location = /database.sql { deny all; }
location = /install.php { deny all; }
location ~ /(?:config\.local\.php|config\.sample\.php|\.env)$ { deny all; }
```

Enable `install.php` only temporarily from a trusted network when needed.

## PHP configuration

Recommended production posture:

```ini
display_errors = Off
log_errors = On
expose_php = Off
session.cookie_httponly = 1
session.cookie_secure = 1
session.cookie_samesite = Lax
```

The application sets several session cookie properties itself, but server policy should reinforce them.

## Database permissions

The application needs table read/write operations and the installer/migration path needs schema modification. In production, consider separating an elevated migration credential from the lower-privilege runtime credential.

## Logging and privacy

API logs and device records may contain license keys, device identifiers, IP addresses, user agents, and application metadata. Define retention, access control, and lawful handling appropriate to the deployment jurisdiction.

## Frontend dependencies

The admin UI loads code and styles from CDNs. A hardened deployment should vendor reviewed versions or apply integrity and Content Security Policy controls in a future release.
