# Admin License Control API

Licora v5.8.3 adds a dedicated server-to-server API for order systems. It creates and manages licenses for existing Secure API v2 client applications. It does **not** create, update or delete applications, and it does not accept API v1 keys or public API v2 device credentials.

## Ready SDK and documentation

The Admin License API page now provides two authenticated actions:

- **Download Ready SDK** builds and downloads `Licora-Admin-API-SDK-v1.0.0.zip` from the maintained source package in `SDK/admin-license-api`.
- **Documents** opens the bundled Markdown guides inside the Admin panel.

The ZIP contains dependency-light PHP 8+ and Node.js 18+ clients, examples, configuration templates, signing/security references and `AI_INSTRUCTIONS.md`. The SDK performs request signing but does not change Admin API authorization, application ownership or scope enforcement.

## Setup

1. Apply `migration-v5.8.3-admin-license-api.sql` when upgrading from v5.8.2. Fresh installs already contain the schema in `database.sql`.
2. Create or verify the target application under **Admin → API & Clients → Client Apps**.
3. Open **Admin → Settings → Admin License API**.
4. Create a key, select its exact allowed applications and least-privilege scopes, then copy the secret shown once.
5. Store the secret only in the order website's server-side secret manager. Never expose it to a browser, desktop application or mobile application.

Keys support active/suspended/permanently-revoked states, rotation, optional IPv4/IPv6 CIDR allowlists, per-key hourly rate limits and optional expiry. A rotated key invalidates its previous secret immediately.

## Authentication and request proof

HTTPS is required by default. Every request must send:

```text
Authorization: Bearer licora_admin_live_<64 lowercase hex characters>
X-Licora-Timestamp: <10-digit Unix timestamp>
X-Licora-Nonce: <unique 16-128 character value>
X-Licora-Signature: <lowercase HMAC-SHA256 hex>
```

The signature secret is the complete bearer token. Build the canonical value from the exact values sent:

```text
UPPERCASE_HTTP_METHOD + "\n" +
EXACT_REQUEST_TARGET_WITH_QUERY + "\n" +
TIMESTAMP + "\n" +
NONCE + "\n" +
LOWERCASE_SHA256_OF_EXACT_BODY_BYTES
```

For GET requests, the body is empty. The request target is the path as deployed, including any base subdirectory and query string—for example `/licora/api/admin/v1/licenses/status.php?license_id=42`. The default clock window is 300 seconds. A nonce can be used only once per key during the replay window.

Create requests must also include a stable `Idempotency-Key` of 8–120 characters. Use the order system's immutable payment/order identifier. Repeating the same order and same normalized request returns the existing license; changing parameters for that order returns `ORDER_CONFLICT`.

## Permissions

| Scope | Operation |
|---|---|
| `license:create` | Create an app-bound license |
| `license:read` | List/read license status and allowed applications |
| `license:reveal` | Include a full license key in a status response |
| `license:extend` | Extend validity |
| `license:activate` | Reactivate a non-expired, non-banned license |
| `license:suspend` | Suspend a license |
| `license:ban` | Ban a license and revoke its devices/tokens |
| `license:delete` | Soft-delete a license and revoke its devices/tokens |
| `device:read` | List devices for an owned license |
| `device:revoke` | Revoke a device and refresh tokens |

Every license is owned by the Admin API key that created its order mapping. A key can act only on its own licenses and its currently assigned, active applications. License creation sets the existing `licenses.app_scope` to the exact selected app and leaves the API v1 key binding empty.

## Endpoints

All responses use JSON and include `success`, `protocol`, `api_version`, `server_version`, `request_id`, `code`, `message` and `server_time`.

### Create license

`POST /api/admin/v1/licenses/create.php`

Required scope: `license:create`. Required header: `Idempotency-Key`.

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

`external_order_id` and optional `customer_reference` accept 1–120 letters, digits and `._:/-`. Validity follows the Admin license min/max settings; device limit is 1–100. A new create returns HTTP 201 and the full license key. An exact replay returns HTTP 200 and the same license.

### License status

`GET /api/admin/v1/licenses/status.php?license_id=42`

or

`GET /api/admin/v1/licenses/status.php?external_order_id=ORDER-10482`

Required scope: `license:read`. Add `include_key=true` only when the key also has `license:reveal`; otherwise only a masked key is returned.

### List licenses

`GET /api/admin/v1/licenses/list.php?page=1&per_page=25&app_id=vibrapilot&status=active&customer_reference=USER-9081`

Required scope: `license:read`. Filters are optional. `per_page` is capped at 100. Status values are `active`, `suspended`, `expired`, `banned` and `deleted`.

### Manage a license

`POST /api/admin/v1/licenses/action.php`

```json
{"license_id":42,"action":"suspend"}
```

Actions and extra fields:

- `activate`: no extra field
- `suspend`: no extra field
- `extend`: required `additional_hours`
- `ban`: required `reason` (1–500 characters)
- `delete`: no extra field; this is an audited soft delete

Each action requires its matching `license:<action>` scope. Ban/delete revoke all API v2 device credentials and refresh tokens for the license and deactivate legacy device rows.

### List devices

`GET /api/admin/v1/devices/list.php?license_id=42`

Required scope: `device:read`.

### Revoke device

`POST /api/admin/v1/devices/revoke.php`

```json
{"credential_id":91}
```

Required scope: `device:revoke`. The credential must belong to a license owned by the calling key.

### List allowed applications

`GET /api/admin/v1/apps/list.php`

Required scope: `license:read`. Returns only active applications assigned to the key. There is intentionally no Admin API endpoint for application creation or management.

## PHP request example

```php
<?php
$secret = getenv('LICORA_ADMIN_API_KEY');
$target = '/api/admin/v1/licenses/create.php';
$url = 'https://licenses.example.com' . $target;
$body = json_encode([
    'external_order_id' => 'ORDER-10482',
    'customer_reference' => 'USER-9081',
    'app_id' => 'vibrapilot',
    'validity_hours' => 720,
    'device_limit' => 2,
], JSON_UNESCAPED_SLASHES);
$timestamp = (string) time();
$nonce = bin2hex(random_bytes(16));
$canonical = "POST\n{$target}\n{$timestamp}\n{$nonce}\n" . hash('sha256', $body);
$signature = hash_hmac('sha256', $canonical, $secret);

$curl = curl_init($url);
curl_setopt_array($curl, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $body,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $secret,
        'Content-Type: application/json',
        'Idempotency-Key: checkout-ORDER-10482',
        'X-Licora-Timestamp: ' . $timestamp,
        'X-Licora-Nonce: ' . $nonce,
        'X-Licora-Signature: ' . $signature,
    ],
]);
$response = curl_exec($curl);
$status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
curl_close($curl);
```

Persist `external_order_id`, returned `license_id`, status and expiry in the order system. Retry network failures with the same order data and idempotency key. Treat 401/403 as credential/configuration incidents and 409 as an order or replay conflict requiring review.

## Error codes

Common codes include `AUTHENTICATION_REQUIRED`, `INVALID_API_KEY`, `INVALID_REQUEST_PROOF`, `STALE_REQUEST`, `REPLAY_DETECTED`, `IP_NOT_ALLOWED`, `RATE_LIMITED`, `SCOPE_REQUIRED`, `APP_NOT_ALLOWED`, `IDEMPOTENCY_KEY_REQUIRED`, `IDEMPOTENCY_CONFLICT`, `ORDER_CONFLICT`, `LICENSE_NOT_FOUND`, `LICENSE_EXPIRED`, `LICENSE_BANNED`, `LICENSE_DELETED`, and `ADMIN_API_NOT_READY`.

The `request_id` is safe to use when correlating a caller error with the Admin API request log. Server responses never return stack traces or database details.
