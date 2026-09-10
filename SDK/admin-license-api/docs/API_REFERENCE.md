# Admin License API Reference

Base path: `/api/admin/v1`

All operations require the signed headers documented in `AUTHENTICATION_AND_SIGNING.md`.

## Response envelope

Every JSON response contains:

```json
{
  "success": true,
  "protocol": "licora-admin-api",
  "api_version": 1,
  "server_version": "5.8.4",
  "request_id": "correlation-id",
  "code": "OK",
  "message": "Operation completed.",
  "server_time": 1789000000,
  "data": {},
  "meta": {}
}
```

`data` and `meta` appear when applicable. Both clients return the complete envelope. Failed HTTP responses raise the supplied exception and retain the HTTP status, API code, request ID and decoded envelope.

## Permission scopes

| Scope | Client operation |
|---|---|
| `license:create` | `createLicense` |
| `license:read` | `licenseStatus`, `listLicenses`, `listAllowedApps` |
| `license:reveal` | `licenseStatus(... includeKey=true)` |
| `license:activate` | `activateLicense` |
| `license:suspend` | `suspendLicense` |
| `license:extend` | `extendLicense` |
| `license:ban` | `banLicense` |
| `license:delete` | `deleteLicense` |
| `device:read` | `listDevices` |
| `device:revoke` | `revokeDevice` |

## List allowed applications

```text
GET /api/admin/v1/apps/list.php
```

Returns only active applications currently assigned to the API key. Each item contains `app_id`, `display_name`, and `min_version`.

## Create license

```text
POST /api/admin/v1/licenses/create.php
Scope: license:create
Required: Idempotency-Key
```

Body:

```json
{
  "external_order_id": "ORDER-10482",
  "customer_reference": "USER-9081",
  "app_id": "vibrapilot",
  "validity_hours": 720,
  "device_limit": 2,
  "notes": "Paid order"
}
```

Required fields are `external_order_id`, `app_id`, `validity_hours`, and `device_limit`. `customer_reference` and `notes` are optional. Order/customer identifiers allow 1–120 letters, digits, `.`, `_`, `:`, `/`, and `-`. Device limit is 1–100. Validity must satisfy the Licora Admin minimum and maximum settings.

New creation returns HTTP 201 with code `LICENSE_CREATED`. An exact replay returns HTTP 200 with code `LICENSE_EXISTS`. Both return the existing/full `license_key` in `data`.

## Read license status

```text
GET /api/admin/v1/licenses/status.php?license_id=42
GET /api/admin/v1/licenses/status.php?external_order_id=ORDER-10482
```

Exactly one identifier is normally supplied. Add `include_key=true` to reveal the full key; this requires `license:reveal` in addition to `license:read`.

## List licenses

```text
GET /api/admin/v1/licenses/list.php?page=1&per_page=25&app_id=vibrapilot&status=active&customer_reference=USER-9081
```

All filters are optional. `per_page` is capped at 100. Status values are `active`, `suspended`, `expired`, `banned`, and `deleted`. Pagination is returned in `meta`: `page`, `per_page`, `total`, and `pages`.

## License record fields

License create/read/list/action responses use these fields as applicable:

```text
license_id, license_key_masked, license_key, external_order_id,
customer_reference, app_id, status, device_limit, active_devices,
notes, created_at, updated_at, expires_at, banned_at, ban_reason, deleted_at
```

`license_key` is included only for create/idempotent create or an authorized reveal request.

## Activate license

```text
POST /api/admin/v1/licenses/action.php
{"license_id":42,"action":"activate"}
```

Requires `license:activate`. An expired license must be extended before activation.

## Suspend license

```text
POST /api/admin/v1/licenses/action.php
{"license_id":42,"action":"suspend"}
```

Requires `license:suspend`.

## Extend license

```text
POST /api/admin/v1/licenses/action.php
{"license_id":42,"action":"extend","additional_hours":720}
```

Requires `license:extend`. The extension and resulting horizon must satisfy server validity limits.

## Ban license

```text
POST /api/admin/v1/licenses/action.php
{"license_id":42,"action":"ban","reason":"Confirmed payment fraud"}
```

Requires `license:ban`. Reason length is 1–500 characters. Ban revokes the license's devices and refresh tokens.

## Soft-delete license

```text
POST /api/admin/v1/licenses/action.php
{"license_id":42,"action":"delete"}
```

Requires `license:delete`. This is an audited soft delete and revokes devices/tokens.

## List devices

```text
GET /api/admin/v1/devices/list.php?license_id=42
```

Requires `device:read`. Device records contain `credential_id`, `app_id`, `device_id`, `public_key_fingerprint`, `status`, `first_seen_at`, `last_seen_at`, and `revoked_at`.

## Revoke device

```text
POST /api/admin/v1/devices/revoke.php
{"credential_id":91}
```

Requires `device:revoke`. The device must belong to a license owned by the calling key. The response contains `credential_id`, `license_id`, and status `revoked`.

## Common error codes

```text
AUTHENTICATION_REQUIRED  INVALID_API_KEY       INVALID_REQUEST_PROOF
STALE_REQUEST            REPLAY_DETECTED       HTTPS_REQUIRED
IP_NOT_ALLOWED           RATE_LIMITED          SCOPE_REQUIRED
APP_NOT_ALLOWED          INVALID_CONTENT_TYPE  INVALID_JSON
REQUEST_TOO_LARGE        INVALID_REQUEST       INVALID_APP
INVALID_VALIDITY         INVALID_DEVICE_LIMIT  IDEMPOTENCY_KEY_REQUIRED
IDEMPOTENCY_CONFLICT     ORDER_CONFLICT        REQUEST_IN_PROGRESS
LICENSE_NOT_FOUND        LICENSE_EXPIRED       LICENSE_BANNED
LICENSE_DELETED          INVALID_ACTION        DEVICE_NOT_FOUND
ADMIN_API_NOT_READY      INTERNAL_ERROR
```

Use `request_id` to correlate an error with **Admin License API → Latest API requests**. Do not expose internal exception details to customers.
