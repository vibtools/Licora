# Licora Actual Implementation Ledger

This ledger preserves the original v5.5.1 state and tracks the current uploaded v5.6.0 baseline plus the v5.6.1 corrective candidate.

Never mark a feature `ACTUAL WORKING` from roadmap text alone.

Status:
- `ACTUAL WORKING`
- `PARTIAL`
- `NOT IMPLEMENTED`
- `IMPLEMENTED / DB GATE PENDING`
- `PLANNED P1`
- `PLANNED P2`
- `DEFERRED`

---

# A. Original v5.5.1 Baseline — Actual Working

| Area | Status | Actual behavior |
|---|---|---|
| Admin authentication | ACTUAL WORKING | Protected admin pages require login |
| Session inactivity timeout | ACTUAL WORKING | 30-minute timeout enforced in `Auth::isAdminLoggedIn()` |
| Shared sidebar/topbar | ACTUAL WORKING | Centralized Licora Light admin shell |
| Settings collapsible submenu | ACTUAL WORKING | v5.5.1 accessible submenu behavior |
| About page corrective UI | ACTUAL WORKING | v5.5.1 product/company composition |
| Dashboard server-side render | ACTUAL WORKING | PHP queries DB and renders cards/charts/table |
| Dashboard automatic refresh | PARTIAL | Full page reload every 30 seconds |
| Dashboard AJAX refresh | NOT IMPLEMENTED | No generic dashboard data endpoint |
| Dashboard manual refresh | NOT IMPLEMENTED | No dedicated data refresh control |
| Dashboard last-updated indicator | NOT IMPLEMENTED | No explicit timestamp UI |
| Daily API chart | PARTIAL | Reads `api_logs`, not all API traffic |
| API v2 dashboard analytics | NOT IMPLEMENTED | `v2_audit_logs` not included in dashboard |
| Legacy `/api/check_license.php` analytics | NOT IMPLEMENTED | Requests are not inserted into `api_logs` |
| Expiration chart | PARTIAL | ±30-day window mislabeled `Expired Trend` |
| Active-device KPI | PARTIAL | Counts `devices.is_active`; real recency may differ |
| System `Live` KPI | PARTIAL/MISLEADING | Represents production environment, not service health |
| Security status | NOT REAL TELEMETRY | Hardcoded `Active` |
| API server status | NOT REAL TELEMETRY | Hardcoded `Running` |
| System Health page DB probe | ACTUAL WORKING | Executes `SELECT 1` |
| Generic admin table search/pagination | ACTUAL WORKING | Client-side over already-loaded rows |
| Update Center live AJAX | ACTUAL WORKING | Status/events/steps update without page reload |
| Secure API v2 | ACTUAL WORKING | Separate endpoints, crypto, DB/audit model |
| API v1 authenticated verify | ACTUAL WORKING | API-key/Bearer path |
| Legacy compatibility endpoint | ACTUAL WORKING/PARTIAL SECURITY | Intentionally unauthenticated for compatibility |
| Cron cleanup | ACTUAL WORKING when scheduled | External scheduler required |
| Expiring-license cron checker | PARTIAL | CLI report only; no email engine |
| Production error display policy | ACTUAL WORKING | Errors hidden, logging enabled |
| Updater structured errors | ACTUAL WORKING | Typed codes + safe JSON |
| API v2 structured errors | ACTUAL WORKING | Typed codes + safe JSON |

---

# A2. Current v5.6.0 Corrective Baseline

- Uploaded baseline SHA-256: `ba99c6e4fd74c2b59d392c3010b3aefe493390b3b0b0c94bd3c211218f14d597`
- Git commit: `5c685636e955422bc70e3bf07694f55d9c7fb1dc`
- Phase 1 source is present.
- PR #8 CI reached the MySQL gate and failed only when the new Dashboard DB fixture attempted an FK-invalid table drop.
- v5.6.1 corrective candidate fixes the DB fixture, declared top-level `recent_activity` response parity, and API v2 signing-key-pair readiness.
- Phase 2 remains not implemented.

# B. After Phase 1 — Expected Verified Working State

Do not move items here to `ACTUAL WORKING` until tests pass.

| Feature | Current | Target after P1 |
|---|---|---|
| Central dashboard read model | NOT IMPLEMENTED | IMPLEMENTED / DB GATE PENDING |
| Read-only Dashboard AJAX endpoint | NOT IMPLEMENTED | IMPLEMENTED / DB GATE PENDING |
| Structured dashboard JSON errors | NOT IMPLEMENTED | IMPLEMENTED / DB GATE PENDING |
| Truthful expiration datasets | PARTIAL | IMPLEMENTED / DB GATE PENDING |
| API v1/v2 analytics distinction | NOT IMPLEMENTED | IMPLEMENTED / DB GATE PENDING |
| Truthful device recency metric | PARTIAL | IMPLEMENTED / DB GATE PENDING |
| Measured health/config facts | PARTIAL | IMPLEMENTED / DB GATE PENDING |
| Dashboard DB integration test | NOT IMPLEMENTED | IMPLEMENTED; local execution pending dedicated MySQL |

### Phase 1 actual additions

Source/static verification confirms the Phase 1 implementation exists. PR #8 run `32420291770` reached the mandatory disposable-MySQL gate and exposed a test-fixture FK cleanup defect. v5.6.1 corrects that defect plus the top-level `recent_activity` and API v2 key-pair readiness mismatches. Final promotion to `ACTUAL WORKING` remains pending a green corrected remote MySQL/CI run.

- `includes/dashboard.php` — centralized read-only model
- `admin/ajax/dashboard-data.php` — authenticated GET-only JSON endpoint
- corrected Dashboard labels and measured health facts
- API v1/v2 tracked activity separation
- expiration past/future separation
- five-minute device-recency reporting with v2 fallback/aggregation
- dashboard contract and DB integration tests
- CI/tagged-release DB-gate wiring
- v5.6.1 FK-safe Dashboard DB fixture cleanup
- v5.6.1 top-level source-separated `recent_activity` contract parity
- v5.6.1 API v2 `Ready` requires a matching private/public signing key pair

---

# C. After Phase 2 — Expected Verified Working State

| Feature | Current | Target after P2 |
|---|---|---|
| Compact dashboard hierarchy | PARTIAL | PLANNED P2 |
| Compact Quick Actions | NOT IMPLEMENTED | PLANNED P2 |
| Reload-free Dashboard polling | NOT IMPLEMENTED | PLANNED P2 |
| In-place KPI updates | NOT IMPLEMENTED | PLANNED P2 |
| In-place Chart.js updates | NOT IMPLEMENTED | PLANNED P2 |
| Recent activity partial refresh | NOT IMPLEMENTED | PLANNED P2 |
| Last updated indicator | NOT IMPLEMENTED | PLANNED P2 |
| Manual Refresh | NOT IMPLEMENTED | PLANNED P2 |
| Stale data indicator | NOT IMPLEMENTED | PLANNED P2 |
| Retry/backoff | NOT IMPLEMENTED | PLANNED P2 |
| No request overlap | NOT IMPLEMENTED | PLANNED P2 |
| Dashboard browser runtime tests | NOT IMPLEMENTED | PLANNED P2 |

### Phase 2 actual additions

`[EMPTY — fill after verified implementation]`

---

# D. Expected Remaining Items After This 2-Phase Program

Unless separately approved, these remain outside this dashboard update:

| Item | Status |
|---|---|
| WebSocket/SSE platform | DEFERRED |
| Full SPA conversion | DEFERRED |
| Expiry email notification engine | DEFERRED |
| Legacy `/api/check_license.php` authentication redesign | DEFERRED compatibility decision |
| Full offline frontend / local replacement for CDNs | DEFERRED |
| Cron heartbeat persistence | DEFERRED unless explicitly added |
| Global normalization of every admin error | DEFERRED |

---

# E. Source-vs-Documentation Conflict Ledger

## Session timeout

- Older feature documentation: says timeout is not enforced.
- Frozen v5.5.1 source: `Auth::isAdminLoggedIn()` enforces 30-minute inactivity timeout.
- Resolution: **ACTUAL SOURCE WINS**.
- Follow-up: stale documentation should be corrected in final documentation phase, not treated as a runtime bug.

## Dashboard `Live`

- UI wording: suggests system live health.
- Source: only checks `ENVIRONMENT === 'production'`.
- Resolution: classify as **misleading presentation**, not an actual health monitor.

---

# F. Ledger Update Rule

After every phase:

1. move only verified items to `ACTUAL WORKING`
2. record commit and test evidence
3. preserve failed/pending items
4. never erase historical state
5. update exact continuation pointer in phase log
