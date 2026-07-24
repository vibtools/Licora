# Installation

Licora v5.1.0 provides a first-run installer for fresh deployments while preserving the existing manual installation and upgrade paths.

## Requirements

- PHP 8.0 or newer
- `pdo`, `pdo_mysql`, `openssl`, and `json`
- MySQL or MariaDB
- A writable `includes/` directory during first installation
- A readable `database.sql`
- HTTPS for production use

## Fresh installation with the wizard

1. Deploy the complete Licora release.
2. Open `/install` or the preserved `/install.php` route.
3. Complete the ten installer steps.
4. Use a dedicated database account with permission to create or initialize the target database.
5. Supply a strong administrator password. No default administrator credential is retained by the wizard.
6. Leave the table-prefix field blank. Fixed table names are part of the frozen compatibility contract.
7. Choose whether to install optional DEMO data.
8. Confirm the installation lock and complete installation.
9. Sign in manually at `admin/login.php`.

The installer executes the existing `database.sql` schema and migrations. No new table, column, index, constraint, trigger, or migration is introduced by v5.1.0.

## Installation detection

Before normal web application boot, Licora checks installation state.

- No configuration: redirect to `/install`.
- Incomplete fresh database: redirect to `/install`.
- Valid existing installation: continue normal application boot.
- Valid legacy installation without a flag: continue normal boot and create the flag when safe.
- Configured installation with a temporary database outage: preserve the existing database-error flow and never reopen the installer.
- Completed installation: installer routes display `Installation already completed.`

CLI execution is not redirected by the first-run guard.

## Installation lock

A successful wizard run writes:

- `includes/config.local.php`
- `includes/.licora-installed`

The flag stores only product, version, and installation timestamp. It contains no database password, application key, encryption key, administrator password, or generated token.

Do not commit either private runtime file.

## Installer recovery

For intentional recovery only:

1. Remove public access or place the server in private maintenance mode.
2. Back up the complete database.
3. Back up `includes/config.local.php`.
4. Back up `includes/.licora-encryption.key` when present.
5. Back up `includes/.licora-installed`.
6. Follow the recovery procedure in `FIRST_RUN_GUIDE.md`.
7. Restore secure private configuration and the lock before reopening public access.

Never delete or regenerate an existing encryption key unless loss of access to encrypted API-key copies and encrypted license values is acceptable.

## Manual installation

The existing manual process remains supported.

```bash
mysql --host=localhost --user=license_app --password license_system < database.sql
```

Create `includes/config.local.php` from `config.sample.php`, replace every placeholder, and set secure file permissions. Manual installations may create `includes/.licora-installed` with non-secret product/version metadata, or allow Licora to backfill it after the first valid web request.

The sanitized schema includes a temporary local-development account for manual import compatibility:

- Username: `admin`
- Password: `ChangeMe!2026`

Change it immediately. The v5.1.0 wizard replaces that temporary row before installation completes.

## Database port

The wizard and runtime support `DB_PORT`. Existing deployments without that constant continue using port `3306`.

## Cron

Example entries:

```cron
*/5 * * * * /usr/bin/php /var/www/licora/cron/cleanup.php >> /var/log/licora-cleanup.log 2>&1
0 8 * * * /usr/bin/php /var/www/licora/cron/check_expiring.php >> /var/log/licora-expiry.log 2>&1
```

## Verification

After installation:

- Sign in at `admin/login.php`.
- Open `admin/health.php`.
- Create a disposable API key and license.
- Verify with `X-API-Key` and Bearer authentication.
- Confirm device registration and audit logs.
- Test backup restore separately.
- Run `bash scripts/validate.sh`.

## Upgrade installations

Existing v5.0.1 and v5.0.1.1 deployments must not run the first-run wizard. Preserve private configuration and encrypted-key material, replace application source, and follow `UPGRADE_GUIDE.md`.

## v5.1.1 production-readiness checks

Before public exposure:

- Use PHP 8.1, 8.2, or 8.3 with required extensions.
- Keep the application read-only where practical.
- Grant temporary write access only to `includes/` during first installation.
- Confirm private configuration, installation flags, cron paths, audit files, and backups are not web-accessible.
- Use a Base URL without credentials, query parameters, or fragments.
- Restore restrictive permissions after installation.
- Review `COMPATIBILITY_MATRIX.md` for server-specific validation.

Licora defines no dedicated upload, cache, or storage directory. v5.1.1 does not introduce one.
