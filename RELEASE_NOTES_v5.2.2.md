# Licora v5.2.2 — API v2 Admin UI Schema Detection Consistency Fix

**Release date:** 2026-08-14  
**Release type:** Backward-compatible production correctness patch  
**Stable base:** `v5.2.1`  
**Database migration:** None. The existing v5.2.0 five-table API v2 schema is unchanged.

## Summary

Licora v5.2.2 fixes an Admin UI schema-detection inconsistency that could hide already-provisioned API v2 data on some production/shared-hosting environments even while API v2 activation, token, and client authentication continued to work correctly.

The API v2 runtime and shared provisioner already use exact `information_schema.TABLES` discovery. The older shared admin helper used a separate prepared `SHOW TABLES LIKE` path and silently returned `false` on metadata-query failure. The License and V2 Devices pages depend on that helper, so a false negative could suppress active V2 Client App options and incorrectly report API v2 provisioning as incomplete.

## Fixed

- Aligned `AdminHelpers::tableExists()` with the exact `information_schema.TABLES` discovery contract already used by API v2 runtime/provisioning.
- Added a safe exact-name `SHOW TABLES` compatibility fallback only when the primary metadata query itself fails.
- Added server-side diagnostic logging for metadata-detection failures without exposing database details in the Admin UI.
- Restored active API v2 Client App visibility to the existing License app-scope selector when the v2 schema is present.
- Restored existing API v2 device-credential visibility to the existing V2 Devices page and eliminated the false provisioning-warning path caused by table-detection false negatives.
- Added a dedicated MySQL-backed regression gate for admin helper detection, active client-app loading, and activated device-list loading.

## Compatibility

- API v1 implementation files and contracts are unchanged.
- API v2 endpoints, request fields, response envelope, P-256 device proofs, RS256 access tokens, refresh-token behavior, rate limits, and client contract are unchanged.
- No database table, column, index, foreign key, trigger, or migration is added, removed, renamed, or modified.
- License format, license app-scope authorization, device limits, Client Apps management, and V2 device revocation behavior are unchanged.
- Creating a Client App still does not create a V2 Device credential; a credential is created only by successful API v2 activation.

## Upgrade

Existing v5.2.1 deployments preserve `includes/config.local.php`, installation/encryption/signing-key files, database content, logs/backups, and other runtime/private data, then overwrite tracked source files with v5.2.2. No installer rerun and no database migration are required.

After upgrade, verify that active entries on **Admin → Client Apps** are available in the existing **Licenses → API v2 Client App** selector and that successfully activated credentials appear on **Admin → V2 Devices** without a false provisioning warning.

## Verification

The release verifier retains API v1 freeze checks, PHP 8.0–8.4 validation, installer/security regression, API v2 static/crypto/database tests, release/version consistency, and private-material hygiene. The MySQL 8.4 CI gate additionally executes the API v2 Admin UI database regression before a verified source artifact can be built.
