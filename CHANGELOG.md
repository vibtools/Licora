# Changelog

All notable public-release changes are recorded here. Historical project notes remain in the original `CHANGELOG-v*.txt` files.

## [Unreleased]

### Planned

- No additional Dashboard scope is approved beyond the v5.7.1 Phase 2 corrective release candidate.

## [5.7.1] - 2026-08-20

### Fixed
- Preserved the Dashboard `Retry` label after failed-refresh loading cleanup instead of resetting it to `Refresh`.
- Preserved the `401 AUTH_REQUIRED` refresh lock and `Refresh paused` state after request cleanup.
- Routed synchronous Dashboard request-transport exceptions through the normal stale/error lifecycle so the in-flight lock and loading state are always released.
- Advanced the Dashboard last-success timestamp only after a snapshot completes rendering successfully.
- Expanded Dashboard browser/runtime regression coverage for the corrected stale/auth/transport/render lifecycle.

### Compatibility
- No database migration, file deletion, backend Dashboard contract change, external API change, license/device enforcement change, authentication/role change, Cron mutation change, updater protocol change, shared shell change or new browser dependency.
- Signed update compatibility accepts both published `v5.6.1` and an already-applied `v5.7.0` source baseline.

## [5.7.0] - 2026-08-20 (source baseline; superseded by 5.7.1 corrective)

### Changed
- Rebuilt the Dashboard as a compact operations view while preserving the existing Licora light shell, sidebar, topbar and other admin pages.
- Replaced the 30-second full-page Dashboard reload with authenticated 30-second AJAX polling against the existing read-only `admin/ajax/dashboard-data.php` contract.
- Added manual refresh, last-updated feedback, stale-data handling, session-expiry handling and request-overlap protection without changing backend business truth.
- Updated API and expiration charts in place, combined source-labelled API v1/v2 recent activity, and converted Quick Actions to compact links over existing routes.

### Added
- Added `admin/assets/js/dashboard.js` as the dedicated Dashboard refresh/controller layer.
- Added Phase 2 source/DOM contract and browser-runtime tests covering polling, manual refresh, overlap prevention, stale behavior and auth expiry.

### Compatibility
- No database migration, file deletion, external API contract change, license/device enforcement change, authentication/role change, Cron mutation change, updater protocol change, installer schema change, sidebar/topbar redesign or shared application architecture change.
- Signed update compatibility starts from the frozen official `v5.6.1` baseline.

## [5.6.1] - 2026-08-20

### Fixed
- Corrected the Dashboard MySQL integration test cleanup so prior API v2 foreign-key tables are removed safely before the dashboard fixture is created.
- Aligned the implemented Dashboard JSON response with the declared top-level `recent_activity` contract while preserving separate API v1 and API v2 tracked sources.
- Tightened Dashboard API v2 readiness so `Ready` now requires the complete v2 schema and a readable, cryptographically matching private/public signing key pair; only readiness booleans are exposed to the browser.
- Added a runtime source guard confirming Licora does not require or download Google Chrome; Licora remains browser-agnostic server software.

### Compatibility
- No database migration, deleted files, external API contract change, license/device enforcement change, Cron mutation change, updater protocol change, or Phase 2 polling/UI implementation.
- Signed update compatibility accepts both the official v5.5.1 source and an already-applied v5.6.0 baseline.

## [5.6.0] - 2026-08-20 (unreleased source baseline; superseded by 5.6.1)

### Added
- Added a centralized read-only Dashboard data model and authenticated `GET /admin/ajax/dashboard-data.php` JSON endpoint.
- Added explicit license, device, API v1/API v2, expiration and measured health/config reporting semantics.
- Added Dashboard contract/MySQL integration tests and made the Dashboard DB test mandatory in CI/tagged-release MySQL gates.

### Fixed
- Replaced misleading Dashboard health labels with measured facts, separated API v1 and Secure API v2 tracked activity, split past/future expiration data, and changed device reporting from an active flag to explicit recently-seen semantics.

### Compatibility
- No database migration or deleted files. Signed direct source is `v5.5.1`; external API contracts, license/device enforcement, authentication/roles, Cron mutation behavior and updater protocol/state machine remain unchanged. The existing 30-second full-page Dashboard reload remains intentionally in Phase 1 for Phase 2 replacement.

## [5.5.1] - 2026-08-18

### Fixed
- Rebalanced Settings management shortcuts into an equal-width responsive row instead of left-clustered intrinsic-width buttons.
- Rebuilt the Settings lower layout so Cron Jobs no longer stretches beside the taller API panel and API v2 Signing stays immediately visible below it.
- Converted Settings child navigation to an accessible collapsible submenu with active-child auto-expansion.
- Rebuilt About Licora with a product hero, verified capability cards, Vib Tools company information and compact project metadata.

### Compatibility
- No database migration or deleted files. Signed direct source is `v5.5.0`; API v1/v2, license/device/cron behavior, authentication/roles and updater protocol/state machine remain unchanged.

## [5.5.0] - 2026-08-14

### Changed
- Refined the v5.4 VibTools Light shell to the compact/no-growth table, form, toolbar, action, scrollbar, confirmation and feedback composition used by the audited VibTools Web UI v2.1.2 reference.
- Reworked Licenses into a full-width table with one compact toolbar and responsive Single/Bulk create modal while preserving existing form/CSRF/backend contracts.
- Reworked Devices and other data-heavy admin surfaces to compact searchable/filterable tables and shared action menus.
- Made Admin Settings truthful by exposing only runtime-backed editable settings; legacy stored-only keys remain preserved but hidden from the active UI.
- Fixed visible product identity to Licora and applied the supplied tracked Licora logos/icons/favicons across shell/login/root/installer/About.

### Added
- Added nested Settings navigation for Audit Trail, Backup & Export, System Health and About Licora.
- Added read-only detected API/runtime/Cron information and Super-Admin Secure API v2 public-key fingerprint/status/download; private signing-key export remains prohibited.
- Added explicit Update Center up-to-date/update-available feedback and shared light scrollbars.
- Added Windows CI coverage for updater manifest-builder portability and changed the test to use the current Python interpreter rather than hardcoded `python3`.

### Compatibility
- No database migration or deleted files. Signed direct source is `v5.4.1`. API v1/v2, license/device/cron semantics, authentication/roles and updater protocol/state machine remain unchanged.

## [5.4.1] - 2026-08-14

### Fixed
- Corrected the updater live-log JavaScript/HTML DOM selector mismatch that could leave a real update job at `fetch_manifest / 1%` before the browser began driving resumable steps.
- Hardened terminal-job UI state, updater DOM validation, light confirmation components, bounded PHP-stream downloads, archive entry validation, verified source/database backup writes and rollback integrity.
- Aligned signed-manifest builder/runtime validation and added exact signed-release runtime verification before GitHub publication.

### Compatibility
- No database migration. Signed direct-update sources are `v5.3.0` and `v5.4.0`. API v1/v2, licensing, device, cron, authentication and the v5.4.0 component UI architecture remain unchanged.

## [5.4.0] - 2026-08-14

### Changed
- Replaced the classic horizontal admin primary navigation with a reusable fixed left sidebar and compact utility topbar.
- Migrated Licora administration, login, installer and Secure Update Center presentation to a centralized component system based on VibTools Web UI v2.1.2 structure, typography and spacing while retaining Licora's light blue/purple identity.
- Removed the Tailwind CDN runtime dependency from migrated admin pages and removed page-level `<style>` blocks from the migrated admin/login/installer surfaces.
- Converted the dark updater visual island to the shared Licora Light component system without changing updater jobs, events, signing, migration, preflight, rollback or release protocol behavior.

### Added
- Added shared PHP sidebar/topbar navigation components, a responsive mobile drawer controller and a single semantic light-theme/component CSS architecture.
- Added `docs/UI_DESIGN_SYSTEM.md` and automated UI route, DOM/form, component architecture and updater presentation contract tests.

### Compatibility
- No database migration is included; the signed release specification accepts `v5.3.0` directly and declares an empty migration list.
- API v1, Secure API v2, licensing, devices, cron, authentication/authorization and Secure Updater backend contracts are unchanged.
- Existing route names, form field names, CSRF inputs, primary navigation destinations and updater notification hooks are preserved.

## [5.3.0] - 2026-08-14

### Added
- Added a Super-Admin-only Secure Update Center for future official Licora releases.
- Added dedicated RSA/SHA-256 signed update manifests and a tracked updater public verification key; the private update signing key remains a GitHub Actions secret and is never distributed with Licora.
- Added persistent `update_jobs`, `update_events`, and `app_migrations` state through an additive v5.3.0 updater migration that is also included in `database.sql` for fresh installations.
- Added cPanel/VPS-safe release preflight, trusted GitHub-only downloads, package SHA-256 and per-file validation, ZIP traversal/symlink rejection, staging, source backup, database safety backup, migration ledger, chunked file application, post-update verification, and rollback handling.
- Added a filesystem critical-update lock that temporarily blocks non-updater traffic only during source/schema mutation and recovers orphaned terminal locks safely.
- Added VibTools Web UI v2.1.2-based updater UI and real live update-log modal with search, level filtering, copy, diagnostic download, pin-to-bottom, progress, and resumable event streaming.
- Added update-available navbar notification for Super Admins without making Cron a dependency.
- Added updater security, manifest, state-machine, failure/recovery, UI-contract, and MySQL integration tests.
- Added release manifest generation and GitHub Actions signing/publication of `licora-update-manifest.json` and `licora-update-manifest.sig`.


### Hardened before release
- Fixed updater MySQL integration test isolation so the test prepares its own production-compatible core `settings` prerequisite instead of inheriting the intentionally minimal API v2 fixture.
- Added controlled core-settings schema validation before updater provisioning to replace raw missing-table PDO failures with `UPDATER_BASE_SCHEMA_MISSING`.
- Added signed `upgrade_from` compatibility metadata and runtime enforcement to prevent unsupported direct release jumps from skipping migration history.
- Made critical lock writes atomic and recoverable when lock metadata is corrupt, preventing a malformed lock file from indefinitely holding normal traffic in HTTP 503.
- Acquired the critical application lock before every chunked migration database backup so the safety dump is internally consistent with respect to normal Licora writes.
- Blocked protocol-v1 deletion of updater control files, aligned release-manifest duplicate-path validation with runtime archive validation, and corrected disabled auto-check behavior.

### Compatibility
- Existing license, device, admin, API v1, and Secure API v2 contracts are unchanged.
- v5.2.2 deployments require one final manual source update to v5.3.0; future compatible signed releases can then use Admin → Updates.

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
