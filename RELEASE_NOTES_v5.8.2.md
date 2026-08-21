# Licora v5.8.2 — Global Device Icon Compatibility Hotfix

Licora v5.8.2 is a no-migration UI compatibility hotfix over the published v5.8.1 release.

## Fixed

- Replaces every remaining runtime `bi-devices` reference with `bi-laptop`, which is available in the pinned Bootstrap Icons 1.8.1 dependency.
- Restores the device glyph in the shared sidebar, Settings shortcut, Device Management page header and empty state, License **View Devices** action, Backup **Devices CSV** export, and About **Device Control** capability card.
- Adds a shared UI regression gate that rejects `bi-devices` anywhere in Admin runtime PHP/JavaScript/HTML sources so the unsupported glyph cannot silently return.

## Compatibility

- Upgrade source: `5.8.1`.
- Database migrations: none.
- Deleted files: none.
- API v1/v2 behavior: unchanged.
- License/device enforcement: unchanged.
- Authentication/roles: unchanged.
- Dashboard data, AJAX refresh and charts: unchanged.
- Developer Guide and downloadable API v2 examples: unchanged.
- Cron behavior and updater runtime/protocol: unchanged.
- Sidebar/navigation structure: unchanged; only the Devices glyph class changes.
