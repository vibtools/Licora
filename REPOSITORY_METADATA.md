# Recommended GitHub Metadata

## Repository

- **Name:** `Licora`
- **Title:** Licora — Open-Source Central License Management System
- **Description:** Licora is an open-source, self-hosted PHP and MySQL/MariaDB central license management system with authenticated license validation, Secure API v2 device-bound clients, API-key/application binding, device controls, administration, audit logs, and backups.
- **Homepage:** `https://vib.tools/`
- **Visibility:** Public
- **Default branch:** `main`
- **License:** MIT

## Topics

`php`, `mysql`, `mariadb`, `license-management`, `license-server`, `license-key`, `api-key`, `device-management`, `admin-dashboard`, `self-hosted`, `php8`, `csrf-protection`, `rate-limiting`, `audit-log`, `vibtools`

## v5.1.0 release

- **Tag:** `v5.1.0`
- **Target:** reviewed `main` commit containing the final installer-lock and release-readiness fixes
- **Title:** `Licora v5.1.0 — Smart Installer & First-Run Wizard`
- **Latest release:** Yes, after all release-gate checks pass
- **Release notes:** `RELEASE_NOTES_v5.1.0.md`
- **Assets:** `Licora-v5.1.0.zip` and `Licora-v5.1.0.zip.sha256`

## Release summary

Licora v5.1.0 adds a ten-step first-run installer, delimiter-aware schema execution, secure administrator and secret generation, atomic private configuration, installation locking, optional demo data, database-port support, safe installer diagnostics, and expanded regression validation. The release preserves the existing license engine, API contracts, database schema, routes, admin UI, cron entry points, and encrypted-data compatibility.

## v5.2.2 release

- **Tag:** `v5.2.2`
- **Title:** `Licora v5.2.2 — API v2 Admin UI Schema Detection Consistency Fix`
- **Release notes:** `RELEASE_NOTES_v5.2.2.md`
- **Assets:** automatically generated `Licora-5.2.2.zip` and `Licora-5.2.2.zip.sha256` after tag publication
- **Publication:** tag-triggered `.github/workflows/release.yml`

Licora v5.2.2 is a backward-compatible correctness patch for Admin UI schema discovery. It aligns the shared admin table-existence helper with API v2 runtime/provisioning, restoring existing active Client App scope options and V2 device rows when the v2 schema is already present. API v1, API v2 protocol/cryptography, license behavior, and the five-table v5.2.0 schema remain unchanged.

## v5.2.1 release

- **Tag:** `v5.2.1`
- **Title:** `Licora v5.2.1 — API v2 Verification & cPanel Upgrade Hardening`
- **Release notes:** `RELEASE_NOTES_v5.2.1.md`
- **Assets:** automatically generated `Licora-5.2.1.zip` and `Licora-5.2.1.zip.sha256`
- **Publication:** tag-triggered `.github/workflows/release.yml`

Licora v5.2.1 is the backward-compatible maintenance/security hardening release for Secure API v2. It fixes CI verification for supported action-major updates, adds cPanel-friendly authenticated API v2 provisioning, validates deployment signing-key pairs, and preserves refresh rate-limit counters across failed proof transactions. API v1 and the v5.2.0 five-table API v2 schema remain unchanged.
