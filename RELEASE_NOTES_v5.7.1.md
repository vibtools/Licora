# Licora v5.7.1 — Dashboard Phase 2 Verification Fix

**Release type:** Corrective Dashboard Phase 2 verification release candidate
**Official source baseline:** `Licora_v5.7.0_Baseline.zip`
**Baseline ZIP SHA-256:** `e198fda3a90f38ef0d15faeab3f0b2797b92ba98b542cb7f22ac8f01b3bda022`
**Embedded Git HEAD:** `4b430b77ccc303aebeadc2852bebd3f11f67452a`
**Database migration:** None
**Deleted files:** None
**External API v1/v2 contracts:** Unchanged
**License/device enforcement:** Unchanged
**Authentication/roles:** Unchanged
**Cron mutation behavior:** Unchanged
**Updater protocol/state machine:** Unchanged
**Shared sidebar/topbar:** Unchanged

## Why v5.7.1 exists

The v5.7.0 Phase 2 source candidate passed the complete local verifier, but a manual forensic review of the Dashboard refresh lifecycle found four client-side state-management defects that were not covered by the original browser-runtime test.

### Fixed

1. **Stale refresh label preservation** — a failed refresh now keeps the reviewed `Retry` label after loading cleanup instead of being overwritten back to `Refresh`.
2. **401/auth lock preservation** — `AUTH_REQUIRED` now keeps manual refresh disabled and preserves `Refresh paused` after the request `finally` path runs.
3. **Synchronous transport failure recovery** — a synchronous request/transport exception is now captured by the normal Promise error path, releases the in-flight lock, clears loading state and surfaces stale data rather than escaping and leaving the Dashboard stuck.
4. **Last-success timestamp correctness** — `lastSuccessAt` now advances only after the new snapshot finishes rendering successfully. A render failure retains the prior successful timestamp.

The Dashboard browser-runtime regression test was expanded to exercise each corrective case.

## Phase 2 behavior retained

- compact operational Dashboard composition;
- server-rendered initial snapshot;
- authenticated 30-second AJAX polling through the unchanged Phase 1 endpoint;
- manual Refresh;
- in-place KPI, measured health, Chart.js, recent-activity and top-license updates;
- overlap protection;
- stale-data preservation;
- responsive/accessibility behavior.

## Browser portability finding

Licora is server-rendered PHP web software. The audited v5.7.0 baseline contains no Google Chrome executable launcher, Chrome installer/downloader, or Google Chrome download URL in the application runtime. The reported “Chrome unavailable → Chrome download failed” behavior therefore is not produced by this Licora source tree. No speculative browser downloader is added. The existing verifier guard against a Chrome-specific runtime dependency remains active.

## Update compatibility

`v5.7.0` is not present as a published GitHub tag on the audited repository at the time of this corrective preparation. The v5.7.1 release specification therefore accepts both:

- published `v5.6.1`; and
- an already-applied `v5.7.0` source baseline.

This corrective release has an empty migration list and empty delete list.

## Remaining acceptance gates

Source/local verification can be completed before publication. Phase 2 must not be marked final `COMPLETE + VERIFIED` until the authorized remote CI/MySQL matrix and the required desktop/tablet/mobile live or staging smoke are accepted.
