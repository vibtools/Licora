# Roadmap

## Dashboard production program (v5.6.x)

- **Phase 1 — Data Truth, Backend Read Model & Error Contract:** implemented in v5.6.0 and corrected in v5.6.1 after PR #8 exposed a MySQL fixture FK-order failure and contract/readiness mismatches; final remote DB/CI verification remains the release gate.
- **Phase 2 — Compact UI, Reload-Free Refresh & Production Gate:** pending; no Phase 2 polling/UI implementation is included in v5.6.1.


Roadmap items are proposals and must be implemented through reviewed, backward-compatible changes.

## Security hardening

- Correct Authorization Bearer parsing and remove secret-adjacent development logs.
- Replace unauthenticated AES-CBC storage with an authenticated encryption format and migration path.
- Move all destructive admin actions from query strings to POST requests.
- Enforce the existing session-timeout method consistently.
- Add explicit installer lock and secure first-admin creation.
- Add configurable security headers and a Content Security Policy.

## Correctness and configuration

- Wire stored maintenance, two-factor, timezone, and API-limit settings into runtime behavior.
- Reconcile the legacy simple verification endpoint with API-key and application-scope policy.
- Correct browser and operating-system detection order.
- Normalize database migrations and remove redundant indexes through a safe migration.

## Quality and operations

- Add disposable-database integration tests.
- Add API contract tests and admin authorization tests.
- Vendor or integrity-pin frontend assets for offline and supply-chain resilience.
- Add structured logs, rotation guidance, and health-check output suitable for monitoring.
- Add container and reverse-proxy examples without making containers mandatory.

## Completed operational foundation

- v5.3.0: signed, resumable Super Admin in-app Update Center with cPanel/VPS-safe no-shell common path, migration ledger, rollback protection, and VibTools live deployment logs.
- v5.4.0: VibTools Light component UI migration with sidebar navigation, centralized presentation components, responsive shell and frozen backend/API/updater contracts.
- v5.4.1: updater recovery and v5.3/v5.4 scope-integrity hotfix with browser DOM contract coverage and release/rollback hardening.
- v5.5.0: VibTools compact light UI refinement, truthful runtime-backed Settings, tracked Licora branding, About page and Windows builder-test portability; no database/API/updater protocol change.
- v5.5.1: Settings layout, collapsible Settings submenu, and professional About Licora UI hotfix; no database/API/updater behavior change.
