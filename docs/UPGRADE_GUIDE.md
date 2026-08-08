# Upgrade Guide

## Supported path

```text
v5.0.1 -> v5.0.1.1 -> v5.1.0 -> v5.2.0
```

The v5.1.0 installer is for fresh installations only. Existing deployments are never required to reinstall.

## Upgrade procedure

1. Back up the complete database.
2. Back up `includes/config.local.php`.
3. Back up `includes/.licora-encryption.key` when present.
4. Record environment variables used by the deployment.
5. Confirm the existing application works before upgrading.
6. Replace source files with v5.1.0.
7. Preserve all private configuration and encrypted-key material.
8. Do not open the installer.
9. Run `bash scripts/validate.sh`.
10. Sign in and run admin, API, license, device, dashboard, cron, and settings regression checks.

## No database migration

Phase 2 does not change the database schema. Do not create a v5.1.0 migration. Existing schema and migration files remain byte-for-byte unchanged.

## Legacy configuration

Older configurations without these constants remain supported:

- `DB_PORT` defaults to `3306`.
- `APP_TIMEZONE` defaults to `Asia/Dhaka`.
- `APP_LOCALE` defaults to `en`.
- `MAIL_FROM_NAME` defaults to `APP_NAME`.
- `APP_KEY` is additive and is not required to force an existing installation through the installer.

## Installation flag backfill

A valid existing installation without `includes/.licora-installed` continues normal boot. After configuration, database connection, required-table, and secret checks pass, Licora attempts to create the non-secret flag. Failure to backfill the flag does not interrupt an otherwise valid legacy deployment.

## Database outage behavior

A configured deployment with a temporarily unavailable database retains the existing database-error response. It is never redirected into the fresh installer.

## Verification matrix

- Admin login/logout
- Session timeout
- License create/verify
- Device register/reconnect
- `X-API-Key`
- Bearer authentication
- Legacy API
- Viewer restrictions
- Manager/Super Admin actions
- Dashboard
- Cron
- Settings
- Installer lock
- Legacy encrypted values

## Release-version precedence

The preserved `includes/config.local.php` file may contain the version recorded by an older installer. v5.1.0 resolves runtime release identity from the source before loading private configuration, so a preserved local `APP_VERSION` definition does not pin future source upgrades. Existing database values, application settings, security secrets, and explicit environment-based version overrides remain supported.

Existing installation flags may continue recording the original installation version; they are installation records, not an upgrade ledger.

## v5.1.0 to v5.2.0 Secure API v2 upgrade

The v5.1.0 first-run installer remains a historical fresh-install baseline. Existing v5.1.0 deployments upgrade in place and must not rerun the installer.

1. Back up the database, `includes/config.local.php`, `includes/.licora-encryption.key` when present, installation flag, and any existing API v2 signing files.
2. Replace application source with v5.2.0 while preserving all private/runtime files.
3. Run `python scripts/verify-local.py` to verify source before changing the database.
4. Run `php scripts/setup-v2.php` once. It applies the additive API v2 migration and creates the deployment signing key pair only when absent.
5. Register a Client App in `admin/client_apps.php`.
6. Bind test licenses to the exact API v2 App ID using the new API v2 Client App selector.
7. Verify activation, refresh, status, deactivation, device limit, revocation, API v1 regression, admin, cron and existing encrypted-data behavior.

`migration-v5.2.0-api-v2.sql` creates only the five `v2_*` tables. It does not drop, rename, or replace existing v1 tables. API v1 endpoints and their shared API-key behavior remain unchanged for compatibility.
