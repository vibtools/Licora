# Authentication and Request Signing

## Transport

Use HTTPS. The SDK rejects non-HTTPS base URLs and does not follow redirects. Configure the base URL as the Licora installation root:

```text
https://licenses.example.com
https://licenses.example.com/licora
```

Do not configure an API endpoint as the base URL.

## Required headers

Every request sends:

```text
Authorization: Bearer licora_admin_live_<64 lowercase hex characters>
Accept: application/json
X-Licora-Timestamp: <10-digit Unix timestamp>
X-Licora-Nonce: <unique 16-128 character value>
X-Licora-Signature: <64-character lowercase HMAC-SHA256 hex>
```

JSON POST requests also send:

```text
Content-Type: application/json
```

License creation additionally sends:

```text
Idempotency-Key: <stable 8-120 character order/payment identifier>
```

Allowed idempotency characters are letters, digits, `.`, `_`, `:`, and `-`.

## Canonical value

The HMAC secret is the complete Admin API bearer token. Join exactly five fields with a single line-feed byte (`\n`) and no trailing line feed:

```text
UPPERCASE_METHOD
EXACT_REQUEST_TARGET_WITH_QUERY
TIMESTAMP
NONCE
LOWERCASE_SHA256_OF_EXACT_BODY_BYTES
```

Equivalent definition:

```text
UPPERCASE_HTTP_METHOD + "\n" +
EXACT_REQUEST_TARGET_WITH_QUERY + "\n" +
TIMESTAMP + "\n" +
NONCE + "\n" +
LOWERCASE_SHA256_OF_EXACT_BODY_BYTES
```

Calculate:

```text
signature = lowercase_hex(HMAC_SHA256(admin_api_key, canonical_value))
```

## Request target rules

The target is the path actually sent to Licora, including:

- the Licora installation subdirectory;
- the endpoint path;
- the exact encoded query string and its ordering.

Example:

```text
/licora/api/admin/v1/licenses/status.php?external_order_id=ORDER-10482&include_key=true
```

Do not sign the scheme, host or URL fragment. Do not build a query after calculating the signature. The PHP and Node.js clients construct the URL first and sign its resulting path and query.

## Body rules

- GET requests use an empty body, so the final canonical field is SHA-256 of zero bytes.
- POST requests hash the exact UTF-8 JSON bytes that are transmitted.
- Do not reformat, re-encode or rebuild JSON after signing.
- Field order does not need to be globally standardized, but the hashed bytes and transmitted bytes must be identical.

## Timestamp and replay protection

`X-Licora-Timestamp` is the current 10-digit Unix timestamp. The default acceptance window is 300 seconds. Synchronize production servers with NTP.

`X-Licora-Nonce` must match `[A-Za-z0-9_-]{16,128}` and may be used only once by a key during the replay window. Both supplied clients generate a fresh cryptographically random nonce for every attempt.

When retrying, generate a new timestamp, nonce and signature. The creation `Idempotency-Key` remains stable.

## Creation idempotency

Use an immutable payment/order identifier, for example:

```text
checkout-ORDER-10482
```

Repeating the same order with the same normalized parameters returns the existing license. Reusing the order or idempotency key with different parameters returns `ORDER_CONFLICT` or `IDEMPOTENCY_CONFLICT`.

Never generate a new idempotency key for each retry of the same logical order.
