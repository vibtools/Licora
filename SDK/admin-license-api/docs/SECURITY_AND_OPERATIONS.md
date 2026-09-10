# Security and Production Operations

## Secret handling

- Store the Admin API key in a managed backend secret store or protected server environment variable.
- Never commit the key, return it to a browser, embed it in an application package, or include it in a URL.
- Redact `Authorization`, `X-Licora-Signature`, raw response bodies containing `license_key`, and environment dumps from logs.
- Treat a revealed license key as customer-sensitive data.
- Rotate the Admin API key immediately after suspected disclosure. The previous secret becomes invalid immediately.

## Least privilege

Create separate keys for independent services or environments. Assign only required:

- applications;
- permission scopes;
- IP/CIDR ranges;
- rate limit;
- expiry.

Do not give a checkout creator key ban/delete/device-revoke scopes unless that service genuinely performs those operations.

## Ownership boundary

An Admin API key controls only licenses represented by order mappings created by that key. Application assignment is checked again on every operation. Changing a key's assigned applications immediately changes what it can access.

## Idempotent order processing

Use one immutable `external_order_id` per paid order/license. Persist:

```text
external_order_id
license_id
app_id
status
expires_at
customer_reference
```

Use a stable `Idempotency-Key` derived from the same immutable order/payment identity. A create retry must use identical license parameters.

## Retry matrix

| Condition | Rule |
|---|---|
| GET network/5xx failure | Bounded retry with a new signed proof. |
| Create network/5xx failure | Retry with identical body, order ID and idempotency key. |
| `429 RATE_LIMITED` | Honor `Retry-After` when available; use bounded exponential backoff. |
| `401` or `403` | Do not retry automatically; investigate key, clock, proof, IP, scope and app assignment. |
| `409` | Reconcile order/license state according to the API code. |
| Extend/ban/delete/revoke network ambiguity | Read current state before deciding whether to repeat. |

The supplied clients intentionally do not hide retries inside mutation methods.

## Error logging

Safe operational fields:

```text
API error code
HTTP status
request_id
operation name
external_order_id or internal license_id
```

Unsafe fields:

```text
Admin API key
Authorization header
HMAC signature
full request/response headers
full license key
customer secrets
```

## Time synchronization

Request proofs expire quickly. Synchronize every calling server with a reliable NTP source. Repeated `STALE_REQUEST` errors indicate clock drift or delayed queued requests.

## Proxy and subdirectory deployments

Set the base URL to the externally requested Licora root. If Licora is deployed at `/licora`, that path must be present because the exact request target is signed. A reverse proxy must preserve the public path consistently or rewrite it in a way that Licora's `REQUEST_URI` matches the client-signed target.

HTTPS termination behind a proxy must use Licora's reviewed trusted-proxy configuration. Do not disable TLS verification in either runtime.

## Rate limiting and queues

- Keep worker concurrency below the key's hourly allowance.
- Serialize or deduplicate fulfillment events by immutable order ID.
- Add a dead-letter/review path for persistent 401, 403 and 409 responses.
- Keep the `request_id` for support correlation.

## Key lifecycle

1. Create a test key with test-only apps and minimal scopes.
2. Validate the fulfillment workflow.
3. Create a separate live key.
4. Apply IP allowlists after egress addresses are stable.
5. Rotate on a schedule appropriate to the deployment.
6. Suspend during investigation; permanently revoke after replacement is confirmed.

## Incident response

If a secret may be exposed:

1. Rotate or revoke it from **Admin License API**.
2. Stop affected workers.
3. Inspect **Latest API requests** using time, route, response code and request ID.
4. Review assigned scopes, applications and allowed IPs.
5. Replace the backend secret and restart the service.
6. Investigate licenses created or modified during the exposure window.

## Deployment checklist

- Production HTTPS URL verified.
- Secret is server-only and redacted.
- Application assignment is exact.
- Scopes are least-privilege.
- NTP is enabled.
- Stable order/idempotency mapping is persisted.
- Retry policy distinguishes safe reads/idempotent create from ambiguous mutations.
- Response `protocol` and `api_version` validation remains enabled.
- Outbound timeout and response-size limits remain enabled.
- PHP cURL/JSON or Node.js 18+ is available.
