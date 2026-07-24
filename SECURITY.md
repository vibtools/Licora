# Security Policy

## Supported release

The latest tagged public release receives security documentation and best-effort fixes. Older snapshots may be referenced for migration context but are not guaranteed to receive patches.

## Reporting a vulnerability

Send a private report to `support@vib.tools` with:

- Affected file, endpoint, or admin action.
- Reproduction steps using non-production data.
- Expected and observed behavior.
- Security impact and prerequisites.
- Suggested mitigation, when available.

Do not include real license keys, API keys, passwords, device hashes, customer information, or live database exports.

## Response expectations

The maintainer should acknowledge receipt, reproduce the issue, assign severity, prepare a coordinated fix, and publish release notes without exposing unnecessary exploitation detail.

## Deployment warning

This repository contains self-hosted security-sensitive software. A safe production deployment requires HTTPS, private configuration, restricted installer and cron access, least-privilege database credentials, tested backups, log retention, and review of [audit/FORENSIC_AUDIT_REPORT.md](audit/FORENSIC_AUDIT_REPORT.md).

The supplied temporary development account must be changed before any internet exposure.

## v5.0.1.1 security stabilization

Licora v5.0.1.1 introduces backward-compatible hardening for API credential parsing, Viewer authorization, installer locking, temporary-account detection, authenticated versioned encryption, session timeout enforcement, rate-limit consistency, security logging, and conservative HTTP headers.

### Encryption compatibility

New encrypted values use the `v2:` authenticated format. Existing unversioned values continue to use the original decryption path and are not automatically rewritten. Standard installer deployments continue using `LICENSE_ENCRYPTION_KEY`. If no usable configured secret exists, Licora creates `includes/.licora-encryption.key` once; this private runtime key must be backed up with the private configuration and must never be committed.

### Installer recovery

An installed deployment returns HTTP 403 from `install.php`. An intentional recovery requires a database backup and a private copy of `includes/config.local.php` and, when present, `includes/.licora-encryption.key`. Temporarily move the configuration outside the web root only in a private maintenance environment, complete recovery, and restore or regenerate secure configuration before reopening the application.

### Remaining compatibility limitation

`/api/check_license.php` remains unauthenticated by default. Mandatory authentication is not introduced in v5.0.1.1 because it would break existing integrations and violate the frozen API contract. Deployments should prefer `/api/verify.php` with `X-API-Key` or `Authorization: Bearer TOKEN`, restrict the legacy path at the web server or network layer where possible, and retain rate limiting.

## v5.1.1 quality and stability hardening

Licora v5.1.1 removes verified technical-detail disclosure from unhandled exception responses and installer requirement output.

- Public unhandled-error JSON remains `{"error":"Internal Server Error"}`.
- Exception messages, SQL text, credentials, stack traces, and full server paths are not returned.
- Internal logs record a diagnostic reference, exception class, file basename, and line number.
- Installer Base URLs cannot contain embedded credentials, query parameters, or fragments.
- Mail From Name rejects CR/LF control characters.
- Installer-generated secrets are validated before activation.
- Existing legacy configuration and secret compatibility are unchanged.

Historical logs are not deleted or rewritten by this release.
