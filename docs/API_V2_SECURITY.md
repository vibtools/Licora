# Licora API v2 Security Model

API v2 removes the requirement for a global/shared Licora API key in desktop/public clients. It does not attempt to hide a server master secret inside a distributed executable.

## Trust boundaries

**Server only:** RSA signing private key, database credentials, existing administrative/server API credentials.  
**Public client:** App ID, API v2 URL, server public verification key, device public key.  
**Device local secret:** device P-256 private key and refresh credential; the client integration is responsible for OS-backed protection.

## Device proof

Authenticated requests are signed by the device P-256 private key over the canonical sequence:

```text
HTTP_METHOD
REQUEST_PATH
TIMESTAMP
NONCE
BODY_SHA256
CONTEXT
```

For access-token requests the context is the access token JTI. Activation uses `activate:<app_id>` and refresh uses a SHA-256-derived refresh-token context.

Licora validates the timestamp window, nonce format, stored device public key and ECDSA SHA-256 signature. Successful/accepted nonces are persisted with a unique `(device_credential_id, nonce_hash)` constraint to prevent replay.

## Server-signed access tokens

Access tokens use a fixed `LICORA-V2`/`RS256` contract and a configured `kid`. Algorithm negotiation from the token is not accepted. The server validates signature, issuer, token version, audience/app consistency, `nbf`, `iat`, and expiry.

## Refresh credentials

Refresh tokens are 256-bit random values. Only SHA-256 hashes are stored in `v2_refresh_tokens`. Each successful refresh creates a new token in the same family and marks the previous token used. Reuse/expiry/revocation of an old token revokes the family outside the failed row-lock transaction so the revocation cannot be undone by rollback.

## Signing key files

Default deployment paths are:

```text
includes/.licora-v2-signing-private.pem
includes/.licora-v2-signing-public.pem
```

Both are runtime/deployment files and are gitignored and excluded from release ZIPs. `scripts/setup-v2.php` creates RSA-3072 keys only from CLI and never overwrites a partial key pair. The baseline Apache deployment denies direct access to `includes/`; Nginx/LiteSpeed or other servers must enforce the equivalent deny rule. For stronger isolation, production deployments may configure the signing key paths outside the web root.

## Network and input controls

Production API v2 requires HTTPS by default. Proxy HTTPS headers are trusted only when explicitly enabled. Requests are POST-only, JSON-only, size-bounded, field-allowlisted and rate-limited. Public error responses use stable codes without returning stack traces or key material.
