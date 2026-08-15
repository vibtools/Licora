# Licora v5.5.0 — VibTools Compact Light UI, Settings Truthfulness & Licora Branding

**Release type:** Minor UI/UX architecture release
**Stable base:** `v5.4.1`
**Database migration:** None
**API v1/v2 contracts:** Unchanged
**License/device/cron contracts:** Unchanged

## Summary

Licora v5.5.0 completes the compact presentation pass that follows the v5.4.0 component-shell migration. It aligns tables, forms, toolbars, row actions, confirmations, scrolling and page density with the audited VibTools Web UI v2.1.2 compact structure while retaining Licora's light theme and existing PHP application architecture.

The release also makes the Settings page truthful: only settings that are actually consumed by the current runtime remain editable. Stored legacy keys are preserved in the database for compatibility but are not presented as active controls. API/runtime endpoints and Cron commands are displayed as read-only operational information.

## UI and branding

- Removes redundant page, section and card descriptions from normal administration surfaces while retaining functional alerts, errors, security warnings and status information.
- Standardizes compact inputs, buttons, tables, table toolbars, action menus, scrollbars and confirmation/feedback components.
- Rebuilds Licenses as a full-width table with one compact toolbar and a responsive Single/Bulk Create License modal while preserving the existing form names, CSRF fields and backend actions.
- Rebuilds Devices as a compact searchable/filterable table with compact metadata, copyable/truncated device hashes and shared row actions.
- Adds compact table/search/filter patterns to API Keys, V2 Devices, Admins, Audit and other data-heavy administration surfaces.
- Adds supplied Licora logos, icons and favicons to the application shell, login, root landing page, installer and About page.
- Adds **About Licora** using verified project metadata only.
- Visible product UI is English-only.

## Settings truthfulness

The Admin Settings UI now exposes only runtime-backed editable controls:

- Default License Hours
- Default Device Limit
- Minimum License Hours
- Maximum License Hours
- Log Retention Days

Legacy stored-only settings are not deleted. Runtime/API endpoints, runtime limits, environment/version data and Cron CLI commands are shown read-only with copy controls.

Secure API v2 key information is limited to status, key ID, public-key fingerprint and an authenticated Super-Admin public-key PEM download. The server private signing key is never displayed or downloadable.

## Updater/UI integration

- Manual **Check for Updates** now reports a clear up-to-date or update-available success message.
- Release-note and compact scroll areas use the Licora light scrollbar treatment.
- Existing v5.4.1 updater state machine, signing, manifest validation, staging, rollback, job/event persistence and security contracts are unchanged.
- Existing v5.4.1 custom updater confirmation behavior remains preserved; remaining native admin `confirm()` usage is removed from production UI.

## Verification portability

`tests/updater_builder_contract.py` now launches the manifest builder using the current Python interpreter (`sys.executable`) instead of assuming the Unix-only `python3` command exists. CI adds a Windows Python contract job to protect this path.

## Compatibility

```json
"upgrade_from": ["5.4.1"],
"migrations": [],
"delete_files": []
```

There is no database migration. API v1, Secure API v2, license validation/generation, device-limit behavior, cron behavior, authentication/authorization and updater protocol/state-machine behavior remain unchanged.
