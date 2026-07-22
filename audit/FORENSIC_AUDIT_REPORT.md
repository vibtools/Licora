# Forensic Project Audit Report

**Project:** VibTools License Management System  
**Audit date:** 2026-07-22  
**Source archive:** `license-system-v5-ui-admin-update.zip`  
**Original SHA-256:** `d29442cfd68a337d2d6b5afae5d27364014de4fd2a923f7492ca7c69acd56c27`  
**Prepared repository:** `vibtools/license-management-system`  
**Method:** complete file inventory, archive safety inspection, static symbol extraction, PHP/JavaScript syntax validation, SQL/data review, security-focused manual review, documentation reconciliation, release packaging, and feature-preservation hash comparison.

## Executive assessment

The uploaded project is a self-contained PHP/MySQL license server with a browser-based administration panel, two verification endpoints, device controls, audit/logging features, exports/backups, health checks, and CLI maintenance jobs. The codebase has no Composer runtime dependency.

The application was **not safe for public GitHub publication in its uploaded form** because `database.sql` was a deployment dump containing operational and identity-linked data. Public-release preparation removed those live rows, replaced deployment-specific defaults, blocked direct Apache web access to cron scripts, removed generated lint clutter, and added professional repository metadata, documentation, validation scripts, issue templates, and CI.

No application feature implementation was removed, disabled, or simplified. Of the 27 executable original PHP files, **26 are byte-for-byte unchanged**. The only existing PHP file modified is `includes/config.php`, where the hardcoded deployment URL fallback was replaced with `http://localhost`. The sanitized `database.sql` intentionally changes data content, not the application schema or feature contract.

## Audit coverage

| Area | Coverage |
|---|---:|
| Original files inventoried | 51 |
| Original bytes analyzed | 747,126 |
| Executable `.php` files linted | 27 |
| PHP classes detected | 6 |
| PHP functions/methods detected | 82 |
| JavaScript files syntax-checked | 1 |
| CSS files inventoried | 1 |
| SQL files reviewed | 6 |
| External URL occurrences cataloged | 55 |
| Duplicate-content groups detected | 1 |

Every original path, line count, byte size, and SHA-256 is recorded in [`ORIGINAL_FILE_INVENTORY.csv`](ORIGINAL_FILE_INVENTORY.csv). Every detected PHP class and function/method is recorded in [`PHP_SYMBOL_INVENTORY.md`](PHP_SYMBOL_INVENTORY.md). A sanitized machine-readable structural inventory is preserved in [`STATIC_INVENTORY.json`](STATIC_INVENTORY.json).

## Architecture and feature map

- **Entry and installer:** `index.php`, `install.php`, `database.sql`, additive migration SQL files.
- **Admin application:** dashboard, licenses, devices, logs, API keys, settings, admins, audit trail, backup/export, and health pages.
- **Verification interfaces:** `api/verify.php` (API-key-aware full endpoint) and `api/check_license.php` (legacy/simple endpoint).
- **Core services:** configuration, PDO database singleton, authentication, security utilities, validation, administration helpers, and `LicenseSystem` domain logic.
- **Maintenance:** `cron/cleanup.php` and `cron/check_expiring.php` for command-line scheduling.
- **UI:** Bootstrap-based server-rendered pages plus one local CSS and one local JavaScript file; several external CDN resources.

See [`../docs/FEATURE_MATRIX.md`](../docs/FEATURE_MATRIX.md), [`../docs/ARCHITECTURE.md`](../docs/ARCHITECTURE.md), and [`../docs/API.md`](../docs/API.md).

## Findings

### F-01 — Critical — Production data embedded in public release source — remediated for packaging

The original SQL dump seeded **2,746 rows** across operational tables:

| Table | Original rows |
|---|---:|
| `admin_users` | 1 |
| `api_keys` | 18 |
| `api_logs` | 1,537 |
| `devices` | 278 |
| `failed_logins` | 2 |
| `licenses` | 50 |
| `logs` | 843 |
| `rate_limits` | 1 |
| `settings` | 16 |


The dump contained license records, API-key material, device fingerprints/metadata, IP addresses, user agents, audit logs, failed-login records, administrator identity fields, and deployment-specific API URLs. Values are not reproduced in this report. The release database now contains the full schema plus local-only settings and a temporary administrator seed. No operational licenses, API keys, devices, request logs, rate-limit rows, or failed-login records remain.

**Required after installation:** sign in with the documented temporary local credentials, immediately set a unique administrator password, create a unique `LICENSE_ENCRYPTION_KEY`, and delete/disable the installer.

### F-02 — High — API Bearer-token parsing is unreachable in common paths — documented, not behavior-changed

`api/verify.php:83-100` includes `HTTP_AUTHORIZATION` in the generic header list and assigns the entire header value to `$apiKey`. `api/verify.php:102-136` only strips the `Bearer ` prefix when `$apiKey` is still empty. Therefore a normal `Authorization: Bearer …` header can reach validation with the prefix attached. The README and API guide recommend `X-API-Key`. This bug was not patched during the GitHub-only preparation phase to avoid changing API behavior before dedicated regression testing.

### F-03 — High — Legacy verification endpoint has no API-key authentication — documented compatibility risk

`api/check_license.php` accepts a license key and device hash, enforces only the IP-based rate limit, and calls the core verification flow without API-key validation. This appears to be a preserved legacy contract. Operators should restrict or disable it at the reverse proxy when unused; application removal was not performed.

### F-04 — High — Encryption lacks authenticated integrity and has a deterministic fallback key — unresolved

`includes/security.php:43-67` uses AES-256-CBC without an authentication tag or MAC. When `ENCRYPTION_KEY` is empty, key material is derived from path/application/database values. This provides weaker confidentiality and no ciphertext integrity. Existing encrypted API-key compatibility prevented silent algorithm replacement. Deployments must provide a high-entropy `LICENSE_ENCRYPTION_KEY`; a future version should introduce versioned authenticated encryption and migration tooling.

### F-05 — High — State-changing admin operations use GET URLs with CSRF tokens — unresolved

API-key toggle/delete, device logout/blacklist/delete, and license activate/suspend/blacklist/delete actions are represented as query-string links. CSRF tokens in URLs can enter browser history, proxy logs, analytics, screenshots, and `Referer` headers. Convert mutations to POST forms in a future feature-tested security release.

### F-06 — Medium — Development diagnostics can disclose authentication material — unresolved

`api/verify.php:92-123` logs partial extracted API-key values and, through `getallheaders()`, can serialize complete incoming headers in development mode. Keep production deployments in `APP_ENV=production`, control log access, and avoid using live credentials in development logs.

### F-07 — Medium — Session inactivity enforcement exists but is not consistently called — unresolved

`Auth::checkSessionValidity()` implements a 30-minute inactivity check, but admin pages generally call `isAdminLoggedIn()` instead. The configured session timeout is therefore not a universal request gate. Central middleware or a common bootstrap call is recommended.

### F-08 — Medium — Installer is security-sensitive and must be removed after setup — documented

`install.php` performs database initialization and writes `includes/config.local.php`. It does not use an authenticated setup lock or CSRF token. Apache rules protect the generated local config, but Nginx/IIS require equivalent rules. The installation guide now makes installer removal and credential rotation mandatory.

### F-09 — Medium — Cron scripts were directly web-addressable under permissive servers — partially remediated

The CLI maintenance scripts mutate/read database state and had no internal `PHP_SAPI` guard. A new `cron/.htaccess` denies Apache web access. Non-Apache deployments must add an explicit server deny rule. A future code update can add fail-closed CLI checks after compatibility testing.

### F-10 — Medium — Stored settings are partially presentation-only — unresolved

The settings UI persists timezone, maintenance mode, two-factor toggle, and API-rate-limit values, but core request handling primarily uses hardcoded or environment constants. Per-API-key `rate_limit_per_hour` is stored but the main limiter uses global `API_RATE_LIMIT`. `CSRF_SECRET` and `JWT_SECRET` are defined but not consumed by the inspected code. Documentation now distinguishes stored settings from enforced runtime behavior.

### F-11 — Medium — Browser and operating-system classification order is inaccurate — unresolved

`includes/functions.php:447-467` checks Linux before Android and Chrome/Safari before Edge/Opera. Many Android devices can be labeled Linux, and Chromium-derived Edge/Opera can be labeled Chrome. This affects reporting metadata, not license authorization.

### F-12 — Medium — Third-party CDN assets are not pinned with integrity metadata — unresolved

Admin pages load Bootstrap, Bootstrap Icons, Chart.js, and Tailwind resources from public CDNs. At least one Tailwind resource is unversioned, and inspected tags do not use Subresource Integrity or a restrictive Content Security Policy. Self-hosting/pinning is recommended for hardened deployments.

### F-13 — Low — Migration/schema overlap and redundant indexes — unresolved

The database dump contains historical additive migration statements after base schema creation, and migration files overlap. The license key has overlapping unique-index declarations. These do not remove data but make fresh installation and upgrade provenance harder to reason about. A future migration baseline should be generated and tested against clean and historical databases.

### F-14 — Low — Dead/contradictory device-limit implementation — documented

`LicenseSystem::logoutOldestDevice()` exists but is not called. Current behavior blocks registration when the active-device limit is full, matching the v4 changelog but contradicting the original README’s “automatic logout” claim. The new README documents the implemented blocking behavior.

### F-15 — Low — Generated clutter and duplicate lint outputs — remediated

The archive contained multiple historical `php-lint*.txt` files and a non-executable `licensesystem` tree note. Two lint reports were byte-identical. These non-runtime artifacts were removed from the release tree; historical changelog files were retained.

### F-16 — Medium — No database-backed automated integration suite — unresolved

The uploaded archive had no unit/integration test harness. Release preparation adds syntax validation, a dependency-free security smoke test, public-marker scanning, SQL seed-scope verification, and a PHP-version CI matrix. These checks do not prove database-backed behavior, web-server routing, CORS, installer flow, backup downloads, or browser UI behavior.

## Dependency and license review

- **PHP runtime:** core PHP/PDO/OpenSSL/JSON; no Composer manifest or vendored package tree.
- **Database:** MySQL/MariaDB SQL and PDO MySQL.
- **Frontend:** local CSS/JavaScript plus external CDN assets.
- **Open-source license selected:** MIT, because the project is a compact reusable server application with no bundled dependency requiring reciprocal licensing. The license is permissive and compatible with commercial and open-source reuse. VibTools names, logos, and brand assets remain subject to the separate notice.
- **Version-vulnerability determination:** no package lock or SBOM exists for the external CDN assets, so automated dependency vulnerability resolution is incomplete. Pin and self-host assets before high-assurance deployment.

## Release modifications

### Existing files modified

- `README.md`
- `database.sql`
- `includes/config.php`


### Original non-runtime artifacts removed

- `licensesystem`
- `php-lint-navfix.txt`
- `php-lint-ui-fix.txt`
- `php-lint-v3.txt`
- `php-lint-v5-fix.txt`
- `php-lint-v5-hotfix-2.txt`
- `php-lint-v5.txt`
- `php-lint.txt`


### New repository/release files

- `.editorconfig`
- `.gitattributes`
- `.github/ISSUE_TEMPLATE/bug_report.yml`
- `.github/ISSUE_TEMPLATE/config.yml`
- `.github/ISSUE_TEMPLATE/feature_request.yml`
- `.github/dependabot.yml`
- `.github/pull_request_template.md`
- `.github/workflows/ci.yml`
- `.gitignore`
- `CHANGELOG.md`
- `CODE_OF_CONDUCT.md`
- `CONTRIBUTING.md`
- `LICENSE`
- `NOTICE`
- `RELEASE_NOTES.md`
- `REPOSITORY_METADATA.md`
- `ROADMAP.md`
- `SECURITY.md`
- `SUPPORT.md`
- `assets/banner.svg`
- `assets/screenshots/README.md`
- `audit/CHANGE_REPORT.md`
- `audit/DEPENDENCY_REPORT.md`
- `audit/FINAL_FILE_INVENTORY.csv`
- `audit/FORENSIC_AUDIT_REPORT.md`
- `audit/ORIGINAL_ARCHIVE_SHA256.txt`
- `audit/ORIGINAL_FILE_INVENTORY.csv`
- `audit/PHP_SYMBOL_INVENTORY.md`
- `audit/PRIVACY_VALIDATION_REPORT.md`
- `audit/RELEASE_CHECKLIST.md`
- `audit/STATIC_INVENTORY.json`
- `audit/VALIDATION_REPORT.md`
- `cron/.htaccess`
- `docs/API.md`
- `docs/ARCHITECTURE.md`
- `docs/BUILD.md`
- `docs/CODING_STANDARDS.md`
- `docs/CONFIGURATION.md`
- `docs/DATABASE.md`
- `docs/DEVELOPMENT.md`
- `docs/FEATURE_MATRIX.md`
- `docs/FOLDER_STRUCTURE.md`
- `docs/INSTALLATION.md`
- `docs/MAINTENANCE.md`
- `docs/MIGRATIONS.md`
- `docs/RELEASE.md`
- `docs/SECURITY_DEPLOYMENT.md`
- `docs/TROUBLESHOOTING.md`
- `scripts/package-release.sh`
- `scripts/validate.sh`
- `tests/security_smoke.php`


## Feature-preservation evidence

- Original executable PHP files: **27**.
- Byte-for-byte unchanged original PHP files: **26**.
- Existing PHP files modified: **`includes/config.php`**.
- No original application PHP file was deleted.
- `database.sql` retains the schema but removes private operational rows and uses a local temporary seed.
- Historical migration and changelog files remain available.
- Both API endpoint files are byte-for-byte unchanged.
- All current PHP files pass syntax validation.
- The local JavaScript file passes `node --check`.
- Security smoke tests pass.

## Validation boundary

The package has been validated statically and with local language-level smoke tests. A live MySQL/MariaDB service and browser/web-server integration environment were not available during this audit. Consequently, claims of complete end-to-end runtime equivalence would be unsupported. Before a production release, execute the manual matrix in [`RELEASE_CHECKLIST.md`](RELEASE_CHECKLIST.md) against a disposable database and supported web server.

## Disposition

**GitHub publication:** ready after repository creation and metadata application.  
**Direct production exposure:** conditional; complete the security deployment checklist and integration test matrix first.  
**Recommended next development phase:** address F-02 through F-12 on a dedicated branch with database/API regression fixtures and explicit backward-compatibility tests.
