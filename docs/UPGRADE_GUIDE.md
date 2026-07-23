# Upgrade Guide

## Supported path

```text
v5.0.1 -> v5.0.1.1 -> v5.1.0
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

## Upgrade from v5.1.0 to v5.1.1

1. Back up the database and private runtime files.
2. Record environment variables and cron configuration.
3. Replace application source with v5.1.1.
4. Preserve all private configuration and encryption-key material.
5. Do not run the first-run installer.
6. Run repository validation.
7. Verify admin, API, license, device, cron, export, settings, logging, and legacy API behavior.

No database migration is required. Existing installation flags may continue recording the original installation version; they are installation records, not an upgrade ledger.
