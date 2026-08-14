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

## v5.1.0 quality and stability hardening

Licora v5.1.0 removes verified technical-detail disclosure from unhandled exception responses and installer requirement output.

- Public unhandled-error JSON remains `{"error":"Internal Server Error"}`.
- Exception messages, SQL text, credentials, stack traces, and full server paths are not returned.
- Internal logs record a diagnostic reference, exception class, file basename, and line number.
- Installer Base URLs cannot contain embedded credentials, query parameters, or fragments.
- Mail From Name rejects CR/LF control characters.
- Installer-generated secrets are validated before activation.
- Existing legacy configuration and secret compatibility are unchanged.

Historical logs are not deleted or rewritten by this release.

## Secure API v2 trust boundary

Licora v5.2.0 API v2 does not require a shared server API key in desktop/public clients. Deployment RSA signing private keys remain server-side; clients use per-device P-256 key pairs and verify/use short-lived server-signed credentials. Refresh tokens are stored as hashes server-side and rotate on use. Production API v2 requires HTTPS by default and uses timestamp/nonce request proofs to resist replay.

Never commit `includes/.licora-v2-signing-private.pem`, `includes/.licora-v2-signing-public.pem`, deployment configuration, device private keys, refresh credentials, or live license/customer data. See `docs/API_V2_SECURITY.md`.


### v5.2.1 API v2 maintenance hardening

Licora v5.2.1 preserves the v5.2.0 public API v2 contract while tightening operational verification. Runtime token services now reject a deployment where the configured RSA private/public signing files do not form the same key pair. Refresh app/device rate-limit writes occur outside the refresh-token row-lock transaction so failed device proofs cannot roll those counters back. Existing deployments without shell access can initialize the additive v2 schema and missing signing keys through the authenticated Client Apps admin page; existing or partial signing files are never replaced automatically.

## v5.3.0 updater trust boundary

The in-app updater is Super-Admin-only and does not accept arbitrary package URLs. It obtains stable release metadata from the configured official GitHub repository, requires a dedicated RSA/SHA-256 signed manifest, validates the signed package size/hash and every staged file hash, rejects path traversal/symlinks/protected deployment paths, uses bounded persistent jobs, serializes concurrent starts, and temporarily blocks non-updater traffic while source/schema mutations are in progress.

The **private update signing key is not a Licora deployment secret** and must never be installed on a Licora server. It belongs only in secured release infrastructure / the GitHub Actions secret `LICORA_UPDATE_SIGNING_PRIVATE_KEY`. The repository contains only the updater public verification key. API v2 signing keys and updater signing keys are separate cryptographic domains and must not be reused.

Updater diagnostics are sanitized operational events; release/download/auth tokens and private key material must never be added to updater event messages.
