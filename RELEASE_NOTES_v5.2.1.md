# Licora v5.2.1 — API v2 Verification, cPanel Upgrade, and CI Maintenance

**Release date:** 2026-08-08  
**Release type:** Backward-compatible maintenance/security hardening release  
**Stable base:** `v5.2.0`  
**Database migration:** No new migration. Reuses additive `migration-v5.2.0-api-v2.sql` when API v2 has not yet been provisioned.

## Summary

Licora v5.2.1 verifies and hardens the Phase-02-Step-001 Secure API v2 implementation without changing the API v1 implementation or the API v2 public endpoint/field contract.

The release fixes GitHub Actions verification that incorrectly rejected valid Dependabot upgrades solely because workflow action majors changed from v6 to v7, adds a cPanel-friendly authenticated admin provisioning path for existing installations without terminal access, ensures refresh app/device rate-limit writes cannot be rolled back with the refresh-token transaction, and rejects mismatched server signing key pairs before token operations.

## API v1 compatibility

The following API v1 implementation files remain frozen at the v5.1.0/v5.2.0 contract:

- `api/verify.php`
- `api/check_license.php`
- `includes/functions.php`
- `includes/security.php`

No API v1 URL, request field, response field, shared API-key behavior, license format, license generation, or legacy encrypted-data compatibility is changed.

## Secure API v2 maintenance fixes

- Added matching RSA private/public signing-key validation and fail-closed runtime checks.
- Added a shared `V2Provisioner` used by both CLI setup and authenticated admin provisioning.
- Added an **Initialize API v2** action to **Client Apps** for cPanel/shared-hosting upgrades where shell access is unavailable.
- Existing signing key files are never silently replaced; partial or mismatched key material fails closed.
- Moved refresh app/device rate-limit consumption before the refresh transaction so invalid device proofs cannot roll those counters back.
- Kept the existing five-table v5.2.0 additive schema unchanged.

## CI/release verification fix

- Updated `actions/checkout` and `actions/setup-node` to v7.
- Replaced exact `@v6` string assertions in `scripts/verify-local.py` with minimum-supported action-version checks, so supported newer Dependabot action majors can be tested instead of being rejected as missing markers.
- Kept PHP 8.0–8.4 validation, MySQL 8.4 API v2 integration, verified source artifacts, exact-tag packaging, SHA-256 generation, and tag-triggered GitHub Release publication.

## cPanel/shared-hosting upgrade

For an existing installation, preserve private/runtime files and overwrite the application source with v5.2.1. Then either:

1. Sign in to the Licora admin panel, open **Client Apps**, and choose **Initialize API v2**, or
2. If terminal access is available, run `php scripts/setup-v2.php`.

Both paths use the same additive provisioner. Fresh installations continue using the first-run installer, which provisions the API v2 schema and server signing keys during installation.

## Verification

The release verifier checks API v1 freeze hashes, PHP syntax, installer regression, API v2 static/crypto contracts, cPanel/admin provisioning integration, signing-key hygiene, release/version consistency, workflow action minimums, documentation, and release packaging. The GitHub CI MySQL job remains the database-backed publication gate.
