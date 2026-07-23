# Changelog

All notable public-release changes are recorded here. Historical project notes remain in the original `CHANGELOG-v*.txt` files.

## [Unreleased]

### Planned

- Resolve the security and correctness items listed in the forensic audit through separate reviewed pull requests.

## [5.0.1.1] - 2026-07-23

### Security

- Corrected `Authorization: Bearer TOKEN` parsing while preserving `X-API-Key`, raw authorization-token, and JSON API-key compatibility.
- Enforced Viewer read-only behavior for license export, API-key testing, and full API-key display.
- Added installed-state detection and CSRF protection to the existing installer flow.
- Added a critical admin-panel warning when the seeded temporary administrator credentials remain active.
- Added authenticated, versioned `v2:` encryption while preserving decryption of existing legacy encrypted values.
- Enforced the existing 30-minute inactivity timeout on every authenticated admin-page check.
- Serialized rate-limit counter updates with MySQL/MariaDB advisory locks and retained the previous implementation as a compatibility fallback.
- Added permission-denial/session-invalidation audit events and removed API-key values/hash prefixes from development logs.
- Added backward-compatible `nosniff` and same-origin referrer-policy headers.

### Added

- `tests/compatibility_regression.php`.
- `RELEASE_NOTES_v5.0.1.1.md`.
- `PHASE1_SECURITY_FIX_SUMMARY.md`.

### Compatibility

- No database schema, migration, table, column, index, foreign key, or trigger changed.
- No API URL, JSON field, route, folder, class, method, license format, license-generation algorithm, cron entry point, CSS, or JavaScript changed.
- Existing license keys, API keys, encrypted values, devices, logs, and audit history remain compatible.
- The legacy `/api/check_license.php` endpoint remains unauthenticated by default because mandatory authentication would break existing clients; its response contract is unchanged.

## [5.0.1] - 2026-07-22

### Fixed

- Corrected the product identity from the placeholder license-system name to **Licora**.
- Replaced the inaccurate open-source-hub company description with the verified Vib Tools company description from the official website.
- Updated repository badges and links to `vibtools/Licora`.
- Updated private support and security contact information to `support@vib.tools`.

### Changed

- Updated the repository banner, maintainer references, NOTICE, and recommended GitHub metadata.
- Removed unrelated ecosystem links from the project-maintainer section.

### Compatibility

- No PHP application logic, API behavior, database schema, migration, or runtime configuration was changed.

## [5.0.0-github-ready] - 2026-07-22

### Added

- Professional repository documentation, contribution policy, support policy, security policy, roadmap, issue templates, pull-request template, validation scripts, smoke tests, and CI workflow.
- Architecture, API, configuration, database, deployment-security, development, build, release, maintenance, migration, folder-structure, coding-standard, troubleshooting, and feature-matrix documentation.
- Complete forensic audit package, original file inventory, static inventory, change report, validation report, and unified source diff.
- Apache deny rule for the CLI-only `cron/` directory.

### Security

- Removed 2,746 operational database rows from the public schema, including license keys, API-key material, encrypted key copies, device fingerprints, IP addresses, request logs, and administrative activity.
- Replaced deployment-specific domains with neutral local defaults.
- Added private-configuration ignore rules and deployment hardening guidance.

### Changed

- Replaced the short legacy README with a complete open-source project guide.
- Converted `database.sql` from a deployment data dump to a sanitized schema and minimal local-development seed.

### Removed

- Historical generated PHP lint output files and the non-executable tree-note file named `licensesystem`. These files had no runtime references or functional impact.

### Compatibility

- Application feature code was not removed, disabled, or simplified.
- No PHP class, function, endpoint, admin page, migration, stylesheet, or JavaScript behavior was intentionally changed during repository preparation.
