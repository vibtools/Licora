# Licora v5.8.4 Frozen Baseline

## Parent

- Development parent: frozen Licora v5.8.3 Admin License Control API source at `f706b870f798a5a4206ff0734c217677a2ecb6f0`.
- Last published release: v5.8.2.
- Target release: v5.8.4.

## Approved delta

- Publish the scoped Admin License Control API and its additive schema from the unpublished v5.8.3 source baseline.
- Add the optional `SDK/python` Secure API v2 client package.
- Add public configuration, examples, documentation and tests.
- Add the compact public license guide and Vib Tools support information.
- Add verified Python and Admin SDK assets to GitHub Releases without changing updater-facing asset semantics.
- Align source, installer, updater and release identity to v5.8.4.

## Compatibility bridge

The signed release accepts v5.8.2 and v5.8.3. It includes the existing additive, idempotent `v5.8.3.scoped-admin-license-api` migration so the last published v5.8.2 can update directly. A v5.8.3 source installation with the same recorded migration ID and checksum safely skips it. No file is deleted.

## Frozen behavior

API v1/v2 client contracts, license/device rules, authentication/authorization, Dashboard, Cron and updater runtime/protocol remain unchanged. SDK assets are independent downloads and are not added to the signed website update manifest.
