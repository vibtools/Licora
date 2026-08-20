# Licora Error Handling — Baseline Inventory & Production Target

## Authority

This matrix preserves the original v5.5.1 baseline inventory and records the implemented Phase 1 backend error contract through the v5.6.1 corrective candidate; Phase 2 client-side refresh handling remains planned.

Status meanings:

- `EXISTING` — present in frozen source
- `PARTIAL` — exists but is inconsistent or unsuitable for new dashboard AJAX behavior
- `REQUIRED` — must be added by the planned update
- `DEFERRED` — not part of this dashboard program

---

# 1. Existing Global Production Error Policy

## Production PHP display policy — `EXISTING`

`includes/config.php`:

- production: `error_reporting(0)`
- `display_errors=0`
- `log_errors=1`
- development: errors displayed/logged

**Production requirement:** keep this behavior unchanged.

## Database connection failure — `EXISTING`

`includes/database.php`:

- catches `PDOException`
- writes detailed message to server error log
- development may display detailed DB error
- production returns generic `Database connection error. Please try again later.`

**Assessment:** suitable baseline separation of internal vs public error detail.

---

# 2. Authentication / Session Errors

## Admin authentication — `EXISTING`

Protected pages call `Auth::isAdminLoggedIn()` and redirect unauthenticated users to login.

## Session security — `EXISTING`

Actual source enforces:

- user-agent consistency
- 30-minute inactivity timeout
- session invalidation
- audit attempt on invalidation
- session clearing

If security audit logging itself fails, error is logged without preventing session invalidation.

## Login throttling — `EXISTING`

Failed-login limit can terminate with a generic “Too many failed login attempts” message.

---

# 3. CSRF Handling

## Generic CSRF requirement — `EXISTING`

`Security::requireCSRFToken()`:

- HTTP 403
- terminates request with `Invalid CSRF token`

## Updater AJAX CSRF — `EXISTING`

Structured JSON:

- 403
- `CSRF_FAILED`
- generic message

**Target for Dashboard:** Dashboard data endpoint is GET/read-only and should not mutate state; no CSRF token is required merely to read if same-session auth is required. If future dashboard actions mutate state, they require CSRF.

---

# 4. API v2 Error Handling

**Status:** `EXISTING / STRONG`

API v2 uses `V2Exception`:

- machine code
- public safe message
- HTTP status

Unexpected throwable:

- internal details logged
- client receives 500
- code `INTERNAL_ERROR`
- message `Request could not be completed.`

Each endpoint wraps execution in `try/catch`, and major success/failure events are audited.

**Rule:** Dashboard AJAX should follow a similarly structured safe-error pattern.

---

# 5. Updater Error Handling

**Status:** `EXISTING / STRONG`

Updater uses `UpdateException`:

- stable error code
- HTTP status
- safe public message

Unexpected failures:

- internal log
- generic 500:
  - `UPDATE_INTERNAL_ERROR`
  - no stack trace

Updater additionally has explicit validation for:

- HTTP/network failures
- release metadata
- signatures
- package size/hash
- archive contents
- backup/rollback
- migration/state
- filesystem/runtime failures

This behavior must remain frozen.

---

# 6. Installer Error Handling

**Status:** `EXISTING / STRONG`

Installer:

- validates input
- handles CSRF
- maps DB permission failures to safe messages
- specifically maps missing `TRIGGER` privilege
- avoids exposing raw SQL exceptions
- logs finalization failures internally
- performs cleanup attempts
- rethrows sanitized public error

Do not weaken this behavior.

---

# 7. License/Core Service Errors

## License creation — `PARTIAL`

`LicenseSystem::createLicense()` catches generic `Exception` and returns:

`License creation failed: <exception message>`

This can expose internal detail to an authenticated admin UI.

It is not a public API response, but it is less sanitized than API v2/updater patterns.

**Dashboard program:** do not expand scope to rewrite all license errors unless required by a direct dashboard dependency. Record as a global hardening candidate.

## License verification — `EXISTING`

Unexpected verification errors:

- rollback
- detailed error logged
- returns generic `Verification failed`

---

# 8. Legacy API v1 / Compatibility Endpoint

## `/api/verify.php` — `PARTIAL`

Has explicit responses for:

- method errors
- rate limit
- missing/invalid API key
- invalid JSON
- validation failures

Development mode can return redacted debug metadata.

Production mode avoids that debug payload.

## `/api/check_license.php` — `PARTIAL`

Has basic method/rate-limit/format handling, but does not use the more structured machine-code error envelope of API v2.

Compatibility contract must not be silently broken by the dashboard update.

---

# 9. Cron Error Handling

## `cron/cleanup.php` — `PARTIAL`

Runs sequential PDO operations and prints progress. It does not wrap the entire job in a top-level structured try/catch/exit-code strategy.

If PDO throws, PHP exits with an error according to CLI/runtime configuration.

## `cron/check_expiring.php` — `PARTIAL`

Queries and prints results; no top-level structured failure/exit-code policy.

Email notification is not implemented.

**Dashboard program:** do not claim cron health based solely on directory existence. A separate cron-hardening project can add structured exit codes/heartbeat if approved.

---

# 10. Dashboard AJAX Error Contract — `IMPLEMENTED`

`admin/ajax/dashboard-data.php` is implemented as a JSON-only authenticated read endpoint.

## Success

HTTP 200:

```json
{
  "success": true,
  "generated_at": "ISO-8601 timestamp",
  "data": {}
}
```

## Authentication failure

HTTP 401:

```json
{
  "success": false,
  "code": "AUTH_REQUIRED",
  "message": "Administrator login is required."
}
```

## Wrong method

HTTP 405:

```json
{
  "success": false,
  "code": "METHOD_NOT_ALLOWED",
  "message": "Dashboard data requires GET."
}
```

Also send `Allow: GET`.

## Unexpected server/data failure

HTTP 500:

```json
{
  "success": false,
  "code": "DASHBOARD_DATA_ERROR",
  "message": "Dashboard data could not be refreshed."
}
```

Server log may include internal exception class/message and an error reference.

Never return:

- SQL
- file paths
- stack trace
- secrets
- private keys
- raw config credentials

---

# 11. Dashboard Client-Side Error Handling — `REQUIRED`

The JS controller must handle:

- HTTP 401 → session-expired state / login path
- HTTP 405/4xx → stop inappropriate retry loop
- HTTP 500 → keep last good data + mark stale
- network timeout/failure → stale indicator + backoff
- invalid JSON/schema → treat as refresh failure
- aborted request → not reported as an application error
- duplicate/in-flight refresh → skip/abort safely

## UX rule

A refresh failure must **not**:

- blank the dashboard
- show fake zero values
- destroy charts
- reload the page repeatedly
- expose internal error messages

---

# 12. Logging Requirements — `REQUIRED`

For Dashboard AJAX:

- log unexpected server exceptions
- include a request/error reference where useful
- do not log secrets
- do not log complete license/API credentials
- avoid logging successful 15-second polling requests unless diagnostics justify it

---

# 13. Error Test Matrix

Required automated cases:

| Case | Expected |
|---|---|
| unauthenticated GET | 401 JSON |
| POST to read endpoint | 405 + `Allow: GET` |
| DB/query exception | generic 500 JSON; internal log only |
| malformed internal response | client marks stale |
| network failure | client retains last valid state |
| repeated failures | backoff applies |
| recovery after failure | fresh state returns, backoff resets |
| concurrent refresh attempt | one in-flight request maximum |
| secret scanning | no secret/private-key fields |
| production error display | no stack/SQL/path leakage |

---

# 14. Deferred Global Hardening Candidates

Not automatically included in the Dashboard two-phase update:

- normalize all admin action errors to typed/sanitized domain errors
- top-level structured cron exit codes
- cron heartbeat persistence
- legacy API v1 machine-code standardization
- legacy compatibility endpoint redesign

These require separate explicit approval if they would enlarge scope.
