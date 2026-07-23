# Licora v5.0.1-baseline — Baseline Release Notes

**Baseline date:** `2026-07-23`  
**Frozen source commit:** `c3db5759ac2539ab1525c73530ef5984b4b73ed6`  
**Repository:** `vibtools/Licora`  
**Release label:** `v5.0.1-baseline`

## Scope

This baseline records the current Licora repository state without changing application behavior.

## Current state

- Self-hosted PHP and MySQL/MariaDB license-management system.
- PHP requirement: `8.0+`.
- Repository release version: `5.0.1`.
- Runtime API metadata version: `2.0`.
- Public verification endpoints:
  - `POST /api/verify.php`
  - `POST /api/check_license.php`
- Administrator pages for dashboard, licenses, devices, logs, API keys, settings, administrators, audit trail, backup and health.
- Eleven logical database tables and two database triggers.
- License expiry, device limits, API-key/application binding, blacklist checks, audit logging, CSV export, SQL backup and cron maintenance.
- Bcrypt administrator passwords, API-key SHA-256 lookup, AES-256-CBC reversible storage, CSRF tokens, role checks and session ID regeneration.
- Existing verified limitations and security findings are recorded in `BASELINE.md`.

## Compatibility

This baseline does not modify:

- PHP application logic.
- License generation.
- License validation.
- Authentication or authorization.
- API URLs, request fields, response fields or status handling.
- Database schema, migrations, triggers, indexes or seed data.
- Admin routes.
- HTML, CSS or JavaScript.
- Cron behavior.
- Configuration loading or configuration keys.

## Baseline artifacts

```text
BASELINE.md
RELEASE_NOTES_BASELINE.md
BASELINE_CHECKSUMS.sha256
BASELINE_MANIFEST.md
```

## Tag and release metadata

```text
Tag: v5.0.1-baseline
Title: Licora v5.0.1-baseline — Immutable Baseline Snapshot
Target: c3db5759ac2539ab1525c73530ef5984b4b73ed6
```

The connected GitHub integration did not expose tag/release creation. This file records the exact immutable release metadata.
