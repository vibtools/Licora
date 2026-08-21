# Licora v5.7.1 — Dashboard Phase 2 Corrective Source Freeze

## Parent Authority

- Official parent baseline: `Licora_v5.7.0_Baseline.zip`
- Parent baseline SHA-256: `e198fda3a90f38ef0d15faeab3f0b2797b92ba98b542cb7f22ac8f01b3bda022`
- Parent embedded Git HEAD: `4b430b77ccc303aebeadc2852bebd3f11f67452a`
- Parent source version: `5.7.0`
- Parent GitHub tag status at audit time: `NOT PUBLISHED`
- Parent Phase 2 state: source implemented + locally verified; remote/live acceptance pending

The uploaded v5.7.0 archive is immutable forensic authority. v5.7.1 is produced only in an isolated work copy.

## Corrective Scope Lock

Only the latest Phase 2 verification/fix scope is changed:

1. preserve `Retry` after failed-refresh cleanup;
2. preserve the 401/session-expiry refresh lock and `Refresh paused` UI;
3. capture synchronous request transport failures in the normal stale/error lifecycle;
4. advance `lastSuccessAt` only after successful render completion;
5. extend Dashboard browser/runtime tests for those four cases;
6. align current source/release/docs identity to `5.7.1`.

## Frozen / Unchanged

- Dashboard read model and authenticated JSON endpoint
- database schema and migration set
- API v1/v2 request/response/auth/crypto contracts
- license/device state/enforcement
- authentication/roles/session policy
- Cron mutation behavior
- updater protocol/signing/rollback/state machine
- shared sidebar/topbar and non-Dashboard admin UI
- Phase 2 layout, KPI definitions, chart semantics, polling cadence and existing navigation routes

## Chrome/Browser Finding

No Chrome launcher/downloader implementation exists in the v5.7.0 baseline application runtime. No browser-specific downloader is introduced in v5.7.1.

## Acceptance State

The full `python3 scripts/verify-local.py` gate passed after corrective source/tests/version/documentation alignment. Remote CI/MySQL and manual live/staging UI smoke remain required before Phase 2 is marked final `COMPLETE + VERIFIED`.

## Release Compatibility

The v5.7.1 signed update specification accepts `5.6.1` and `5.7.0`, with no migrations and no delete list. This allows direct upgrade from the published v5.6.1 release while also supporting installations where the v5.7.0 source baseline was already applied.

## Source Freeze Packaging

The v5.7.1 source baseline freeze is packaged from the verified work tree with repository `.git` metadata excluded. This avoids treating an uncommitted local Git object database as product source. The external baseline ZIP SHA-256 is recorded alongside the delivered artifact; the authoritative Git commit SHA will be established only after the user performs the reviewed commit/push flow.
