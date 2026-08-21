# Licora v5.8.1 — Developer Integration Guide Verification Fix

Licora v5.8.1 is a no-migration corrective release for the v5.8.0 Developer Integration Guide source candidate.

## Fixed

- Aligns the pull-request CI candidate ZIP, release specification and generated updater manifest on version `5.8.1`; the v5.8.0 source candidate used a stale `--version 5.7.1` manifest-builder argument.
- Restores the **Recently Seen Devices** and **Manage Devices** Dashboard glyphs by using `bi-laptop`, which exists in the existing Bootstrap Icons 1.8.1 dependency, instead of unsupported `bi-devices`.
- Adds regression checks for CI version coherence and the Dashboard device-icon contract.

## Verified scope

- Preserves the authenticated Developer Guide and exactly ten approved Secure API v2 reference targets: Python, PowerShell/CMD, C, C++, C#/.NET, Java, Flutter, React Native, PHP and Node.js.
- Revalidates the canonical device-proof sequence, P-256/ECDSA-SHA256 signing, activation/refresh/status/deactivate contexts, stable error codes and no-shared-API-v1-secret rule.
- Extends the source browser-dependency guard to the Developer Guide page/controller. Licora contains no Chrome installer/downloader/launcher; any external Chrome download failure requires the separate launcher/wrapper source to diagnose.

## Compatibility

- Upgrade sources: `5.7.1`, `5.8.0`.
- Database migrations: none.
- Deleted files: none.
- API v1/v2 server behavior: unchanged.
- License/device enforcement: unchanged.
- Authentication/roles: unchanged.
- Dashboard data/refresh behavior: unchanged; only two icon classes change.
- Cron and updater runtime/protocol: unchanged.
