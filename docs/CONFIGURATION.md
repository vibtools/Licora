# Configuration Reference

## Loading order

1. `includes/config.php` starts the session.
2. `includes/config.local.php` is loaded when present.
3. Environment variables supply missing constants.
4. Safe or empty defaults are used last.

Environment variables are preferred in managed hosting and containers. Keep local configuration outside the public web root when possible.

## Variables

| Constant | Environment variable | Default | Notes |
|---|---|---|---|
| `DB_HOST` | `LICENSE_DB_HOST`, fallback `DB_HOST` | `localhost` | Database hostname; installer also accepts `host:port`. |
| `DB_NAME` | `LICENSE_DB_NAME`, fallback `DB_NAME` | empty | Required. |
| `DB_USER` | `LICENSE_DB_USER`, fallback `DB_USER` | empty | Use least privilege. |
| `DB_PASS` | `LICENSE_DB_PASS`, fallback `DB_PASS` | empty | Required where the DB user has a password. |
| `APP_NAME` | `APP_NAME` | `License System` | Application label. |
| `APP_URL` | `APP_URL` | `http://localhost` | Used for CORS fallback and key derivation fallback. |
| `APP_VERSION` | `APP_VERSION` | `2.0` | Returned by the verification API. This historical runtime value differs from repository release numbering. |
| `ENVIRONMENT` | `APP_ENV` | `production` | `development` enables verbose output and debug logging. |
| `ENCRYPTION_KEY` | `LICENSE_ENCRYPTION_KEY` | empty | Set a random high-entropy value. Empty invokes a deterministic fallback and is not recommended. |
| `CSRF_SECRET` | `LICENSE_CSRF_SECRET` | empty | Present for compatibility; current CSRF tokens are session-random and do not consume this constant. |
| `JWT_SECRET` | `LICENSE_JWT_SECRET` | empty | Reserved; current code does not issue JWTs. |
| `API_RATE_LIMIT` | `API_RATE_LIMIT` | `1000` | Global hourly IP limit used by both APIs. |
| `API_VERSION` | `API_VERSION` | `v1` | Compatibility metadata. |
| Allowed origin | `LICENSE_ALLOWED_ORIGIN` | `APP_URL` | Exact-origin match for the full API endpoint. |

## Example environment

```dotenv
LICENSE_DB_HOST=127.0.0.1
LICENSE_DB_NAME=license_system
LICENSE_DB_USER=license_app
LICENSE_DB_PASS=replace-with-a-secret
APP_URL=https://licenses.example.com
APP_ENV=production
LICENSE_ENCRYPTION_KEY=replace-with-at-least-32-random-bytes
API_RATE_LIMIT=1000
LICENSE_ALLOWED_ORIGIN=https://app.example.com
```

## Stored settings

The `settings` table and admin page store system name, timezone, default license hours, default device limit, API rate limit, retention, two-factor toggle, maintenance mode, license prefix, and API base URL. The audit found that several stored values are not connected to runtime enforcement. Consult [FEATURE_MATRIX.md](FEATURE_MATRIX.md) before relying on a toggle.

## Time zone

The PHP configuration currently sets `Asia/Dhaka` directly. The stored timezone setting is not applied by the runtime.
