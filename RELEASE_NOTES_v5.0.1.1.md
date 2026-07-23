# Licora v5.0.1.1 — High Security Stabilization

**Release date:** 2026-07-23  
**Release type:** Backward-compatible security stabilization  
**Baseline:** `v5.0.1-baseline`  
**Baseline source commit:** `c3db5759ac2539ab1525c73530ef5984b4b73ed6`

## Summary

Licora v5.0.1.1 applies surgical security fixes to the existing PHP and MySQL/MariaDB license-management system. It does not redesign the application, rename routes, change the database schema, alter the license format, remove features, or replace the current architecture.

## Security fixes

### Bearer authentication

`Authorization: Bearer TOKEN` is normalized correctly before API-key hashing. Existing `X-API-Key`, raw authorization-token, and JSON `api_key` compatibility remains available.

### Viewer permissions

Viewer accounts remain read-only. Server-side permission checks now block:

- License CSV export from the bulk-license action.
- API-key test actions.
- Full API-key decryption and display.

Manager and Super Admin behavior remains unchanged.

### Installer hardening

The existing installer flow is preserved while adding:

- Existing-installation detection through `includes/config.local.php`, the legacy root configuration marker, or database environment configuration.
- HTTP 403 installer lock response after installation.
- Installer CSRF protection before application configuration exists.
- A documented manual recovery procedure.

### Temporary administrator warning

The admin panel detects the seeded `admin` / `ChangeMe!2026` credential pair and displays a critical warning. Licora never disables, deletes, or changes the account automatically.

### Versioned authenticated encryption

New encrypted license and API-key records use the `v2:` format:

- AES-256-CBC encryption.
- Independent encryption and authentication keys.
- HMAC-SHA-256 integrity authentication.
- Random initialization vectors.

Existing unversioned encrypted values continue to decrypt through the legacy compatibility path. No existing encrypted record requires migration.

### Session security

The existing 30-minute inactivity timeout is enforced by every authenticated admin-page check. Existing login flow, session key names, and logout route remain unchanged.

### Rate limiting

Rate-limit updates now use a MySQL/MariaDB advisory lock per IP and endpoint. This reduces concurrent counter races without changing limits, endpoint behavior, HTTP responses, or JSON contracts. The previous implementation remains as a compatibility fallback if advisory locks are unavailable.

### Security logging

The release adds security audit entries for denied management/delete actions and session invalidation. Development API logs no longer print API-key values or hash prefixes.

### Security headers

The application and installer send:

- `X-Content-Type-Options: nosniff`
- `Referrer-Policy: same-origin`

No Content Security Policy or cross-origin policy was added because the existing administration interface uses external CDN resources.

## Compatibility guarantees

The following remain unchanged:

- Database schema, tables, columns, indexes, foreign keys, and triggers.
- SQL migration files and upgrade order.
- License-key format and generation.
- License validation behavior.
- Public API URLs.
- Existing API JSON field names and response structures.
- Legacy `/api/check_license.php` response format.
- Admin routes and file locations.
- Cron entry points.
- UI layout and frontend assets, except the required temporary-credential warning.
- Existing encrypted data.
- Existing API keys, license keys, devices, logs, and audit history.

## Regression validation

The validation suite now runs:

1. PHP syntax checks for every PHP file.
2. Security smoke tests.
3. Compatibility regression checks.
4. JavaScript syntax validation.
5. Public-release marker scan.
6. SQL seed-scope validation.

The compatibility test verifies immutable hashes for the database schema, migrations, cron scripts, CSS, and JavaScript; confirms route and session compatibility; validates Bearer normalization and encryption compatibility; and confirms viewer export/test restrictions.

## Remaining known limitations

- `/api/check_license.php` remains unauthenticated by default because mandatory authentication would break existing integrations. The endpoint retains its exact JSON behavior and receives the concurrency-safe rate limiter and global security headers.
- API keys remain reversibly stored for Manager and Super Admin viewing compatibility. New copies use authenticated versioned encryption, and Viewer access is blocked.
- Legacy encrypted values retain their original decryption path. They are not automatically rewritten.
- Full database-backed HTTP regression testing still requires a configured MySQL/MariaDB test deployment.
- Security headers intentionally avoid CSP because the current UI depends on external Bootstrap, Bootstrap Icons, Tailwind, and Chart.js resources.

## Upgrade

No database migration is required.

1. Back up the current repository, private configuration, and database.
2. Apply the v5.0.1.1 source update.
3. Run `bash scripts/validate.sh`.
4. Confirm that existing encrypted values can still be viewed by an authorized Manager or Super Admin.
5. Confirm that the installer returns the locked message on an installed system.
6. Test primary and legacy API clients in a non-production environment.
