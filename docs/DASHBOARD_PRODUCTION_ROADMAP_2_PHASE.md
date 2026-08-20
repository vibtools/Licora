# Licora Dashboard Production Update — 2-Phase Roadmap

## Program Status

- Baseline: `v5.5.1`
- Planned phases: `2`
- Completed phases: `0`
- Remaining phases: `2`
- Runtime implementation: `PHASE 1 IMPLEMENTED — LOCAL PASS; DB/CI GATE PENDING`
- Target version: `v5.6.0`

No phase may be marked complete until its acceptance gates pass.

---

# Phase 1 — Data Truth, Backend Read Model & Error Contract

**Goal:** Dashboard-এর data semantics trustworthy করা এবং full reload ছাড়া data পাওয়ার জন্য secure read-only backend foundation তৈরি করা।

## P1-001 — Freeze Existing Contracts

Preserve:

- Authentication/authorization behavior
- License generation/verification behavior
- API v1 response contracts
- Secure API v2 protocol/crypto contracts
- Updater signing/state-machine behavior
- Existing cron mutation responsibility
- Existing database schema unless explicitly approved
- Existing shared UI system

**Acceptance:** regression tests show no unintended contract change.

## P1-002 — Define Dashboard Read Model

Create one centralized dashboard read layer rather than keeping duplicated SQL inside the page.

Planned responsibility:

- license summary
- device summary
- API activity summary
- expiration timeline
- recent tracked activity
- top license usage
- measured health/config facts
- generated timestamp

**Rule:** read model must not write/update/delete data.

## P1-003 — Truthful License Metrics

Required fields:

- total licenses
- active licenses
- expired licenses
- suspended licenses
- expiring soon count

Definitions must be SQL/test documented.

## P1-004 — Truthful Device Metrics

Do not call a metric “live” unless recency is measured.

Planned reporting distinction:

- total device records
- currently active-flagged records
- recently seen device records

For Secure API v2, `v2_device_credentials.last_seen_at` must be considered where available.

No license/device enforcement semantics may change merely to improve reporting.

## P1-005 — API Activity Semantics

Dashboard must stop implying `api_logs` equals all API traffic.

Recommended production representation:

- API v1 tracked verification activity (`api_logs`)
- Secure API v2 activity (`v2_audit_logs`)
- clearly named combined/summary view only if semantics are normalized
- legacy `/api/check_license.php` must not be silently counted unless explicit logging is implemented and tested

Labels must state what is actually measured.

## P1-006 — Expiration Semantics

Replace ambiguous `Expired Trend`.

Recommended contract:

- `expired_last_30_days`
- `expiring_next_30_days`

UI may display one combined `Expiration Timeline` chart with clearly separate datasets, or two summaries.

## P1-007 — Measured Health Facts

Remove hardcoded truth claims.

Allowed baseline-derived checks include:

- database probe
- PHP version
- environment
- config-local presence
- required directory/file availability
- API v2 schema/key readiness when it can be measured safely

`Cron Running` must not be shown unless a real heartbeat exists. Without heartbeat, use `Cron Scripts Available` or omit the claim.

## P1-008 — Authenticated Read-Only AJAX Endpoint

Planned endpoint:

`admin/ajax/dashboard-data.php`

Required behavior:

- GET only
- admin authentication required
- JSON only
- `Cache-Control: no-store`
- no DB mutation
- no secrets
- no raw stack trace
- no SQL exception text
- stable structured error contract

Proposed success shape is defined in `docs/DASHBOARD_DATA_CONTRACT.md`.

## P1-009 — Dashboard Error Contract

Required codes at minimum:

- `AUTH_REQUIRED` → 401
- `METHOD_NOT_ALLOWED` → 405
- `DASHBOARD_DATA_ERROR` → 500 generic message

Internal exception details go to server log only.

## P1-010 — Phase 1 Automated Tests

Add tests for:

- endpoint method restriction
- auth restriction
- response schema
- no secret fields
- truthful metric labels/contract
- API v1/v2 source separation
- expiration split
- read-only behavior
- DB-backed query behavior

Recommended new tests:

- `tests/dashboard_data_contract.php`
- `tests/dashboard_db_integration.php`

Integrate into `scripts/verify-local.py` and MySQL CI gate where applicable.

## P1 Acceptance Gate

Phase 1 may be marked `COMPLETE` only when:

- all P1 items are implemented
- local static suite passes
- DB integration passes in CI/test DB
- existing API/license/updater tests pass
- endpoint has no mutation path
- docs/log ledger updated

---

# Phase 2 — Compact UI, Reload-Free Refresh & Production Gate

**Goal:** Phase 1 backend contract ব্যবহার করে professional compact dashboard তৈরি করা এবং full-page reload সরিয়ে reliable in-place refresh চালু করা।

## P2-001 — Dashboard Composition Redesign

Retain current Licora Light design system.

Target hierarchy:

### Header
- Dashboard title
- `Last updated`
- manual Refresh
- primary CTA only where justified

### Primary KPI row
Recommended:
- Total / Active Licenses
- Recently Seen Devices
- API Activity
- Expiring Soon

### Secondary compact strip
- Expired
- Suspended
- API Keys / API source context
- Environment
- measured system facts

No fake status.

## P2-002 — Compact Quick Actions

Remove oversized duplicate tile composition.

Use compact actions:
- Create License
- API Keys
- Devices
- optional More menu

Audit/Backup/Health remain available through sidebar navigation.

## P2-003 — Analytics Layout

Replace three equal cramped panels with stronger hierarchy:

- primary wide API activity chart
- secondary expiration insight
- top-used licenses compact panel or integrated secondary row

No page-specific stylesheet.

## P2-004 — Dedicated Dashboard JS Controller

Recommended file:

`admin/assets/js/dashboard.js`

Responsibilities:

- fetch dashboard JSON
- update KPI DOM
- update charts in-place
- update recent activity
- update health facts
- update last-refresh timestamp
- handle stale/error states
- manual refresh
- polling lifecycle

Do not put business/database logic in JavaScript.

## P2-005 — Remove Full Page Reload

Delete the baseline behavior:

`window.location.reload()` after 30 seconds.

No automatic full-document refresh should remain for dashboard data synchronization.

## P2-006 — Polling Policy

Production-safe default:

- visible dashboard: refresh approximately every 15 seconds
- hidden tab: pause or reduce frequency
- only one request in flight
- failure backoff: approximately 15s → 30s → 60s
- successful request resets backoff
- manual refresh allowed
- page unload cancels active request where practical

Do not use aggressive sub-second polling.

## P2-007 — In-Place Chart Updates

Reuse Chart.js instances.

Update:

- labels
- datasets
- chart state

Do not destroy/recreate the full page.

## P2-008 — Stale / Error UX

When refresh fails:

- keep last known valid values
- clearly mark data as stale
- show non-blocking feedback
- do not replace valid values with fake zeros
- do not expose internal exception details
- manual Retry/Refresh remains possible

## P2-009 — Accessibility

Required:

- refresh button accessible name
- keyboard usability
- visible focus
- `aria-live` for refresh/error status where appropriate
- status not color-only
- responsive layout without horizontal viewport overflow

## P2-010 — Phase 2 Tests

Recommended:

- `tests/dashboard_ui_contract.php`
- `tests/dashboard_browser_runtime.js`
- extend component/route contracts where appropriate

Runtime test cases:

- initial render
- successful refresh
- chart update
- recent activity update
- failed refresh / stale state
- retry
- no overlapping requests
- no `window.location.reload`
- hidden-tab policy
- manual refresh
- accessible status changes

## P2-011 — Full Regression Gate

Must pass:

- PHP 8.0–8.4 validation
- security smoke
- compatibility regression
- installer smoke
- API v1 freeze
- API v2 static/crypto/DB
- updater static/manifest/state/DB/browser tests
- UI contracts
- dashboard tests
- MySQL integration
- `git diff --check`

## P2-012 — Manual Production Smoke

Verify in disposable deployment:

- login/session timeout/logout
- dashboard initial render
- API v1 activity reflected correctly
- API v2 activity reflected correctly
- device activity semantics
- license expiry semantics
- cron-driven changes reflected after refresh
- manual refresh
- failed AJAX request behavior
- slow-network behavior
- mobile sidebar/dashboard
- charts
- no secret/internal error leakage

## P2-013 — Documentation Finalization

Update:

- phase log
- implementation ledger
- error handling matrix
- release notes
- changelog
- relevant feature matrix
- data contract if implementation differs only through an explicitly approved decision

## P2 Acceptance Gate

Phase 2 is complete only when all automated/manual gates pass and the phase ledger records the exact verified state.

---

# Final Program Completion Definition

The two-phase program is complete only when:

- Phase 1 = `COMPLETE + VERIFIED`
- Phase 2 = `COMPLETE + VERIFIED`
- Dashboard no longer auto-reloads for data refresh
- metrics are truthfully named
- no hardcoded fake health claim remains
- AJAX endpoint is authenticated/read-only
- stale/error state is safe
- tests/CI pass
- documentation matches actual source

Anything not verified remains `PENDING` or `UNKNOWN`.
