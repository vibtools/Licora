# Licora v5.1.0 — Smart Installer & First-Run Wizard

**Release type:** Backward-compatible installer feature release
**Stable base:** `v5.0.1.1`
**Database migration:** None

## Summary

Licora v5.1.0 introduces a professional first-run installation experience for the open-source, self-hosted PHP and MySQL/MariaDB license management system.

The release improves fresh installation only. Existing deployments continue normal operation and are not required to reinstall.

## Smart first-run installer

The installer now provides ten guided steps:

1. Welcome and server compatibility checks
2. Database configuration and connection validation
3. Administrator setup
4. Application configuration and secure secret generation
5. Existing schema initialization review
6. Optional DEMO data
7. Installation-lock confirmation
8. Atomic finalization
9. Installation success summary
10. Redirect to admin login without auto-login

Both installer routes are available:

- `/install.php` remains fully supported
- `/install` is an additive alias

## Installation detection

Before normal web application boot, Licora distinguishes between:

- Fresh unconfigured deployments
- Incomplete fresh installations
- Valid existing installations
- Valid legacy installations without an installation flag
- Configured installations experiencing a temporary database outage

A database outage never reopens the installer for an existing configured deployment.

## Atomic installation

The installer validates all input before finalization and uses temporary private files before activation.

It executes the existing repository `database.sql`, including current migrations, indexes, constraints, and triggers. A delimiter-aware parser removes the need for manual SQL import.

If installation fails before activation, Licora attempts to remove only installer-created objects. Unrelated pre-existing database objects are not removed.

## Administrator security

Fresh wizard installations require:

- Administrator name
- Valid email address
- Unique-format username
- Password of at least 12 characters
- Uppercase, lowercase, number, and symbol

The temporary development account from the sanitized manual-import schema is replaced before wizard completion. Licora never auto-logs in the new administrator.

## Application configuration

The wizard generates and stores private values for:

- Application key
- Encryption key
- CSRF secret
- JWT secret

It also configures:

- Application name
- Base URL
- Timezone
- Locale
- Mail From Name
- Database port

Generated secrets and credentials are never displayed or logged.

## Installation lock

After successful installation Licora creates:

- `includes/config.local.php`
- `includes/.licora-installed`

The installation flag contains only product, version, and installation timestamp. Installer files remain on disk but execution is disabled.

## Optional DEMO data

When selected, the installer creates clearly marked DEMO data using existing tables only:

- DEMO API credential
- DEMO PRODUCT representation
- DEMO license
- DEMO CUSTOMER marker
- Demo cleanup settings

No product, customer, role, or permission table is added.

Demo records can be removed with:

```bash
php scripts/remove-demo-data.php
```

## Compatibility guarantees

This release does not change:

- Database tables or columns
- Indexes, foreign keys, triggers, or migrations
- License-key format
- License generation
- License validation
- Device registration
- API URLs
- API request or response JSON
- Legacy API behavior
- Admin routes or page design
- Cron entry points
- CSS or JavaScript
- Existing encrypted data

Table prefixes remain unsupported because fixed table names are part of the frozen schema and runtime-query contract. The installer field must remain blank.

## Upgrade instructions

Existing v5.0.1 and v5.0.1.1 installations:

1. Back up the database.
2. Back up private configuration and encryption-key material.
3. Replace application source with v5.1.0.
4. Preserve `includes/config.local.php` and private key files.
5. Do not run the first-run installer.
6. Run `bash scripts/validate.sh`.
7. Verify admin, API, license, device, dashboard, cron, settings, and encrypted-data compatibility.

No v5.1.0 database migration is required.

## Validation

The repository validation suite covers:

- PHP syntax
- Security smoke tests
- Compatibility regression tests
- Installer smoke tests
- SQL delimiter parsing
- Strong password validation
- Table-prefix rejection
- Installation-flag redaction
- Versioned demo encryption
- Existing license format
- Immutable database and migration hashes
- Preserved API and route contracts
- JavaScript syntax
- Public-release marker scanning
- SQL seed-scope validation

## Documentation

- `docs/INSTALLATION.md`
- `docs/INSTALLER_ARCHITECTURE.md`
- `docs/FIRST_RUN_GUIDE.md`
- `docs/UPGRADE_GUIDE.md`
- `docs/DEMO_DATA.md`
- `PHASE2_INSTALLER_SUMMARY.md`
