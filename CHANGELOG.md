# Changelog

All notable public-release changes are recorded here. Historical project notes remain in the original `CHANGELOG-v*.txt` files.

## [Unreleased]

### Planned

- Continue reviewed Zero Freedom development after the v5.1.0 installer release.

## [5.2.2] - 2026-08-14

### Fixed

- Aligned the shared admin table-existence helper with the `information_schema.TABLES` schema-discovery contract used by Secure API v2 runtime/provisioning.
- Fixed active API v2 Client Apps being hidden from the existing License app-scope selector when the legacy admin metadata probe returned a false negative.
- Fixed the V2 Devices page incorrectly reporting API v2 provisioning as incomplete and skipping existing device credentials under the same false-negative condition.
- Added a safe exact-table fallback and server-side metadata diagnostic logging without exposing database errors in the UI.

### Verification

- Added MySQL-backed regression coverage for admin V2 table detection, active Client App loading, and activated V2 device-list loading.
- Preserved API v1 freeze checks and the existing API v2 protocol, cryptographic, refresh, device-limit, and revocation tests.

### Compatibility

- No API v1 or API v2 public contract changed.
- No database schema or migration changed.
- No License, Client Apps, V2 Devices layout, naming, workflow, or existing runtime behavior was redesigned.

## [5.2.1] - 2026-08-08

### Fixed

- Fixed GitHub Actions verification falsely rejecting supported Dependabot action-major upgrades because `scripts/verify-local.py` required exact `@v6` marker strings.
- Updated `actions/checkout` and `actions/setup-node` to v7 while keeping minimum-supported workflow-action verification for future compatible updates.
- Fixed API v2 refresh app/device rate-limit writes being opened inside the refresh-token transaction, where invalid-proof rollback could undo those counters.
- Added fail-closed validation that the configured API v2 RSA private/public signing files form the same key pair.

### Added

- Added a shared API v2 provisioner used by CLI setup and authenticated admin provisioning.
- Added a cPanel/shared-hosting friendly **Initialize API v2** action on the existing Client Apps page so additive schema/signing-key setup does not require shell access.
- Added provisioning/key-pair regression coverage and extended the MySQL integration path to exercise the shared provisioner.

### Compatibility

- API v1 implementation files remain byte-for-byte frozen at the established contract.
- API v2 public endpoints, request fields, response envelope, five-table schema, license format, and existing client contract are unchanged.
- No existing database table or column is dropped, renamed, or replaced; v5.2.1 introduces no new database migration.
- Fresh installer behavior remains additive, and existing cPanel deployments can update by preserving private/runtime files and overwriting source files.

## [5.2.0] - 2026-08-08

### Added

- Added Secure API v2 activation, refresh, status and deactivation endpoints without a desktop shared/master API key.
- Added device-bound P-256 request proofs, RSA-3072/RS256 server-signed access tokens, rotating hashed refresh tokens, nonce replay protection and v2 audit logging.
- Added additive API v2 client-app/device/refresh/nonce/audit database tables and migration/setup tooling.
- Added Client Apps and V2 Devices administration pages and additive API v2 app-scope selection during license creation.
- Added local verification, API v1 freeze checks, API v2 crypto/static/database tests, CI package artifacts and tag-triggered automatic GitHub Releases.

### Compatibility

- Preserved API v1 endpoint implementation and existing v1 license/API-key behavior unchanged.
- Preserved existing license format, license engine, encryption compatibility, cron routes and existing admin workflows outside the API v2 additions.

## [5.1.0] - 2026-08-06

### Added

- Added a ten-step first-run installer wizard with server compatibility checks, database validation, administrator setup, application configuration, optional demo data, installation locking, success reporting, and admin-login redirect.
- Added `/install` as an additive installer alias while preserving `/install.php`.
- Added pre-boot installation detection for incomplete fresh installations.
- Added an atomic private configuration and installation-flag workflow.
- Added an installer SQL parser that executes the existing schema, migrations, indexes, constraints, and triggers without manual import.
- Added optional DEMO records using existing `api_keys`, `licenses`, and `settings` tables only.
- Added a CLI demo-data cleanup utility.
- Added installer architecture, first-run, upgrade, demo-data, release, and implementation documentation.
- Added installer smoke tests and expanded compatibility regression coverage.

### Changed

- Updated the default application version to `5.1.0`.
- Added optional database-port, application-key, timezone, locale, and mail-from configuration constants.
- Updated database connection construction to honor `DB_PORT` while retaining port `3306` as the default.
- Updated the root landing page to trigger the installation guard before normal output.

### Fixed

- Enforced the installation lock for same-session requests to installer steps 1-8 after successful installation.
- Restricted the completion screen to one pending view and cleared completion-session state before the admin-login redirect.
- Prevented private installer configuration from pinning the source release version during future upgrades.
- Removed exception messages, source paths, line numbers, and absolute installer paths from public responses.
- Rejected Base URLs containing embedded credentials, query parameters, or fragments.
- Rejected CR/LF characters in Mail From Name and validated installer-generated secrets before activation.
- Refreshed cached file-status data before checking the `includes` directory and made the permission regression test portable across root-based CI containers.
- Added a safe actionable diagnostic for database accounts that lack the `TRIGGER` privilege required by the existing schema.
- Replaced release packaging based on the live directory with validated `git archive` packaging and SHA-256 output.
- Corrected release, configuration, metadata, FAQ, compatibility, troubleshooting, and upgrade documentation for v5.1.0.

### Compatibility

- No database table, column, index, foreign key, trigger, or migration was changed.
- No license generation, license validation, API response, route, admin page, cron entry point, CSS, or JavaScript behavior was changed.
- Existing v5.0.1 and v5.0.1.1 installations continue normal boot without reinstalling.
- Temporary database outages never reopen the installer for configured deployments.
- Table prefixes remain unsupported because the frozen schema and runtime query contract use fixed table names.

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

- Existing runtime routes, API contracts, schema objects, license format, and application behavior were preserved.
