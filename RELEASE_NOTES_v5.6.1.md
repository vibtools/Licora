# Licora v5.6.1 — Dashboard Phase 1 Verification Fix

**Release type:** Corrective Phase 1 verification release
**Source baselines:** v5.5.1 and applied v5.6.0
**Database migration:** None
**Deleted files:** None
**External API v1/v2 contracts:** Unchanged
**License/device enforcement:** Unchanged
**Updater protocol/state machine:** Unchanged
**Phase 2 reload-free Dashboard UI:** Not included

## Why v5.6.1 exists

GitHub Actions PR #8 run `32420291770` exposed a real test-fixture defect after all PHP 8.0–8.4 validation jobs and Windows Python portability passed. The MySQL integration job failed in `tests/dashboard_db_integration.php` because it attempted to drop `v2_device_credentials` while `v2_refresh_tokens.fk_v2_refresh_device` still referenced it.

The audit also found two Phase 1 truth/contract mismatches that the original static tests did not catch:

1. the documented top-level `recent_activity` response field was absent from the actual Dashboard endpoint; and
2. the Dashboard could report Secure API v2 as `Ready` from schema + public-key presence even if the server private signing key was missing or mismatched.

## Fixed

- Made the Dashboard DB integration fixture isolate the full relevant v2 table set with foreign-key-safe cleanup.
- Added minimal v2 fixture tables required to verify complete schema readiness in the Dashboard integration test.
- Added top-level `recent_activity.v1_tracked` and `recent_activity.v2_tracked` to the Dashboard snapshot/JSON contract without removing the existing nested source-specific activity arrays.
- Changed Dashboard API v2 readiness to require a readable cryptographically matching private/public signing key pair plus the complete v2 schema.
- Extended Dashboard contract tests to enforce the corrected response shape and key-pair readiness rule.
- Added a verifier guard confirming Licora runtime does not contain a Chrome-specific launcher/download dependency.

## Browser portability finding

Licora is server-rendered PHP web software. The audited baseline contains no Google Chrome installer, Chrome executable launcher, or Google Chrome download URL. Therefore the reported “Chrome unavailable → Chrome download failed” behavior is not produced by this Licora repository and no unsupported browser-downloader implementation was invented. Licora continues to work through a compatible user-selected browser.

## Compatibility

No schema migration, file deletion, external API change, license/device state transition change, Cron behavior change, updater protocol change, or Phase 2 UI/polling implementation is introduced.

The 30-second full-page Dashboard reload remains intentionally unchanged for Phase 2.
