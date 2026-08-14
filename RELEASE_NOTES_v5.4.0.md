# Licora v5.4.0 — VibTools Light Component UI

**Release type:** Minor UI architecture release
**Stable base:** `v5.3.0`
**Database migration:** None
**API v1/v2 contracts:** Unchanged
**Updater protocol:** Unchanged

## Summary

Licora v5.4.0 replaces the classic horizontal admin navigation with a reusable left-sidebar application shell and migrates the full Licora presentation layer to a centralized component system based on the audited **VibTools Web UI v2.1.2** structural and typography rules. Licora's existing light blue/purple product identity is retained; the VibTools dark Sandbox theme is not applied to Licora.

## Added

- Shared fixed desktop sidebar and responsive mobile off-canvas navigation.
- Compact utility topbar with page context; primary navigation is sidebar-only.
- Central Licora Light semantic theme mapped over the existing VibTools v2.1.2 foundation.
- Reusable application-shell, card, button, form, table, badge, alert, modal, pagination, toolbar, empty-state, loading, authentication and installer component styling.
- Shared PHP navigation/sidebar/topbar presentation components while retaining `admin/includes/navbar.php` as a compatibility include.
- Reusable sidebar JavaScript controller with mobile backdrop/Escape/navigation close behavior.
- Unified light presentation for Dashboard, Licenses, Devices, Logs, API Keys, Client Apps, V2 Devices, Updates, Settings, Admins, Audit, Backup and Health pages.
- Unified light authentication and first-run installer presentation.
- Light VibTools adaptation of the existing real-time updater log modal.
- `docs/UI_DESIGN_SYSTEM.md` as the authoritative future Licora UI development contract.
- UI route, form/DOM, component and updater presentation regression tests.

## Preserved

- All existing admin routes and primary menu destinations.
- Super Admin visibility restriction for Updates.
- Update-available navigation badge hook.
- License creation, expiry, device-limit, API binding and API v2 client-app scope behavior.
- Existing form field names, CSRF fields, action URLs and JavaScript hooks.
- API v1 and Secure API v2 implementations.
- Cron behavior and database schema.
- Secure updater signing, manifest, preflight, staging, backup, migration ledger, resumable apply, rollback and event/job behavior.

## Removed from the presentation layer

- Classic horizontal primary-menu navbar.
- Tailwind CDN runtime dependency from migrated Licora admin pages.
- Page-level `<style>` blocks in migrated admin/login/installer surfaces.
- The old dark Update Center island; updater functionality is unchanged and now uses the common Licora Light component system.

## Upgrade

v5.4.0 is designed to be installed by an existing v5.3.0 deployment through **Admin → Updates**. The signed release manifest declares:

```json
"upgrade_from": ["5.3.0"],
"migrations": []
```

No database migration is required.
