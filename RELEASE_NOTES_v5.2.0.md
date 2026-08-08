# Licora v5.2.0 — Secure API v2

**Release date:** 2026-08-08  
**Release type:** Backward-compatible secure client API feature release  
**Stable base:** `v5.1.0`  
**Database migration:** Additive `migration-v5.2.0-api-v2.sql`

## Summary

Licora v5.2.0 adds a new Secure API v2 for desktop and other public clients without requiring a shared Licora master API key inside the client application. Existing API v1 endpoints and their request/response contracts remain unchanged.

API v2 introduces application registration, device-bound P-256 public keys, RS256 server-signed short-lived access tokens, rotating hashed refresh tokens, nonce/timestamp replay protection, device revocation, security audit events, and additive administrative pages.

## API v1 compatibility

The following v5.1.0 implementation files are frozen byte-for-byte at the Git content-contract level:

- `api/verify.php`
- `api/check_license.php`
- `includes/functions.php`
- `includes/security.php`

Existing API-key clients can continue using API v1. The legacy unauthenticated compatibility endpoint is not promoted for new integrations.

## Secure API v2 endpoints

- `POST /api/v2/activate.php`
- `POST /api/v2/refresh.php`
- `POST /api/v2/status.php`
- `POST /api/v2/deactivate.php`

API v2 desktop requests do not use `X-API-Key`. Each activated installation proves possession of its own P-256 private key, while the server signs access tokens with a deployment-specific RSA-3072 key pair.

## Database changes

Five new tables are additive:

- `v2_client_apps`
- `v2_device_credentials`
- `v2_refresh_tokens`
- `v2_used_nonces`
- `v2_audit_logs`

Existing tables are not removed or renamed. API v2 reuses the existing `licenses`, `devices`, `blacklist`, `rate_limits`, and `settings` data model where appropriate.

Existing deployments run:

```bash
php scripts/setup-v2.php
```

The setup script applies the additive migration and generates the deployment signing key pair if neither key file exists. It refuses to overwrite a partial/existing key pair.

## Admin additions

- **Client Apps** registers stable public App IDs and token/rate policies.
- **V2 Devices** lists device-bound credentials and allows authorized administrators to revoke a device.
- License creation keeps the existing API v1 binding selector and adds a separate API v2 Client App scope selector.

## CI and release automation

A normal push or pull request now runs the source verifier across PHP 8.0–8.4, runs a dedicated MySQL API v2 integration job, and builds a verified CI source ZIP/checksum artifact after all required jobs pass.

Pushing a semantic version tag automatically validates the exact tag, runs the database integration test, builds the exact-tag release ZIP/checksum, and publishes a GitHub Release using the matching `RELEASE_NOTES_<tag>.md` file.

## Security notes

- Server signing private keys are deployment material and are never committed or packaged.
- Refresh-token plaintext is returned only to the client; the database stores SHA-256 token hashes.
- Refresh tokens rotate after use. Reuse revokes the token family.
- Signed requests bind method, path, timestamp, nonce, request-body SHA-256 and a request context.
- Production API v2 requires HTTPS by default.
- No API v2 desktop endpoint accepts the API v1 shared/master key.

See `docs/API_V2_SECURITY.md` and `docs/API_V2_CLIENT_INTEGRATION.md` before integrating a client.
