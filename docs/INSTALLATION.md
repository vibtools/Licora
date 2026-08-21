# Installation

Licora v5.7.1 provides a first-run installer for fresh deployments while preserving the existing manual installation and upgrade paths.

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

The installer executes `database.sql`, including the additive Secure API v2 schema. Existing v1 tables, columns, indexes, constraints, triggers, routes, and license behavior remain preserved.

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
- `includes/.licora-v2-signing-private.pem`
- `includes/.licora-v2-signing-public.pem`

The flag stores only product, version, and installation timestamp. It contains no database password, application key, encryption key, administrator password, generated token, or API v2 private key. The API v2 private signing key remains server-side deployment material and is never displayed by the installer.

Do not commit either private runtime file.

## Installer recovery

For intentional recovery only:

1. Remove public access or place the server in private maintenance mode.
2. Back up the complete database.
3. Back up `includes/config.local.php`.
4. Back up `includes/.licora-encryption.key` when present.
5. Back up the API v2 signing key pair when present; loss of the private key invalidates access tokens and requires an intentional signing-key rotation.

6. Back up `includes/.licora-installed`.
7. Follow the recovery procedure in `FIRST_RUN_GUIDE.md`.
8. Restore secure private configuration and the lock before reopening public access.

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

Change it immediately. The v5.5.1 wizard replaces that temporary row before installation completes.

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

## v5.5.1 production-readiness checks

Before public exposure:

- Use a supported PHP 8.0–8.4 runtime with the required extensions; production deployments should prefer a currently maintained PHP branch supported by the hosting provider.
- Keep the application read-only where practical.
- Grant temporary write access only to `includes/` during first installation.
- Confirm private configuration, installation flags, cron paths, audit files, and backups are not web-accessible.
- Use a Base URL without credentials, query parameters, or fragments.
- Restore restrictive permissions after installation.
- Review `COMPATIBILITY_MATRIX.md` for server-specific validation.

Licora defines no dedicated upload, cache, or storage directory. v5.5.1 retains private updater runtime storage under `includes/.licora-updater/`; it is created on demand, web-denied by the parent `includes/` rule, ignored by Git, and must be writable by the PHP process for in-app updates.

## Secure API v2 installation

Fresh v5.5.1 wizard installations generate the deployment RSA-3072 API v2 signing key pair automatically and create the additive v2 tables through `database.sql`. The private key is never shown in the UI.

For an existing Licora deployment upgraded from v5.1.0 or v5.2.0, preserve all private/runtime files and overwrite only the application source. Then initialize/verify API v2 by either method:

- **cPanel/shared hosting without Terminal:** sign in as an authorized administrator, open **Admin → Client Apps**, and choose **Initialize API v2**.
- **CLI-capable hosting:** run `php scripts/setup-v2.php` from the Licora project root.

Both paths apply only the existing additive `migration-v5.2.0-api-v2.sql`, generate the signing key pair only when neither key file exists, validate that an existing private/public pair actually matches, and refuse partial or mismatched key material rather than silently replacing deployment identity. If the host database account cannot create the additive tables or the signing-key directory is not writable, provisioning fails without modifying API v1.

### Updater readiness

Fresh v5.5.1 installs include updater persistence in `database.sql`. Existing v5.2.2 deployments receive the same additive schema idempotently when a Super Admin first opens **Admin → Updates**. For future one-click updates, enable `ZipArchive`, OpenSSL, PDO MySQL, and either cURL or HTTPS streams; ensure the PHP process can write the tracked source paths and `includes/.licora-updater/`. The updater never requires Git, SSH, Composer, Python, `exec()`, or `shell_exec()` on the hosted application.

## v5.5.1 product identity

The first-run UI presents the fixed product name **Licora** and uses the tracked Licora branding assets. The installer still writes the same private configuration/runtime data and does not introduce a database migration. `APP_NAME` remains a compatibility configuration value, but the product UI is not a tenant/site-name customization surface.


## v5.7.1 Dashboard Phase 2 verification fix

v5.7.1 adds no installation-time table, column, trigger or migration. It preserves the v5.7.0 Dashboard layout and backend contract while correcting only the client-side refresh lifecycle. Existing v5.6.1 deployments and installations where the v5.7.0 source baseline was already applied are accepted by the v5.7.1 release specification.

## v5.7.0 Dashboard Phase 2 source baseline

v5.7.0 adds no installation-time table, column, trigger or migration. Fresh installations receive the compact Dashboard and reload-free browser controller from source. The Dashboard still reads through the v5.6.1 `DashboardReadModel`/authenticated JSON contract; no new secret, scheduler, API or database configuration is introduced.

## v5.6.1 Dashboard Phase 1 corrected foundation

v5.6.1 adds no installation-time table, column, trigger or migration. Fresh installations receive the corrected Dashboard read model and authenticated Dashboard data endpoint from source. The signed source-compatibility contract accepts v5.5.1 and an already-applied v5.6.0 baseline without schema changes.
