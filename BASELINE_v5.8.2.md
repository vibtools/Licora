# Licora v5.8.2 Source Baseline Freeze

## Parent authority

- Published parent release: `v5.8.1`
- Parent commit: `f7b7fc2275a33a2aa29db5f464c2ffd0a6172045`
- Parent release ZIP: `Licora-5.8.1.zip`
- Parent release ZIP SHA-256: `710f2431f27bbb76c3b522ae9ca3d215bd647e4793623958573c77637db28c60`
- Target source version: `5.8.2`
- Release channel: stable candidate
- Database migrations: none
- Delete files: none
- Accepted updater source: `5.8.1`

## Hotfix scope

1. Replace all seven remaining Admin runtime `bi-devices` usages with Bootstrap Icons 1.8.1-compatible `bi-laptop`.
2. Cover the shared sidebar, Settings shortcut, Device Management header/empty state, License View Devices action, Backup Devices CSV action, and About Device Control card.
3. Add a recursive Admin runtime UI regression gate that rejects future `bi-devices` usage.
4. Align runtime/installer/CI/updater release identity and release documentation to v5.8.2.

No database, API, license/device enforcement, authentication, Dashboard data/AJAX, Developer Guide behavior, Cron or updater runtime/protocol change is authorized or included.

The final v5.8.2 baseline ZIP SHA-256 is recorded by the delivery package generated after verification.
