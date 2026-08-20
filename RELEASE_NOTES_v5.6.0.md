# Licora v5.6.0 — Dashboard Data Truth & Read Model

**Release type:** Backward-compatible dashboard data foundation release  
**Stable base:** v5.5.1  
**Database migration:** None  
**Deleted files:** None  
**API v1/v2 external contracts:** Unchanged  
**License/device enforcement:** Unchanged  
**Updater protocol/state machine:** Unchanged  
**Dashboard browser refresh model:** Existing 30-second full-page reload intentionally retained for Phase 2

## Summary

Licora v5.6.0 completes Phase 1 of the Dashboard production program. It replaces duplicated Dashboard SQL with one read-only data model, makes Dashboard metrics and labels match their real data sources, adds an authenticated read-only JSON endpoint for the next reload-free UI phase, and adds dedicated dashboard contract/MySQL validation.

## Added

- Central `DashboardReadModel` in `includes/dashboard.php`.
- Authenticated `GET /admin/ajax/dashboard-data.php` JSON endpoint.
- Stable dashboard endpoint errors: `AUTH_REQUIRED`, `METHOD_NOT_ALLOWED`, `DASHBOARD_DATA_ERROR`.
- Explicit license metrics for total, active, expired, suspended and expiring-soon licenses.
- Device reporting for total records, active-flagged records and five-minute recently-seen devices.
- Secure API v2 device-recency contribution through `v2_device_credentials.last_seen_at` when the v2 schema is available, without double-counting the same base device.
- Separate API v1 tracked verification activity (`api_logs`) and Secure API v2 audit activity (`v2_audit_logs`).
- Explicit `legacy_check_license_included=false` reporting because `/api/check_license.php` is not written to `api_logs`.
- Separate expired-last-30-days and expiring-next-30-days datasets.
- Measured Dashboard health/config facts for database access, PHP runtime, environment, config-local presence, Cron script availability and API v2 readiness.
- Dashboard data contract and MySQL integration tests; both CI and tagged release gates run the DB integration test.

## Corrected

- `System Status: Live` no longer presents the production environment as a service-health signal.
- Removed hardcoded `Security: Active` and `API Server: Running` Dashboard claims.
- `Daily API Requests` is now `Tracked API Activity`, with v1/v2 sources distinguished.
- `Expired Trend` is now `Expiration Timeline`, with past and future expiration datasets separated.
- `Top Used Licenses` and recent calls explicitly identify their API v1 verification source.
- Dashboard device presentation uses `Recently Seen Devices` instead of treating the persisted active flag as real-time presence.

## Compatibility

This release does not modify external API request/response contracts, license generation/verification, device authorization/revocation, authentication/roles, installer flow, Cron mutation behavior, database schema, updater signing, update state machine or existing shared UI architecture.

The Dashboard AJAX endpoint is internal/admin-only and read-only. It performs no `INSERT`, `UPDATE`, `DELETE`, migration or cleanup action.

## Phase boundary

Phase 1 intentionally does **not** add Dashboard polling JavaScript, manual refresh, stale-state UI, last-updated UI or compact Dashboard composition. The existing 30-second full-page reload remains in v5.6.0 and is the explicit continuation point for Phase 2.
