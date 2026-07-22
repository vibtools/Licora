# Installation

## 1. Prepare the environment

Install PHP 8.0 or later with `pdo_mysql`, `openssl`, and `json`. Create a dedicated database and a database user limited to that database. Enable HTTPS before production use.

## 2. Place files

Deploy the repository under the intended application path. On Apache, allow the supplied `.htaccess` rules. On Nginx, manually reproduce the deny rules in [SECURITY_DEPLOYMENT.md](SECURITY_DEPLOYMENT.md).

## 3. Choose an installation method

### Installer

Open `install.php` once in a private staging environment. Enter the database host, name, username, and password. The installer creates/selects the database, imports `database.sql`, generates cryptographic configuration values, and writes `includes/config.local.php`.

Immediately after completion:

1. Sign in at `admin/login.php`.
2. Change the temporary password.
3. Remove or deny `install.php`.
4. Confirm that `includes/config.local.php` is not web-accessible.

### Manual import

```bash
mysql --host=localhost --user=license_app --password license_system < database.sql
```

Create `includes/config.local.php` from `config.sample.php` and replace every placeholder. Alternatively, provide environment variables.

## 4. Temporary local account

The sanitized schema creates a local-only account:

- Username: `admin`
- Password: `ChangeMe!2026`

Change it immediately. Do not expose a fresh installation to the internet while the temporary account is active.

## 5. Configure cron

Example scheduler entries:

```cron
*/5 * * * * /usr/bin/php /var/www/license-system/cron/cleanup.php >> /var/log/license-system-cleanup.log 2>&1
0 8 * * * /usr/bin/php /var/www/license-system/cron/check_expiring.php >> /var/log/license-system-expiry.log 2>&1
```

The scripts are intended for CLI execution. The repository denies `cron/` over Apache; add equivalent Nginx rules.

## 6. Verify the deployment

- Open `admin/health.php` while authenticated.
- Create a disposable API key and license.
- Verify the license with `X-API-Key`.
- Confirm device registration and logs.
- Test backup restore in a separate database.
- Run `bash scripts/validate.sh` on the deployed source tree.

## Upgrades

Apply migration files in chronological order only after a backup. See [MIGRATIONS.md](MIGRATIONS.md).
