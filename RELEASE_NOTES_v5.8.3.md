# Licora v5.8.3 — Scoped Admin License Control API

Licora v5.8.3 adds a production-oriented server-to-server API for connecting an external order website to Licora license delivery without widening the public API v1/v2 client trust boundary.

## Added

- Dedicated Admin API keys, separate from existing API v1 verification keys and API v2 device credentials.
- Exact assignment to one or more existing active client applications.
- Granular license/device scopes and per-key active/suspended/revoked lifecycle.
- One-time secret display, immediate key rotation, optional IPv4/IPv6 CIDR allowlist, per-key hourly limit and expiry.
- HMAC-SHA256 request proofs over the exact method/target/body, 300-second timestamp window and persistent single-use nonces.
- Idempotent external-order license creation with conflict protection, customer reference, exact app scope, validity and device limit.
- Owned-license status/list/reveal/activate/suspend/extend/ban/soft-delete operations.
- Owned-device list/revoke operations; ban/delete revoke v2 credentials and refresh tokens and deactivate legacy devices.
- Admin Settings control page and latest-request audit view.
- Additive seven-table migration, fresh-install schema, API reference and PHP integration example.
- Static/security, UI and MySQL integration gates.

## Explicitly not added

- No Admin API endpoint creates, updates or deletes API v2 applications.
- No existing API v1/v2 request or credential format changes.
- No hard license deletion.
- No raw Admin API secret storage or later secret reveal.

## Upgrade

The signed updater accepts only v5.8.2 and applies `migration-v5.8.3-admin-license-api.sql`. The migration is additive/idempotent, alters no existing table and deletes no file. For a manual source upgrade, back up source/database, deploy the exact v5.8.3 source, apply the migration once and run `python3 scripts/verify-local.py` in a compatible environment.

## Configuration

- `ADMIN_API_REQUIRE_HTTPS=1` (default)
- `ADMIN_API_MAX_BODY_BYTES=65536` (default)
- `ADMIN_API_CLOCK_SKEW=300` (default)

Per-key policies are configured under **Admin → Settings → Admin License API**. See `docs/ADMIN_LICENSE_API.md`.

## Compatibility

API v1/v2 client behavior, existing application management, current license/device enforcement, authentication/roles, Dashboard, Cron and updater state machine remain compatible with the frozen v5.8.2 source.
