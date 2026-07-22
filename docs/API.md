# API Reference

## Full verification endpoint

`POST /api/verify.php`

### Headers

- `Content-Type: application/json`
- `X-API-Key: <key>` — recommended and verified by the current implementation.

The endpoint advertises `Authorization`, but the forensic audit identified inconsistent Bearer extraction. Use `X-API-Key` until the parser is corrected in a reviewed release.

### Request

```json
{
  "license_key": "AAAAAAAA-BBBBBBBB-CCCCCCCC-DDDDDDDD",
  "device_hash": "client-generated-stable-identifier",
  "app_id": "desktop-client",
  "app_version": "1.0.0"
}
```

`license_key` is required and must use four groups of eight uppercase alphanumeric characters. `device_hash`, `app_id`, and `app_version` are optional. A missing device hash is generated from request headers on the server, which is less reliable than a stable client-generated value.

### Success response

```json
{
  "success": true,
  "license": {
    "key": "AAAAAAAA-BBBBBBBB-CCCCCCCC-DDDDDDDD",
    "expires": "2026-12-31 23:59:59",
    "device_limit": 1,
    "devices_used": 1,
    "status": "active",
    "created_at": "2026-07-22 12:00:00"
  },
  "device_hash": "client-generated-stable-identifier",
  "timestamp": "2026-07-22T12:00:00+06:00",
  "server_time": 1784700000,
  "server_version": "2.0",
  "message": "License valid"
}
```

Possible failures include missing/invalid API key, malformed JSON, invalid request fields, invalid or expired license, suspended/blacklisted access, API-key or application-scope mismatch, device-limit exhaustion, and rate limiting.

### Binding behavior

- A license with `api_key_id` accepts only that API key.
- A license with `app_scope` but no direct key binding compares the license scope with the API key's `app_name` or `scope_label`.
- Unscoped licenses retain legacy behavior.

## Simple verification endpoint

`POST /api/check_license.php`

```json
{
  "license_key": "AAAAAAAA-BBBBBBBB-CCCCCCCC-DDDDDDDD",
  "device_hash": "client-generated-stable-identifier"
}
```

This endpoint does not require an API key and calls license verification without an API-key context. It is retained for backward compatibility but should not be exposed to untrusted networks without an external gateway rule. Scoped licenses may fail because no API-key context is supplied.

## Rate limiting

Both endpoints use an IP-and-endpoint counter in `rate_limits` with a one-hour window. The full endpoint applies the global `API_RATE_LIMIT`; the per-key `rate_limit_per_hour` column is currently not enforced.

## CORS

The full endpoint returns `Access-Control-Allow-Origin` only when the request origin exactly matches `LICENSE_ALLOWED_ORIGIN` or `APP_URL`. The simple endpoint does not implement CORS headers.

## Status codes

The full endpoint explicitly uses `200`, `400`, `401`, `405`, and `429` in key paths. Some license failures are serialized with a response body while the final HTTP status may remain `200`; integrations must inspect the `success` field.
