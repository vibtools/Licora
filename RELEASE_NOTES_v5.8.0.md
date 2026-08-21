# Licora v5.8.0 — Developer Integration Guide

Licora v5.8.0 adds an authenticated, compact **Developer Guide** to the existing Licora admin shell. The guide documents the real Secure API v2 device-proof contract, shows the detected API v2 endpoints for the current installation, and ships downloadable multi-language lifecycle reference clients.

## Added

- New `Developer Guide` route under **API & Clients**.
- Secure API v2 Quick Start covering Client App registration, scoped licensing, P-256 device identity, activation, status, refresh rotation and deactivation.
- Exact canonical request-proof reference for `X-Licora-Timestamp`, `X-Licora-Nonce` and `X-Licora-Device-Signature`.
- Downloadable examples for Python, PowerShell/CMD, C, C++, C#/.NET, Java, Flutter, React Native, PHP and Node.js.
- One-file `licora-v2-test.ps1` lifecycle test for Windows developers.
- Stable error-code and production security checklists.
- Copy-to-clipboard and compact language-tab UI without changing Licora's shared shell.

## Security

- Public-client examples never embed the API v1 shared/master credential.
- Examples sign the exact raw JSON bytes sent to Licora and use fresh timestamp/nonce values.
- Test clients use ephemeral P-256 device credentials and deactivate them after the lifecycle test.
- Production guidance requires OS-backed private-key/refresh-token storage and pinned `LICORA-V2`/`RS256` server-token verification before trusting claims locally.

## Compatibility

- Direct signed update source: `v5.7.1`.
- Database migrations: none.
- Deleted files: none.
- API v1 behavior: unchanged.
- Secure API v2 protocol/cryptography: unchanged.
- License/device enforcement, authentication/roles, Dashboard, Cron and updater runtime/protocol: unchanged.
