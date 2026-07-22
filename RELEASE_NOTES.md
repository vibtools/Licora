# Release Notes — v5.0.0-github-ready

**Release date:** 2026-07-22  
**Repository target:** `vibtools/license-management-system`

## Overview

This is the first public-source preparation release of the VibTools License Management System. It packages the existing v5 application behavior with a sanitized database, professional repository documentation, security guidance, audit evidence, validation automation, and GitHub community files.

## Included application capabilities

- License generation, status, expiry, application/API-key binding, bulk operations, and device limits.
- API-key administration, app/scope metadata, request counters, activation, and expiry.
- Full and legacy license-verification endpoints.
- Device registration, activity, logout/revocation, blacklist, and risk indicators.
- Super-admin, manager, and viewer roles.
- Dashboard, logs, audit trail, CSV exports, SQL backup, settings, and health checks.
- CLI cleanup and expiring-license reporting.

## Public-release security changes

- Removed 2,746 deployment rows from `database.sql`, including operational licenses, API-key material, device data, logs, IP addresses, and failed-login records.
- Replaced deployment-specific URL defaults with localhost-safe values.
- Added Apache denial for direct access to the CLI-oriented `cron/` directory.
- Added private configuration exclusions, deployment hardening guidance, and a disclosure policy.

## Repository engineering

- MIT license, notice, code of conduct, contributing guide, support policy, roadmap, and security policy.
- Complete README and documentation for architecture, installation, configuration, API, database, development, build, release, maintenance, migrations, coding standards, and troubleshooting.
- GitHub Actions validation across PHP 8.0 through 8.4.
- Pull-request template, structured bug/feature forms, and Dependabot for Actions.
- Validation/package scripts and a dependency-free security smoke test.
- Forensic audit, original/final inventories, static symbol inventory, unified diff, change report, and release checklist.

## Compatibility statement

No original application PHP file was deleted. Twenty-six of the twenty-seven executable original PHP files remain byte-for-byte unchanged. `includes/config.php` only changes the deployment-specific fallback URL to `http://localhost`. API endpoint code and application feature logic remain unchanged. `database.sql` changes are intentional data sanitization while retaining the schema.

## Required first-run actions

1. Install only in a private staging environment.
2. Change the temporary `admin` / `ChangeMe!2026` credential immediately.
3. Configure a unique high-entropy `LICENSE_ENCRYPTION_KEY`.
4. Delete or deny `install.php` after setup.
5. Configure HTTPS, database least privilege, cron restrictions, log retention, and tested backups.
6. Complete `audit/RELEASE_CHECKLIST.md` before production exposure.

## Validation boundary

Syntax, static security smoke tests, public-marker scanning, SQL seed restrictions, GitHub YAML parsing, and documentation links were validated. Database-backed and browser/web-server end-to-end tests were not executed in the packaging environment and remain a required release gate.

## Known security and correctness items

Review `audit/FORENSIC_AUDIT_REPORT.md` before deployment. The preserved code includes known items such as Bearer-header parsing inconsistency, a legacy endpoint without API-key authentication, unauthenticated CBC encryption, GET-based state changes, incomplete session-timeout enforcement, partially unenforced settings, and CDN supply-chain exposure. These should be addressed in separate regression-tested releases rather than silently changed in this repository-preparation release.
