# Licora Dashboard Production Validation Gates

## Rule

A phase is not complete because code “looks correct”. It is complete only after defined gates pass.

---

# Gate 0 — Baseline Before Any Runtime Change

Required:

- clean Git state
- baseline commit recorded
- `python3 scripts/verify-local.py` passes in supported environment
- baseline version recorded
- no unrelated local modifications

Current frozen baseline:

`v5.5.1 / 2f48ef569e6c532ab0de974a418c644e4ea8423f`

---

# Phase 1 Gates

## Static Contract

- endpoint exists at approved route
- GET only
- auth required
- no secret fields
- no mutation SQL
- stable JSON envelope
- source labels for v1/v2 metrics
- expiration semantics correct
- health labels measured/truthful

## PHP Tests

Recommended:

- `tests/dashboard_data_contract.php`

Must assert:

- response-contract markers
- method/auth/error markers
- no dangerous secret/output markers
- no `INSERT|UPDATE|DELETE` in dashboard read layer/endpoint
- no hardcoded `Security Active` / `API Server Running`

## DB Integration

Recommended:

- `tests/dashboard_db_integration.php`

Seed a disposable test DB and verify:

- license counts
- expiration split
- device recency semantics
- API v1 activity source
- API v2 activity source
- no double-counting where combined
- recent activity order
- no DB mutation after read

## Regression

Must still pass:

- `security_smoke.php`
- `compatibility_regression.php`
- `installer_smoke.php`
- `api_v1_freeze.php`
- `api_v2_static.php`
- `api_v2_crypto.php`
- updater contracts

---

# Phase 2 Gates

## UI Contract

Recommended:

- `tests/dashboard_ui_contract.php`

Assert:

- no 30-second `window.location.reload`
- refresh button exists
- last-updated region exists
- stale/error region exists
- shared CSS only
- no page `<style>`
- chart containers remain stable
- compact action hierarchy

## Browser Runtime

Recommended:

- `tests/dashboard_browser_runtime.js`

Cases:

1. successful initial refresh
2. KPI DOM update
3. chart data update
4. recent activity update
5. last-updated timestamp update
6. failed request keeps old data
7. stale state appears
8. recovery clears stale state
9. repeated failures back off
10. no overlapping refresh request
11. hidden-tab policy
12. manual refresh
13. 401/session-expired behavior
14. invalid JSON/schema behavior

## Responsive Manual Check

At minimum:

- desktop
- tablet
- mobile-width
- sidebar open/close
- no viewport horizontal overflow
- charts remain readable
- status text not color-only

---

# Full Repository Gate

Before final release candidate:

- PHP 8.0
- PHP 8.1
- PHP 8.2
- PHP 8.3
- PHP 8.4
- Python verification
- Node runtime tests
- MySQL 8.4 integration
- Windows Python builder portability
- `git diff --check`
- package/release builder contract

---

# Manual Production Smoke Gate

Disposable production-like deployment:

## Authentication

- login
- session inactivity timeout
- logout

## Dashboard

- initial load
- manual refresh
- automatic partial refresh
- no full-page periodic reload
- last-updated timestamp
- stale state
- recovery
- slow network

## Data change sources

Generate/verify:

- license creation/state change
- API v1 verify activity
- API v2 activity
- device activity
- cron cleanup change
- expiration state

Confirm dashboard reflects each according to documented semantics.

## Error Safety

Simulate:

- DB unavailable
- endpoint 500
- network failure
- expired session

Confirm:

- no SQL
- no path
- no stack
- no secret
- no fake zeros replacing last valid data

---

# Phase Completion Evidence Template

```text
PHASE:
COMMIT:
TEST COMMAND:
STATIC RESULT:
DB RESULT:
BROWSER RESULT:
MANUAL RESULT:
KNOWN SKIPS:
KNOWN FAILURES:
DECISION: COMPLETE / INCOMPLETE
```

Any skipped critical test keeps the phase `INCOMPLETE` unless explicitly accepted and documented with rationale.
