# Licora Phase 1 Security Fix Summary

## Release identity

- **Target version:** `v5.0.1.1`
- **Source baseline:** `v5.0.1-baseline`
- **Baseline source commit:** `c3db5759ac2539ab1525c73530ef5984b4b73ed6`
- **Baseline documentation commit:** `ebd641858ccfd45b34f80d45039681df18c2fe6f`
- **Mode:** Zero Freedom Development

## Security fix summary

| Area | Change | Compatibility |
|---|---|---|
| Version | Default `APP_VERSION` becomes `5.0.1.1` | Environment override remains supported |
| Bearer auth | Correct Bearer prefix extraction before whitespace cleanup | `X-API-Key`, raw token, JSON fallback remain |
| Viewer permissions | Blocks license export, API-key testing, and full API-key display | Manager and Super Admin unchanged |
| Installer | Adds installed-state lock and pre-install CSRF | Existing form and database import flow remain |
| Temporary admin | Adds critical warning when seeded credentials remain | No automatic account action |
| Encryption | Adds authenticated `v2:` encryption with legacy decryption | Existing encrypted values remain valid |
| Sessions | Enforces existing 30-minute timeout everywhere | Session keys and login/logout routes unchanged |
| Rate limiting | Serializes counters with advisory locks | Same limits, status codes, and JSON |
| Logging | Audits permission denials/session invalidation and redacts debug secrets | Existing tables and historical rows remain |
| Headers | Adds `nosniff` and same-origin referrer policy | API, downloads, admin, installer remain functional |
| Legacy API | Preserves exact behavior; benefits from shared rate-limit/header hardening | No client contract change |

## Files modified

- `.gitignore`
- `CHANGELOG.md`
- `SECURITY.md`
- `docs/INSTALLATION.md`
- `includes/config.php`
- `includes/security.php`
- `includes/auth.php`
- `includes/admin_helpers.php`
- `admin/includes/navbar.php`
- `admin/admins.php`
- `admin/license.php`
- `admin/api_keys.php`
- `api/verify.php`
- `install.php`
- `tests/security_smoke.php`
- `scripts/validate.sh`

## Files added

- `tests/compatibility_regression.php`
- `RELEASE_NOTES_v5.0.1.1.md`
- `PHASE1_SECURITY_FIX_SUMMARY.md`

## Files intentionally unchanged

- `database.sql`
- Every migration SQL file
- `includes/functions.php`
- `api/check_license.php`
- All CSS and JavaScript
- Every cron entry point
- License generation and validation logic
- Public and admin route filenames

## Reason for every change

Each runtime change maps directly to a verified security finding or a mandatory Phase 1 task. No performance optimization, architecture rewrite, feature redesign, route rename, schema migration, or UI redesign is included.

## Backward compatibility verification

Automated static compatibility checks confirm:

- Database and migration hashes remain identical to the Phase 0 baseline.
- License format and generation source markers remain unchanged.
- Primary and legacy API response markers remain present.
- Public routes, admin routes, and cron files remain present.
- Existing session key names remain present.
- Legacy encrypted values are accepted.
- Manager and Super Admin authorization semantics remain unchanged.
- Viewer license-export and API-key test paths are denied server-side.

## Regression test coverage

Automated repository validation covers:

- PHP syntax.
- Versioned-encryption round trip.
- Legacy-encryption decryption.
- Ciphertext-tampering rejection.
- Bearer-token normalization.
- Password hashing.
- Token generation.
- HTML escaping.
- Database/migration/cron/frontend hash preservation.
- API-contract markers.
- Route preservation.
- Session-structure preservation.
- Viewer permission checks.

## Manual runtime matrix

The following must be executed on a disposable MySQL/MariaDB deployment before production release:

- Admin login.
- Inactivity timeout and logout.
- License creation.
- Existing encrypted license/API-key display.
- License verification and device registration.
- `X-API-Key` primary API request.
- Bearer primary API request.
- Legacy API request.
- Viewer license export denial.
- Viewer API-key secret/test denial.
- Manager and Super Admin management actions.
- Dashboard.
- Cron cleanup and expiry checks.
- Settings save.
- Installer locked-state response.
- Backup/export downloads.

## Remaining known limitations

- Mandatory authentication was not added to `/api/check_license.php` because that would break existing clients.
- Reversible API-key storage remains for authorized Manager/Super Admin viewing; Viewer access is blocked and new values use authenticated encryption.
- Live database and HTTP tests cannot be completed without deployment credentials and a disposable database.
- CSP is not added because the existing UI relies on external CDN assets.
