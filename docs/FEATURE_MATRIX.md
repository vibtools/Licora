# Feature Matrix

| Area | Current status | Evidence / limitation |
|---|---|---|
| License creation | Implemented | Hours, device limit, notes, app scope, optional API-key binding. |
| License status and extension | Implemented | Admin mutation actions and audit entries. |
| Device registration | Implemented | New devices are blocked when the active limit is full. |
| Automatic oldest-device logout | Dormant code | Private method exists but is not called; current behavior blocks the new device. |
| Full API-key verification | Implemented | `api/verify.php` with key hash lookup and scope binding. |
| Secure API v2 | Implemented | Device-bound P-256 request proofs, RS256 server tokens, rotating refresh credentials, replay protection, matching signing-key validation, persistent refresh rate limits, and no desktop shared API key. |
| API v2 client app management | Implemented | Admin Client Apps page controls App IDs, version floor, TTL and rate policy; it also provides authenticated cPanel-friendly v2 initialization when shell access is unavailable. v5.2.2 aligns admin schema discovery so active V2 apps remain visible to the existing License app-scope selector. |
| API v2 device revocation | Implemented | Admin V2 Devices page lists activated credentials and revokes the credential and refresh tokens; v5.2.2 aligns its schema detection with API v2 runtime. |
| API v1 Bearer/API-key authentication | Implemented | Existing reviewed v1 credential normalization remains supported; v1 is unchanged. |
| Simple API | Implemented / legacy risk | No API key; retained for compatibility. |
| Global IP rate limit | Implemented | `API_RATE_LIMIT`; one-hour window. |
| Per-API-key rate limit | Stored only | `rate_limit_per_hour` is not applied. |
| Admin roles | Implemented | `super_admin`, `manager`, `viewer`. |
| Session inactivity timeout | Implemented | `Auth::isAdminLoggedIn()` enforces the existing 30-minute inactivity timeout on protected admin pages. |
| CSRF protection | Mostly implemented | Admin mutations use tokens; installer has none; some actions use GET query tokens. |
| Password hashing | Implemented | Bcrypt cost 12 with legacy MD5/SHA-1 migration support. |
| Two-factor authentication | Schema only / not implemented | Database columns/legacy setting may exist, but v5.5.0 does not present a working toggle because no challenge flow exists. |
| Maintenance mode | Stored only / not implemented | Legacy setting may remain in the database; v5.5.0 does not present it as an active control because enforcement is absent. |
| Stored timezone | Stored only | Legacy DB value is not the runtime authority; runtime timezone comes from `APP_TIMEZONE`/configuration. |
| Audit trail | Implemented | Additive table plus fallback general logs. |
| CSV and SQL backup | Implemented | Role protected; exported material is sensitive. |
| Cron cleanup | Implemented | CLI intended; web access denied for Apache in public release. |
| Secure in-app updates | Implemented in v5.3.0 | Super Admin Update Center checks stable official GitHub releases, verifies a dedicated signed manifest and package inventory, runs preflight, stages/backups, tracks migrations/jobs/events, applies source in resumable chunks, provides live VibTools logs, and supports rollback. v5.2.2 → v5.3.0 itself remains the one manual bootstrap update. |
| VibTools Light component UI | Implemented in v5.4.0 | Shared light semantic theme, reusable sidebar/topbar shell, cards/forms/tables/modals and responsive drawer based on VibTools Web UI v2.1.2 structure. Primary routes, DOM/form/backend contracts remain unchanged. |
| Truthful Admin Settings | Implemented in v5.5.0 | Only runtime-consumed license defaults/limits and log retention are editable; legacy stored-only keys remain preserved but hidden from the active Settings UI. |
| API/runtime integration information | Implemented in v5.5.0 | Read-only detected API endpoints, runtime limits, Cron CLI commands, environment/version and API v2 public-key status/fingerprint are shown with copy/download actions. Private signing-key export is prohibited. |
| Licora product branding | Implemented in v5.5.0 | Supplied Licora logos/icons/favicons are tracked and used across shell/login/root/installer/About; visible product identity is fixed to Licora. |
| Settings/About UI finishing | Implemented in v5.5.1 | Equal-width Settings shortcuts, non-stretching Cron/Signing composition, collapsible Settings submenu and complete About Licora product/company presentation. |
| VibTools Compact Light UI | Implemented in v5.5.0 | Compact tables/forms/toolbars/action menus/scrollbars and responsive License/Device recomposition refine the existing v5.4 component shell without changing backend contracts. |
| Dashboard centralized read model | Implemented in v5.6.0; contract corrected in v5.6.1 | `DashboardReadModel` supplies the initial Dashboard and authenticated Dashboard JSON endpoint through read-only queries. |
| Dashboard data truth | Implemented in v5.6.0; corrected in v5.6.1 | License/device/API/expiration labels are tied to explicit sources; v1/v2 tracked activity is separated and fake operational health claims are removed. |
| Dashboard reload-free refresh | Implemented in v5.7.0; corrected and published in v5.7.1 | Dedicated Dashboard controller uses authenticated 30-second AJAX polling, manual refresh, in-place KPI/chart/activity updates, overlap prevention, last-updated feedback and stale/auth states while preserving server-rendered fallback. |
| Developer Integration Guide | Implemented in v5.8.0 source candidate; corrected/verified in v5.8.1 | Authenticated compact Secure API v2 Quick Start, exact device-proof contract, detected endpoints, stable error codes, security checklist and downloadable references for Python, PowerShell/CMD, C, C++, C#/.NET, Java, Flutter, React Native, PHP and Node.js. |
| Admin device icon compatibility | Corrected in v5.8.2 | All Admin device-related glyphs use Bootstrap Icons 1.8.1-compatible `bi-laptop`; regression coverage rejects unsupported `bi-devices`. |
| Browser runtime dependency | Browser-agnostic | Licora is server-rendered PHP and contains no Chrome installer/downloader or `chrome.exe` launcher dependency; browser selection belongs to the user/client environment. |
| Frontend offline mode | Not implemented | UI depends on external CDNs. |
