# Licora API v2

API v2 is the recommended Licora protocol for desktop/public clients that cannot safely retain a shared server API key.

## Endpoints

| Endpoint | Purpose |
|---|---|
| `POST /api/v2/activate.php` | Bind an active license to an approved App ID and device public key; issue access/refresh credentials. |
| `POST /api/v2/refresh.php` | Rotate the refresh credential, re-check app version policy, and issue a new short-lived access token. |
| `POST /api/v2/status.php` | Verify current access, device proof, license state and app scope. |
| `POST /api/v2/deactivate.php` | Revoke the current device credential and its refresh credentials. |

All API v2 responses contain `protocol: licora-api-v2`, `api_version: 2`, `server_version`, a request ID, stable machine `code`, message and server time.

## Client identity

API v2 uses:

- stable public `app_id`
- existing Licora `license_key`
- stable client-generated `device_id`
- client-generated P-256 public/private key pair

The client private key is never sent to Licora. API v2 does not require the API v1 `X-API-Key` credential.

## App scope

A v2 license must have a non-empty `licenses.app_scope` exactly matching an enabled `v2_client_apps.app_id`. A license scoped for one application cannot activate another application.

## Access tokens

Licora signs short-lived compact access tokens with a deployment RSA-3072 private key using SHA-256. Tokens include the app, license, device, device-credential ID, public-key fingerprint, expiry, JTI and token version.

## Error codes

Stable codes include `INVALID_LICENSE`, `LICENSE_EXPIRED`, `LICENSE_INACTIVE`, `INVALID_APP`, `APP_NOT_ALLOWED`, `APP_VERSION_UNSUPPORTED`, `DEVICE_LIMIT_REACHED`, `DEVICE_REVOKED`, `DEVICE_KEY_MISMATCH`, `INVALID_DEVICE_PROOF`, `REPLAY_DETECTED`, `TOKEN_EXPIRED`, `INVALID_REFRESH_TOKEN`, `REFRESH_TOKEN_REUSED`, and `RATE_LIMITED`.
