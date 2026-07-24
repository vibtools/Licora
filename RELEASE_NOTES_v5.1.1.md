# Licora v5.1.1 — Quality & Stability Release

**Release type:** Backward-compatible quality and production-readiness release
**Stable base:** `v5.1.0`
**Database migration:** None
**Development mode:** Zero Freedom Development

## Summary

Licora v5.1.1 improves verified error handling, installer configuration validation, permission diagnostics, regression coverage, and deployment documentation. It introduces no business feature, UI redesign, database change, API-contract change, license-engine change, or route rename.

## Verified issues corrected

### Technical error disclosure

Unhandled application exceptions could expose exception messages, source filenames, and line numbers outside production mode. v5.1.1 always returns the existing generic `Internal Server Error` JSON response and records a non-secret diagnostic reference, exception class, file basename, and line number internally.

### Installer path disclosure

The installer requirements screen displayed the absolute server path of the writable `includes` directory. v5.1.1 reports only `Writable` or `Not writable`.

### Base URL validation

The existing installer accepted syntactically valid URLs containing embedded credentials, query strings, or fragments. v5.1.1 rejects those values while continuing to support HTTP/HTTPS, ports, IPv4/IPv6 hosts, and subdirectory deployments.

### Mail From Name validation

The installer now rejects CR/LF control characters in the existing Mail From Name field. The field and normal values remain unchanged.

### Installer exception consistency

Unexpected installer exceptions are mapped to a safe, consistent message. Approved user-facing conditions remain readable, while database messages, credentials, SQL text, and server paths are never returned.

### Generated secret validation

The installer verifies its generated application, encryption, CSRF, and JWT secrets before finalization. This does not change legacy secret acceptance or existing installations.

### Existing-upgrade version precedence

Installer-generated private configuration from v5.1.0 may contain `APP_VERSION` set to `5.1.0`. Previously, preserving that file during an upgrade could pin the displayed runtime version to the old value. v5.1.1 now resolves the release identity before loading private configuration. Database credentials, application settings, and security secrets remain preserved, while the runtime reports the installed source release correctly. The existing environment override remains supported.

## Version update

The default runtime version, generated installer configuration, installation flag, installed-version setting, installer display, and regression assertions are updated to `5.1.1`.

## Compatibility guarantees

The following remain unchanged:

- Database schema and every migration file
- License-key format
- License generation and validation
- Device activation and registration
- Primary and legacy API URLs and JSON contracts
- Admin routes and page design
- Role behavior
- Cron entry points
- CSS and JavaScript
- Existing encrypted data
- Historical logs and audit records

## Validation coverage

Automated repository validation includes PHP syntax, security smoke, compatibility regression, installer smoke, quality/stability checks, immutable file hashes, API markers, license-format markers, lock decision tests, safe error-output assertions, URL/mail validation, and non-secret flag checks.

## Compatibility matrix

See `docs/COMPATIBILITY_MATRIX.md`.

## Upgrade

Existing v5.1.0 installations must preserve private configuration and encryption-key material. Replace application source, do not run the first-run installer, run repository validation, and complete the regression checklist in `docs/UPGRADE_GUIDE.md`.

No v5.1.1 database migration exists or is required.

## Release gate

Do not tag or publish v5.1.1 until pull-request CI, fresh production installation, fresh DEMO installation, existing-install upgrade, interruption/permission scenarios, and full admin/API/license/device/cron/export/settings regression validation pass.
