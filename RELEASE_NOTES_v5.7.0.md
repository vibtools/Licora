# Licora v5.7.0 — Compact Dashboard & Reload-Free Refresh

**Release type:** Dashboard Phase 2 UI/runtime release candidate
**Official parent baseline:** v5.6.1 / `4b430b77ccc303aebeadc2852bebd3f11f67452a`
**Database migration:** None
**Deleted files:** None
**External API v1/v2 contracts:** Unchanged
**License/device enforcement:** Unchanged
**Authentication/roles:** Unchanged
**Cron mutation behavior:** Unchanged
**Updater protocol/state machine:** Unchanged
**Shared sidebar/topbar:** Unchanged

## Dashboard Phase 2

v5.7.0 keeps the verified v5.6.1 Dashboard read model/error contract and changes only the approved Dashboard presentation/browser interaction layer.

### Changed

- Rebuilt `admin/index.php` as a compact operations Dashboard while retaining server-rendered initial content.
- Removed the former 30-second `window.location.reload()` behavior.
- Added authenticated 30-second AJAX refresh through the existing GET-only `admin/ajax/dashboard-data.php` endpoint.
- Added manual Refresh and a last-successful-update indicator.
- Updates license/device KPIs, health facts, API/expiration charts, recent activity and top API v1 licenses in place.
- Combines recent API v1 calls and API v2 audit events in one source-labelled operational activity view without changing the backend source distinction.
- Replaces large Quick Action tiles with compact links to existing admin routes.

### Error and session behavior

- Prevents overlapping poll/manual requests with an in-flight lock.
- Refresh failures preserve the last successfully rendered data and show a stale-data/Retry state instead of replacing values with zero or blank content.
- An endpoint `401 AUTH_REQUIRED` pauses polling and surfaces the existing sign-in path.
- No backend exception/credential/key detail is exposed by the browser controller.

### Accessibility and responsive behavior

- Refresh is a real keyboard-accessible button.
- Refresh/stale/auth feedback uses an ARIA live status region.
- System facts include visible text in addition to color indicators.
- Dashboard layout collapses for tablet/mobile while the existing shared shell remains unchanged.
- Refresh animation respects `prefers-reduced-motion`.

## Verification added

- `tests/dashboard_phase2_contract.php` verifies Dashboard DOM/scope/runtime contract and the frozen Phase 1 endpoint boundary.
- `tests/dashboard_browser_runtime.js` verifies polling cadence, manual refresh, overlap prevention, successful rendering lifecycle, stale preservation and 401 polling shutdown.
- Existing Dashboard data/DB, API, installer, security, updater and UI regression gates remain required.

## Compatibility

v5.7.0 declares an empty migration list and empty delete list. The signed update source is the frozen official `v5.6.1` baseline. No new database table/column, external API response/request field, license/device state transition, auth policy, Cron write behavior or updater protocol is introduced.
