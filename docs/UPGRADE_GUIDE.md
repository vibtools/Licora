# Upgrade Guide

## Supported path

```text
v5.0.1 -> v5.0.1.1 -> v5.1.0 -> v5.2.0 -> v5.2.1 -> v5.2.2 -> v5.3.0 -> v5.4.0 -> v5.4.1 -> v5.5.0 -> v5.5.1 -> v5.6.0 -> v5.6.1 -> v5.7.0 -> v5.7.1 -> v5.8.0 -> v5.8.1 -> v5.8.2
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


## v5.2.0 to v5.2.1 maintenance upgrade

v5.2.1 is a source/security-maintenance update for the existing API v2 implementation. It introduces **no new database migration** and keeps the five-table `migration-v5.2.0-api-v2.sql` schema unchanged.

1. Back up the database and all private/runtime files, including `includes/config.local.php`, `includes/.licora-encryption.key`, `includes/.licora-installed`, and both API v2 signing key files when present.
2. Verify the current deployment before changing source.
3. Overwrite the tracked application source with v5.2.1 while preserving the private/runtime files above.
4. Run `python scripts/verify-local.py` when local/Terminal access exists.
5. If API v2 was never provisioned, or you want to verify its provisioning state, use **Admin → Client Apps → Initialize API v2**. CLI-capable hosts may use `php scripts/setup-v2.php` instead.
6. Existing valid signing keys are retained and verified. Partial or mismatched signing-key files are not replaced automatically; correct the deployment files from a trusted backup before retrying.
7. Confirm Client Apps, V2 Devices, activation, refresh, status, deactivation, device limit/revocation, API v1, admin, cron and existing encrypted-data behavior.

The v5.2.1 overwrite does not run the fresh-install wizard and does not require schema changes when API v2 is already provisioned. The authenticated admin provisioning action exists specifically for cPanel/shared-hosting environments without shell access.

## v5.2.1 to v5.2.2 Admin UI schema-detection fix

v5.2.2 is a source-only production correctness patch. It introduces **no database migration** and leaves the existing v5.2.0 five-table API v2 schema unchanged.

1. Back up the database and all private/runtime files.
2. Preserve `includes/config.local.php`, `.licora-encryption.key`, `.licora-installed`, and both API v2 signing-key files.
3. Overwrite tracked application source with v5.2.2.
4. Do not rerun the first-run installer and do not re-provision API v2 when it is already ready.
5. Confirm active Client Apps appear in the existing License API v2 app-scope selector.
6. Confirm successfully activated credentials appear in V2 Devices without a false provisioning warning.
7. Smoke-test API v1 and API v2 clients.

The patch changes only admin metadata discovery and release/test synchronization; API v2 activation, token, proof, refresh, license-scope, device-limit, and revoke contracts remain unchanged.

## v5.2.2 to v5.3.0 Secure Updater bootstrap

v5.3.0 is the **one final manual source upgrade** required before Licora can install later compatible releases from the Admin UI.

1. Back up the database and private/runtime files.
2. Preserve `includes/config.local.php`, `includes/.licora-encryption.key`, `includes/.licora-installed`, API v2 signing keys, logs/backups/exports and deployment environment variables.
3. Replace tracked v5.2.2 source with the verified v5.3.0 release/delta.
4. Do not rerun the fresh installer.
5. Sign in as Super Admin and open **Admin → Updates**. The updater persistence migration is applied idempotently if those tables are not already present.
6. Confirm `update_jobs`, `update_events`, and `app_migrations` are ready and that the Update Center renders.
7. Run the normal admin, license, device, API v1, API v2 and cron smoke tests.
8. Configure the GitHub repository secret `LICORA_UPDATE_SIGNING_PRIVATE_KEY` before publishing the v5.3.0 tag; the matching public key is tracked in `includes/updater/update-signing-public.pem`.

No license/API/device schema is changed by this bootstrap migration. From v5.3.0 onward, normal signed releases can use **Admin → Updates** when preflight passes. See `docs/UPDATER.md`.


## v5.3.0 to v5.4.0 VibTools Light UI migration

v5.4.0 is the first normal signed release designed to be installed by the v5.3.0 Secure Update Center. It introduces **no database migration**.

1. Confirm the installed application reports `5.3.0` and the current deployment works normally.
2. Maintain an external production backup even though the updater creates its normal source rollback backup.
3. Sign in as Super Admin and open **Admin → Updates**.
4. Check for updates and verify the signed `v5.4.0` release is offered.
5. Run Preflight. Installation remains blocked unless signature, package, PHP/extensions, storage, source permissions, disk space and updater compatibility all pass.
6. Install the update and monitor the real VibTools deployment log modal.
7. After completion, verify the new left sidebar/light component UI plus existing Licenses, Devices, API Keys, Client Apps, V2 Devices, Logs, Settings, Admins and updater operations.
8. Smoke-test API v1/API v2 clients and normal cron execution.

The signed v5.4.0 release specification accepts only `5.3.0` as its direct source and declares an empty migration list. The release changes presentation only: API, licensing, device, authentication, cron, database and updater backend contracts remain unchanged.


## v5.4.0 to v5.4.1 updater recovery hotfix

v5.4.1 introduces **no database migration** and preserves the v5.4.0 sidebar/component UI plus all API/license/device/cron contracts.

1. Maintain the normal external production backup.
2. If there is no active updater job, use **Admin → Updates** after the v5.4.1 signed release is published.
3. If an older v5.3.0/v5.4.0 browser controller already left a real job at `running / fetch_manifest / 1%`, do not delete or edit the database job. Apply the verified browser rescue overlay, reload **Updates**, and let the same persistent job resume.
4. Verify the live log modal opens, stages advance beyond `fetch_manifest`, and the job reaches a terminal state.
5. Verify sidebar/UI, API v1/v2, Licenses, Devices, Client Apps/V2 Devices and cron regressions.

The signed v5.4.1 release accepts reviewed direct source versions `5.3.0` and `5.4.0`, with an empty migration list. A pre-existing active job still targets the version recorded when that job was created and must be resumed/finalized before a later release can start.


## v5.6.1 / v5.7.0 to v5.7.1 Dashboard Phase 2 corrective

v5.7.1 is a signed **no-migration** corrective candidate for the Phase 2 Dashboard browser lifecycle. The release specification accepts both the published `v5.6.1` source and an already-applied `v5.7.0` source baseline. It preserves the v5.7.0 layout, 30-second AJAX cadence, backend data contract and application behavior while correcting Retry/auth-lock/synchronous-transport/last-success state handling.

1. Back up application files and the database using the existing procedure.
2. Run the v5.7.1 preflight only after an official signed release is published.
3. Install v5.7.1.
4. Confirm the Dashboard loads from the server-rendered snapshot, refreshes without full-page reload, preserves Retry on failure, and pauses refresh on session expiry.

## v5.6.1 to v5.7.0 Dashboard Phase 2 source baseline

v5.7.0 is a signed **no-migration** Dashboard interaction/UI update from the frozen `v5.6.1` baseline. It keeps the Phase 1 read model and authenticated JSON contract unchanged, replaces the 30-second full-page refresh with 30-second authenticated AJAX polling, and adds manual refresh, last-updated/stale/auth feedback, overlap protection and in-place KPI/chart/activity updates.

1. Preserve normal deployment backups and private configuration/key material.
2. Run the v5.7.0 preflight only after an official signed release is published.
3. Install v5.7.0.
4. Verify initial server-rendered Dashboard content still appears before/without JavaScript refresh.
5. Verify manual Refresh updates data without a full-page reload.
6. Verify the automatic 30-second refresh updates Dashboard data in place and does not overlap a request already in flight.
7. Verify API v1/v2 tracked activity, expiration timeline, recently-seen devices and health facts remain truthful to Phase 1 semantics.
8. Simulate a refresh failure/session expiry in a safe environment and verify the last successful snapshot remains visible with stale/auth feedback.
9. Verify desktop/tablet/mobile layout and existing sidebar/topbar/other admin pages remain unchanged.

No file is deleted and no database migration is executed.

## v5.5.1/v5.6.0 to v5.6.1 Phase 1 verification corrective

v5.6.1 is a signed **no-migration** corrective update. It accepts the official `v5.5.1` source as well as an already-applied `v5.6.0` source, fixes the Dashboard DB integration fixture, aligns the internal Dashboard JSON contract, and makes API v2 readiness require a valid matching signing key pair.

1. Preserve normal deployment backups and private configuration/key material.
2. Run the v5.6.1 preflight after the official signed release is published.
3. Install v5.6.1.
4. Verify Dashboard license/device/API/expiration values and API v2 readiness.
5. Verify API v1/v2, licensing, device enforcement, Cron and updater regressions.
6. Confirm the Dashboard still uses the intentionally preserved 30-second full-page refresh; Phase 2 has not begun.

No file is deleted and no migration is executed.

## v5.5.1 to v5.6.0 Dashboard data foundation — superseded source baseline

v5.6.0 was the no-migration Phase 1 source baseline, but it was **not published as a GitHub tag/release**. PR #8 CI exposed a Dashboard DB-test fixture defect before release acceptance, and v5.6.1 supersedes it.

Do not wait for or publish a v5.6.0 updater release. Deployments on official v5.5.1 should use the published signed v5.6.1 release before the v5.7.0 Phase 2 update. Deployments where the v5.6.0 source delta was already applied are also accepted by the v5.6.1 release specification.

No file deletion or database migration is required for either source path. Reload-free Dashboard polling remains Phase 2.

## v5.5.0 to v5.5.1 Settings/About UI hotfix

v5.5.1 is a signed **no-migration** update from `v5.5.0`. It corrects Settings shortcut distribution, removes the lower-grid blank-space composition, adds a collapsible Settings child menu, and rebuilds About Licora with verified product/company information.

1. Back up the deployment normally.
2. Open **Admin → Updates** on v5.5.0 and run preflight after the official signed v5.5.1 release is published.
3. Install v5.5.1 and verify Settings, nested navigation, and About.

## v5.4.1 to v5.5.0 compact UI and branding

v5.5.0 is a signed **no-migration** update from `v5.4.1`. It refines the VibTools Light component presentation, tracks the supplied Licora brand assets, makes Settings controls truthful to current runtime behavior, adds About and a public-key-only Secure API v2 information/download path, and fixes Windows Python verification portability.

1. Preserve `includes/config.local.php`, encryption/install flags, API v2 deployment keys and `includes/.licora-updater/` runtime data.
2. Open **Admin → Updates** on v5.4.1 and run preflight after the official signed v5.5.0 release is published.
3. Install v5.5.0 and monitor the existing live updater event modal.
4. Verify compact License/Device tables, Single/Bulk license modal, Settings integration information, Settings nested navigation, About, branding and update feedback.
5. Verify API v1/v2, license/device behavior, cron and updater regression gates.

The signed release specification accepts exactly `5.4.1`, declares no migrations and deletes no files. Legacy stored-only Settings keys remain in the database but are no longer exposed as active controls.

## v5.7.1 to v5.8.0 Developer Integration Guide

v5.8.0 is a signed **no-migration** feature release over the published v5.7.1 baseline. It adds the authenticated Developer Guide route, sidebar entry, static downloadable API v2 reference clients and guide-specific tests/styles/scripts. API v1/v2 server behavior, database schema, license/device enforcement, Dashboard, authentication, Cron and updater runtime remain unchanged.


## v5.7.1 / v5.8.0 to v5.8.1 Developer Guide verification correction

v5.8.1 is a signed **no-migration** corrective target that accepts both the published `v5.7.1` baseline and an already-applied `v5.8.0` Developer Guide source candidate. It preserves the guide/examples and API v2 protocol, corrects CI package/manifest version coherence, and restores the two Dashboard device glyphs without changing Dashboard data or refresh behavior.

1. Preserve deployment-private configuration, encryption/install markers, API v2 signing keys and updater runtime data.
2. Use only an official signed v5.8.1 release after remote CI/release gates pass.
3. Verify **API & Clients → Developer Guide**, example downloads, the PowerShell test tool, and the two Dashboard device icons.
4. Confirm API v1/v2, license/device, Dashboard, authentication, Cron and updater regression gates remain green.

## v5.8.1 to v5.8.2 global device icon compatibility hotfix

v5.8.2 is a signed **no-migration** UI compatibility hotfix over the published v5.8.1 release. It replaces all remaining Admin `bi-devices` classes with the Bootstrap Icons 1.8.1-compatible `bi-laptop` glyph and adds a regression gate preventing recurrence.

1. Preserve deployment-private configuration, encryption/install markers, API v2 signing keys and updater runtime data.
2. Install only the official signed v5.8.2 release after remote CI/release gates pass.
3. Verify the Devices icon in the sidebar, Settings shortcut, Device Management header/empty state, License View Devices action, Backup Devices CSV action and About Device Control card.
4. Confirm API v1/v2, licensing/device behavior, Dashboard, Developer Guide, authentication, Cron and updater regression gates remain green.

No file deletion or database migration is required.
