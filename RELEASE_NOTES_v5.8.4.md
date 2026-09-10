# Licora v5.8.4 — Admin Automation and Production SDKs

Licora v5.8.4 is the final combined release over the last published v5.8.2 baseline. It adds the scoped Admin License Control API, the production Python Secure API v2 client SDK, a compact public license guide and independently downloadable developer SDK packages.

## Added

- Dedicated, least-privilege Admin API keys with application assignment, granular scopes, lifecycle controls, CIDR allowlists, request limits and expiry.
- Replay-resistant HMAC-SHA256 Admin API requests, idempotent external-order license creation, license lifecycle operations and device revocation.
- Admin API documentation plus ready-to-use PHP and Node.js integration SDKs.
- Super-admin-only, CSRF-protected Admin API database initialization from the Admin UI for container deployments without a MySQL CLI.
- Python 3.10+ Secure API v2 SDK with P-256 device proofs, pinned RS256 token verification, OS-backed persistence, async helpers and background validation.
- Public Vib Tools license landing page covering activation, responsible use, device changes, sharing restrictions and support contacts.
- GitHub Release downloads for the Python SDK source, Python wheel/sdist, Admin API SDK and a SHA-256 SDK checksum manifest.

## Compatibility and upgrade

- Direct signed sources: v5.8.2 and v5.8.3.
- v5.8.2 installations apply `migration-v5.8.3-admin-license-api.sql`.
- v5.8.3 source installations safely skip the same already-recorded idempotent migration.
- Deleted files: none.
- Existing API v1/v2 client contracts, license/device enforcement, authentication/roles, Dashboard, Cron and updater runtime protocol remain compatible.

The Python client SDK contains no API v1 key, Admin API key or private server signing key. Admin SDK secrets remain server-side environment configuration and are never shipped as embedded credentials.
