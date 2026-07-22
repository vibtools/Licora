# Feature Matrix

| Area | Current status | Evidence / limitation |
|---|---|---|
| License creation | Implemented | Hours, device limit, notes, app scope, optional API-key binding. |
| License status and extension | Implemented | Admin mutation actions and audit entries. |
| Device registration | Implemented | New devices are blocked when the active limit is full. |
| Automatic oldest-device logout | Dormant code | Private method exists but is not called; current behavior blocks the new device. |
| Full API-key verification | Implemented | `api/verify.php` with key hash lookup and scope binding. |
| Bearer authentication | Defective | Header is advertised, but extraction paths are inconsistent. Use `X-API-Key`. |
| Simple API | Implemented / legacy risk | No API key; retained for compatibility. |
| Global IP rate limit | Implemented | `API_RATE_LIMIT`; one-hour window. |
| Per-API-key rate limit | Stored only | `rate_limit_per_hour` is not applied. |
| Admin roles | Implemented | `super_admin`, `manager`, `viewer`. |
| Session inactivity timeout | Defined, not enforced | `checkSessionValidity()` is not called by admin pages. |
| CSRF protection | Mostly implemented | Admin mutations use tokens; installer has none; some actions use GET query tokens. |
| Password hashing | Implemented | Bcrypt cost 12 with legacy MD5/SHA-1 migration support. |
| Two-factor authentication | Schema/settings only | UI toggle and columns exist; no challenge flow. |
| Maintenance mode | Stored only | UI setting is not enforced. |
| Stored timezone | Stored only | PHP timezone is hardcoded to `Asia/Dhaka`. |
| Audit trail | Implemented | Additive table plus fallback general logs. |
| CSV and SQL backup | Implemented | Role protected; exported material is sensitive. |
| Cron cleanup | Implemented | CLI intended; web access denied for Apache in public release. |
| Frontend offline mode | Not implemented | UI depends on external CDNs. |
