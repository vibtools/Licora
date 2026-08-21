# Licora v5.7.0 — Phase 2 Source Candidate Freeze

## Parent Authority

- Official parent baseline: `Licora-5.6.1.zip`
- Parent version/tag: `v5.6.1`
- Parent Git commit: `4b430b77ccc303aebeadc2852bebd3f11f67452a`
- Parent release ZIP SHA-256: `0ca0ad76b5c0091912aa441fcac4c033a54bac630d6c1a7255ac5b2b75db5493`
- Parent release manifest source: `v5.6.1`

The parent baseline is immutable. v5.7.0 is produced in an isolated work copy and does not rewrite the v5.6.1 freeze.

## Candidate Identity

- Target version: `5.7.0`
- Scope: Dashboard Phase 2 only
- Database migration: `NONE`
- Deleted files: `NONE`
- External API contract change: `NONE`
- Dashboard backend/read-model contract change: `NONE`
- License/device enforcement change: `NONE`
- Authentication/role change: `NONE`
- Cron mutation change: `NONE`
- Updater protocol/state-machine change: `NONE`
- Shared sidebar/topbar redesign: `NONE`

## Phase 2 Runtime Scope

1. compact Dashboard operations composition;
2. measured system-status strip;
3. four primary truthful KPI cards;
4. dedicated `admin/assets/js/dashboard.js` browser controller;
5. authenticated 30-second AJAX refresh through the existing Phase 1 endpoint;
6. manual refresh and last-updated feedback;
7. in-place Chart.js/KPI/activity/top-license updates;
8. request-overlap protection;
9. stale-data preservation/Retry state and auth-expiry polling shutdown;
10. responsive/accessibility behavior and dedicated Phase 2 tests.

## Acceptance State

Current source-candidate evidence:

- full local verifier: `PASS`;
- targeted Phase 2 contract/browser-runtime tests: `PASS`;

The candidate must not be treated as a published baseline until the remaining required gates have evidence:

- required remote CI/MySQL matrix after an authorized push;
- manual desktop/tablet/mobile production/staging smoke;
- authorized merge/tag/release workflow.

The exact v5.7.0 commit SHA does not exist until the user commits the reviewed delta.
