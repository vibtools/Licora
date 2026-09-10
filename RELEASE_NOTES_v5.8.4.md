# Licora v5.8.4 — Production Python SDK

Licora v5.8.4 adds a ready-to-use Python 3.10+ SDK for existing Secure API v2 Client Apps. Copy or install `SDK/python`, configure only public deployment/App/signing-key values in `licensing_public.py`, and use the high-level `LicoraAuth` module for license login, restore, validation, background checks and logout.

## Added

- Exact P-256 request proof for activation, status, refresh and deactivation.
- Pinned RSA-3072/RS256 access-token verification with exact issuer, audience, App ID, device identity, timestamp, JTI and protocol checks.
- Windows DPAPI and macOS/Linux system-keyring persistence with no plaintext fallback.
- Serialized login/validation/logout, one-shot refresh rotation, device recovery, local license-expiry cap, asynchronous helpers and background validation.
- Secret-free immutable status snapshots, a license guard decorator, headless/Tkinter examples and full integration documentation.
- Python 3.10/3.13 tests on Linux and Windows in the required CI dependency chain.

## Compatibility and upgrade

- Direct signed source: v5.8.3 only.
- Database migration: none.
- Deleted files: none.
- Server dependencies: none.
- Existing API v1/v2, Admin API, schema, license/device policy, UI, Dashboard, Cron and updater behavior are unchanged.

The SDK contains no API v1 key, Admin API key or private server signing key. A user license key is accepted only at runtime and is protected by the configured OS-backed state store.
