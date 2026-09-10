# Licora v5.8.4 Frozen Baseline

## Parent

- Parent release: frozen Licora v5.8.3 Admin License Control API baseline.
- Parent source head: `f706b870f798a5a4206ff0734c217677a2ecb6f0`.
- Target release: v5.8.4.

## Approved delta

- Add the optional `SDK/python` Secure API v2 client package.
- Add its public-only configuration template, examples, documentation and tests.
- Add Linux/Windows package-install and lifecycle verification to GitHub CI.
- Align source/installer/updater release identity to v5.8.4.

## Frozen behavior

API v1/v2 server endpoints, Admin License Control API, database schema/migrations, license/device rules, authentication/authorization, Admin UI, Dashboard, Cron and updater runtime/protocol remain unchanged. The v5.8.4 signed release specification accepts only v5.8.3, deletes no file and applies no migration.
