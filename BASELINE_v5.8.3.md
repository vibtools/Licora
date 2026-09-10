# Licora v5.8.3 Source Baseline Freeze

## Parent authority

- Frozen parent source: `v5.8.2` main head
- Parent commit: `fbdb20227a1daa7de1a62b07bd218d446abe2bd4`
- Target source version: `5.8.3`
- Release channel: stable candidate
- Accepted signed updater source: `5.8.2`
- Additive migration: `migration-v5.8.3-admin-license-api.sql`
- Delete files: none

## Authorized scope

1. Add a dedicated server-to-server Admin License Control API under `api/admin/v1/`.
2. Create and manage licenses only for exact, existing, active Secure API v2 client applications; do not add application creation/management endpoints.
3. Add dedicated hashed Admin API keys with granular scopes, exact app assignments, one-time secret display, rotation, suspension, permanent revocation, IP/CIDR allowlists, hourly rate limits and expiry.
4. Add request HMAC, timestamp/nonce replay protection, idempotent external-order creation, key ownership isolation and safe JSON errors/audit correlation.
5. Support owned-license read/list/activate/suspend/extend/ban/soft-delete and owned-device list/revoke. Ban/delete must revoke v2 credentials/refresh tokens and deactivate legacy device rows.
6. Add only the persistence tables required by this feature, an Admin Settings page, reference documentation, automated tests and v5.8.3 release plumbing.

## Frozen compatibility boundary

- API v1 endpoint files and shared runtime/security implementation retain their v5.8.2 Git blob identities.
- Public Secure API v2 endpoints, device-proof/token protocols, Client Apps workflow and existing licenses/devices remain unchanged.
- Existing Admin routes/forms, Dashboard, authentication/roles, Cron, updater runtime/protocol and historical release artifacts remain unchanged except additive navigation/integration visibility and current release identity.
- The Admin API never stores its raw secret and never accepts API v1 or API v2 client credentials.

This v5.8.3 source becomes the baseline for the next update only after repository verification and required GitHub CI pass.
