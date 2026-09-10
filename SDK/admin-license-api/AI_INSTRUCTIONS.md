# AI Integration Instructions

Use this file as the authoritative context when integrating the enclosed Licora Admin License API SDK into a trusted PHP or Node.js backend.

## Objective

Connect an existing server-side order or automation system to Licora without reimplementing request signing. Use the supplied client for every request.

## Non-negotiable boundaries

1. This is a privileged server-to-server API. Never put `LICORA_ADMIN_API_KEY` in frontend JavaScript, Electron renderer code, desktop binaries, mobile apps, logs, screenshots, URLs or source control.
2. Do not replace the HMAC canonicalization, nonce generation, timestamp generation, exact JSON bytes or URL construction implemented by the client.
3. Do not use an API v1 key or Secure API v2 public-client credential. The required key format is `licora_admin_live_<64 hex>` or `licora_admin_test_<64 hex>`.
4. Do not create or manage Client Apps through this SDK. Client Apps and key assignments are configured in Licora Admin.
5. A key may operate only on licenses created by that key's order mappings and applications currently assigned to it.
6. Preserve the caller's immutable order identifier as `external_order_id` and use a stable `Idempotency-Key` for license creation.
7. Do not automatically retry ambiguous non-idempotent operations such as `extend`, `ban`, `delete` or device revocation. Reconcile current state first.
8. Never weaken HTTPS enforcement, response-envelope validation, redirect rejection, response-size limits or secret redaction.

## Files to use

- PHP: `php/src/LicoraAdminClient.php` and `php/src/LicoraAdminApiException.php`
- Node.js: `nodejs/src/licora-admin-client.mjs`
- Endpoint rules: `docs/API_REFERENCE.md`
- Proof rules: `docs/AUTHENTICATION_AND_SIGNING.md`
- Deployment/retry rules: `docs/SECURITY_AND_OPERATIONS.md`

## Required configuration

Read from the backend's environment or secret manager:

```text
LICORA_ADMIN_API_BASE_URL=https://licenses.example.com/licora
LICORA_ADMIN_API_KEY=licora_admin_live_<secret>
```

The base URL is the Licora installation root. The supplied client appends `/api/admin/v1/...` and includes any installation subdirectory in the signed request target.

## Integration sequence

1. Instantiate one client with the environment values.
2. At successful payment/order completion, call `createLicense` using the immutable order ID and a stable idempotency key.
3. Store `license_id`, `external_order_id`, `app_id`, `status` and `expires_at` in the order system. Deliver the returned full `license_key` only through the approved secure customer channel.
4. Use `licenseStatus` to reconcile a known order/license.
5. Use explicit action methods for activate/suspend/extend/ban/delete.
6. Use `listDevices` and `revokeDevice` only from authenticated administrative workflows.
7. Catch the supplied API exception class. Log only `code`, HTTP status and `requestId`; do not log the API key, signature, raw authorization header or returned license key.

## Safe retry policy

- GET: retry with a new timestamp, nonce and signature.
- Create license: retry only with the same `external_order_id`, identical normalized payload and same `Idempotency-Key`.
- `429 RATE_LIMITED`: honor `Retry-After` when present or apply bounded exponential backoff.
- Network failure during `extend`, `ban`, `delete` or revoke: query current status before deciding whether to issue another mutation.
- `401`/`403`: stop retrying and treat as credential, clock, IP allowlist, scope or app-assignment configuration failure.
- `409`: inspect the response code and reconcile order/license state.

## Completion checklist

- Backend-only secret storage confirmed.
- Correct Licora root URL confirmed, including any subdirectory.
- Key has least-privilege scopes and application assignments.
- System clock is synchronized.
- Create flow uses stable idempotency.
- License key is excluded from normal logs and analytics.
- Error handling records `request_id` for Licora Admin log correlation.
- Deployment uses PHP 8.0+ with cURL/JSON or Node.js 18+.

Do not modify Licora's server endpoints, database schema or cryptographic contract to integrate this package.
