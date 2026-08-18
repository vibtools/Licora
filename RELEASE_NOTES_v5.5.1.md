# Licora v5.5.1 — Settings Layout and About UI Hotfix

**Release type:** Corrective UI/UX patch
**Stable base:** v5.5.0
**Database migration:** None
**API v1/v2 contracts:** Unchanged
**License/device/cron contracts:** Unchanged
**Updater protocol/state machine:** Unchanged

## Summary

Licora v5.5.1 closes four presentation defects identified after the v5.5.0 compact UI release. It keeps the existing VibTools Light component architecture and changes only shared presentation composition, navigation interaction, release metadata and regression coverage.

## Fixed

- Settings management shortcuts now use an equal-width responsive grid instead of clustering small buttons at the left edge.
- The Settings lower section now places `API & Integration` in the primary column and stacks `Cron Jobs` with `API v2 Signing` in the secondary column, removing the large blank Cron card area.
- The Settings child navigation is now a keyboard-accessible collapsible submenu. Child pages automatically open the submenu; other pages keep it collapsed by default.
- `About Licora` is rebuilt with a balanced product hero, verified core-capability cards, Vib Tools company information and compact project/release metadata.

## Security and compatibility

The API v2 private signing key remains server-only and is neither displayed nor downloadable. The existing public-key status, fingerprint and download path remain unchanged. No database table/column/data migration is included. Existing license, device, API, authentication, role, cron and updater behavior is unchanged.

## Upgrade

The signed v5.5.1 release accepts exactly the official `v5.5.0` source as its direct upgrade baseline. No files are deleted and no migration is executed.
