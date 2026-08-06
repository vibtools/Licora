# Recommended GitHub Metadata

## Repository

- **Name:** `Licora`
- **Title:** Licora — Open-Source Central License Management System
- **Description:** Licora is an open-source, self-hosted PHP and MySQL/MariaDB central license management system with authenticated license validation, API-key/application binding, device controls, administration, audit logs, and backups.
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
