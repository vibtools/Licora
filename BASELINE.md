# Licora Phase 0 Immutable Baseline

**Baseline label:** `v5.0.1-baseline`  
**Repository:** `vibtools/Licora`  
**Default branch at snapshot:** `main`  
**Frozen source commit:** `c3db5759ac2539ab1525c73530ef5984b4b73ed6`  
**Audit date:** `2026-07-23`  
**Repository release version:** `5.0.1`  
**Runtime API metadata version:** `2.0`  
**Baseline mode:** Documentation and metadata only

This document freezes the current repository state for future comparison. It does not change PHP logic, SQL, routes, APIs, authentication, license generation, frontend assets, database schema, or runtime behavior.

---

## 1. Repository Integrity Snapshot

| Item | Frozen value |
|---|---|
| Repository name | `Licora` |
| Full repository identity | `vibtools/Licora` |
| Visibility | Public |
| Current branch | `main` |
| Frozen source commit | `c3db5759ac2539ab1525c73530ef5984b4b73ed6` |
| Repository version | `5.0.1` |
| Runtime `APP_VERSION` default | `2.0` |
| PHP requirement | PHP `8.0+` |
| Required PHP extensions | `pdo_mysql`, `openssl`, `json` |
| Database requirement | MySQL or MariaDB |
| Storage engine/collation baseline | InnoDB and `utf8mb4` |
| Pre-baseline source file count | `96` |
| Pre-baseline directory count | `17` |
| PHP framework | `NOT PRESENT` |
| Composer runtime packages | `NOT PRESENT` |
| Public API endpoint count | `2` |
| Logical database table count | `11` |
| Database trigger count | `2` |

### Count basis

The repository inventory at the open-source baseline contains 95 files. Commit `c3db5759ac2539ab1525c73530ef5984b4b73ed6` adds `RELEASE_NOTES-v5.0.1.md` and changes documentation/branding files only. Therefore the frozen source contains 96 files. The 17 directories exclude the repository root.

---

## 2. Baseline Release and Tag Metadata

### Baseline tag

```text
v5.0.1-baseline
```

### Recommended tag target

```text
c3db5759ac2539ab1525c73530ef5984b4b73ed6
```

### Recommended annotated tag command

```bash
git tag -a v5.0.1-baseline c3db5759ac2539ab1525c73530ef5984b4b73ed6 -m "Licora v5.0.1 immutable baseline"
git push origin v5.0.1-baseline
```

### Baseline release metadata

| Field | Value |
|---|---|
| Release tag | `v5.0.1-baseline` |
| Release title | `Licora v5.0.1-baseline — Immutable Baseline Snapshot` |
| Target commit | `c3db5759ac2539ab1525c73530ef5984b4b73ed6` |
| Release type | Baseline metadata release |
| Runtime changes | None |
| Schema changes | None |
| API changes | None |
| UI changes | None |

The connected GitHub integration used for this phase did not expose tag or release creation and returned an integration-level `403` for branch-reference creation. The exact tag and release metadata are therefore frozen here without changing runtime source.

---

## 3. Public Route Snapshot

### Root and installer

```text
/index.php
/install.php
```

### Admin routes

```text
/admin/login.php
/admin/logout.php
/admin/index.php
/admin/license.php
/admin/device.php
/admin/logs.php
/admin/api_keys.php
/admin/settings.php
/admin/admins.php
/admin/audit.php
/admin/backup.php
/admin/health.php
```

### Public API routes

```text
/api/verify.php
/api/check_license.php
```

### Cron entry points

```text
/cron/cleanup.php
/cron/check_expiring.php
```

---

# API Snapshot

This section is the immutable public API contract at the frozen source commit.

## 4. Endpoint: Full License Verification

### Method and URL

```text
POST /api/verify.php
OPTIONS /api/verify.php
```

### Request headers

```http
Content-Type: application/json
X-API-Key: <raw-api-key>
```

The endpoint also inspects header-name variants and `Authorization`. The current source advertises Bearer use, but the current extraction order does not reliably strip the `Bearer` prefix. JSON-body fallback key field: `api_key`.

### Request JSON

```json
{
  "license_key": "AAAAAAAA-BBBBBBBB-CCCCCCCC-DDDDDDDD",
  "device_hash": "optional-client-generated-device-id",
  "app_id": "optional-application-id",
  "app_version": "optional-application-version",
  "api_key": "optional-body-fallback-api-key"
}
```

### Required fields

- `license_key`

### Optional fields

- `device_hash`
- `app_id`
- `app_version`
- `api_key`

### License-key validation format

```regex
^[A-Z0-9]{8}-[A-Z0-9]{8}-[A-Z0-9]{8}-[A-Z0-9]{8}$
```

### Success response JSON

```json
{
  "success": true,
  "license": {
    "key": "AAAAAAAA-BBBBBBBB-CCCCCCCC-DDDDDDDD",
    "expires": "YYYY-MM-DD HH:MM:SS",
    "device_limit": 1,
    "devices_used": 1,
    "status": "active",
    "created_at": "YYYY-MM-DD HH:MM:SS"
  },
  "device_hash": "client-device-hash",
  "timestamp": "ISO-8601 timestamp",
  "server_time": 0,
  "server_version": "2.0",
  "message": "License valid"
}
```

### Conditional success fields

```json
{
  "warning": "License expires in N days",
  "days_remaining": 0,
  "debug": {}
}
```

`debug` is emitted only when the runtime environment is `development`.

### Failure response JSON

```json
{
  "success": false,
  "message": "Failure message",
  "timestamp": "ISO-8601 timestamp",
  "server_version": "2.0"
}
```

Some early failures also include a `debug` key, which is `null` in production.

### Status codes

| Condition | HTTP status |
|---|---:|
| CORS preflight | `200` |
| Successful verification | `200` |
| License-domain failure after valid API authentication | Usually `200` with `"success": false` |
| Invalid JSON or invalid request data | `400` |
| Missing API key | `401` |
| Invalid API key | `401` |
| Unsupported method | `405` |
| Rate limit exceeded | `429` |
| Uncaught production exception | `500` |

### Authentication and rate limiting

- API authentication uses SHA-256 lookup of the raw API key.
- The API key must be active and not expired.
- Rate limiting is tracked by client IP and endpoint in a one-hour window.
- The configured global default is `1000` requests per hour.
- Exact allowed-origin CORS comparison is applied to this endpoint.

### Verification flow

1. Enforce POST or process OPTIONS.
2. Apply IP/endpoint rate limit.
3. Extract and validate API key.
4. Decode JSON.
5. Validate license request.
6. Find an active license.
7. Enforce API-key or application-scope binding.
8. Enforce expiration.
9. Enforce license/device/IP blacklist.
10. Reconnect an existing device or register a new device.
11. Enforce active-device limit.
12. Update API-key counters.
13. Write API log.
14. Return JSON.

---

## 5. Endpoint: Legacy/Simple License Verification

### Method and URL

```text
POST /api/check_license.php
```

### Request headers

```http
Content-Type: application/json
```

API-key authentication is `NOT PRESENT` on this endpoint.

### Request JSON

```json
{
  "license_key": "AAAAAAAA-BBBBBBBB-CCCCCCCC-DDDDDDDD",
  "device_hash": "optional-client-generated-device-id"
}
```

### Success response shape

The endpoint returns the raw `LicenseSystem::verifyLicense()` result. Existing success variants include:

```json
{
  "success": true,
  "license": {},
  "message": "Device reconnected",
  "total_devices": 1,
  "active_devices": 1
}
```

```json
{
  "success": true,
  "license": {},
  "device_id": 1,
  "message": "New device registered",
  "total_devices": 1,
  "active_devices": 1
}
```

```json
{
  "success": true,
  "license": {},
  "active_devices": 0,
  "total_devices": 0
}
```

### Failure response examples

```json
{
  "success": false,
  "message": "Invalid license key"
}
```

```json
{
  "success": false,
  "message": "License has expired"
}
```

```json
{
  "success": false,
  "message": "Access denied"
}
```

```json
{
  "success": false,
  "message": "Device limit reached"
}
```

### Status codes

| Condition | HTTP status |
|---|---:|
| Success | `200` |
| Invalid method | Effective `200` with JSON error |
| Invalid license format | Effective `200` with JSON error |
| License-domain failure | Effective `200` with `"success": false` |
| Rate limit exceeded | `429` |
| Uncaught production exception | `500` |

---

# Database Snapshot

## 6. Database identity

```text
Database family: MySQL / MariaDB
Engine: InnoDB
Primary charset: utf8mb4
Primary collation: utf8mb4_unicode_ci
Base database name in schema: license_system
```

## 7. Table: `admin_users`

Purpose: administrator authentication, roles, login metadata, failed-attempt counters and optional two-factor fields.

| Column | Definition |
|---|---|
| `id` | `int(11) NOT NULL AUTO_INCREMENT` |
| `username` | `varchar(50) NOT NULL` |
| `password` | `varchar(255) NOT NULL` |
| `email` | `varchar(100) DEFAULT NULL` |
| `last_login` | `datetime DEFAULT NULL` |
| `failed_attempts` | `int(11) DEFAULT 0` |
| `locked_until` | `datetime DEFAULT NULL` |
| `two_factor_secret` | `varchar(255) DEFAULT NULL` |
| `two_factor_enabled` | `tinyint(1) DEFAULT 0` |
| `role` | `enum('super_admin','manager','viewer') NOT NULL DEFAULT 'super_admin'` |
| `created_at` | `timestamp NOT NULL DEFAULT current_timestamp()` |

Indexes:

```text
PRIMARY KEY (id)
UNIQUE KEY username (username)
KEY idx_username (username)
```

Foreign keys: `NOT PRESENT`.

---

## 8. Table: `api_keys`

Purpose: API-key authentication, activation, expiry, usage counters and application/scope metadata.

| Column | Definition |
|---|---|
| `id` | `int(11) NOT NULL AUTO_INCREMENT` |
| `api_key_hash` | `varchar(64) NOT NULL` |
| `api_key_encrypted` | `text DEFAULT NULL` |
| `name` | `varchar(100) NOT NULL` |
| `app_name` | `varchar(120) DEFAULT NULL` |
| `scope_label` | `varchar(120) DEFAULT NULL` |
| `user_id` | `int(11) DEFAULT NULL` |
| `is_active` | `tinyint(1) DEFAULT 1` |
| `rate_limit_per_hour` | `int(11) DEFAULT 1000` |
| `created_at` | `timestamp NOT NULL DEFAULT current_timestamp()` |
| `last_used_at` | `datetime DEFAULT NULL` |
| `expires_at` | `datetime DEFAULT NULL` |
| `request_count` | `int(11) DEFAULT 0` |

Indexes:

```text
PRIMARY KEY (id)
UNIQUE KEY api_key_hash (api_key_hash)
KEY user_id (user_id)
KEY idx_api_key_hash (api_key_hash)
KEY idx_is_active (is_active)
KEY idx_created_at (created_at)
```

Foreign key:

```text
api_keys_ibfk_1: user_id -> admin_users.id ON DELETE CASCADE
```

---

## 9. Table: `api_logs`

Purpose: API request metadata and response-code history.

| Column | Definition |
|---|---|
| `id` | `int(11) NOT NULL AUTO_INCREMENT` |
| `api_key_id` | `int(11) NOT NULL` |
| `endpoint` | `varchar(50) NOT NULL` |
| `license_key` | `varchar(100) DEFAULT NULL` |
| `ip_address` | `varchar(45) DEFAULT NULL` |
| `user_agent` | `text DEFAULT NULL` |
| `request_data` | `longtext DEFAULT NULL` |
| `response_code` | `int(11) DEFAULT NULL` |
| `response_time_ms` | `int(11) DEFAULT NULL` |
| `created_at` | `datetime DEFAULT current_timestamp()` |

Table default charset/collation:

```text
latin1 / latin1_swedish_ci
```

Selected text columns explicitly use `utf8mb4_unicode_ci`.

Indexes:

```text
PRIMARY KEY (id)
KEY idx_api_key_id (api_key_id)
KEY idx_created_at (created_at)
KEY idx_endpoint (endpoint)
KEY idx_license_key (license_key)
```

Foreign key:

```text
api_logs_ibfk_1: api_key_id -> api_keys.id ON DELETE CASCADE
```

---

## 10. Table: `blacklist`

Purpose: deny license, device or IP values.

| Column | Definition |
|---|---|
| `id` | `int(11) NOT NULL AUTO_INCREMENT` |
| `type` | `enum('device','ip','license') NOT NULL` |
| `value` | `varchar(255) NOT NULL` |
| `reason` | `text DEFAULT NULL` |
| `banned_by` | `int(11) DEFAULT NULL` |
| `expires_at` | `datetime DEFAULT NULL` |
| `created_at` | `timestamp NOT NULL DEFAULT current_timestamp()` |

Indexes:

```text
PRIMARY KEY (id)
KEY idx_blacklist_type (type, value)
KEY idx_blacklist_expires (expires_at)
KEY banned_by (banned_by)
```

Foreign key:

```text
blacklist_ibfk_1: banned_by -> admin_users.id
```

---

## 11. Table: `devices`

Purpose: license-bound device records and activity state.

| Column | Definition |
|---|---|
| `id` | `int(11) NOT NULL AUTO_INCREMENT` |
| `license_id` | `int(11) NOT NULL` |
| `device_hash` | `varchar(255) NOT NULL` |
| `device_info` | `text DEFAULT NULL` |
| `os` | `varchar(50) DEFAULT NULL` |
| `browser` | `varchar(50) DEFAULT NULL` |
| `country_code` | `varchar(2) DEFAULT NULL` |
| `city` | `varchar(100) DEFAULT NULL` |
| `login_time` | `datetime DEFAULT current_timestamp()` |
| `last_active` | `datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()` |
| `is_active` | `tinyint(1) DEFAULT 1` |

Indexes:

```text
PRIMARY KEY (id)
KEY idx_license_device (license_id, device_hash)
KEY idx_active_devices (license_id, is_active, last_active)
KEY idx_device_hash (device_hash)
KEY idx_license_id (license_id)
KEY idx_is_active (is_active)
```

Foreign key:

```text
devices_ibfk_1: license_id -> licenses.id ON DELETE CASCADE
```

Trigger:

```text
update_device_last_active
BEFORE UPDATE ON devices
SET NEW.last_active = NOW()
```

---

## 12. Table: `failed_logins`

Purpose: IP-based failed administrator login history.

| Column | Definition |
|---|---|
| `id` | `int(11) NOT NULL AUTO_INCREMENT` |
| `ip_address` | `varchar(45) NOT NULL` |
| `username` | `varchar(50) DEFAULT NULL` |
| `attempt_time` | `datetime DEFAULT current_timestamp()` |
| `user_agent` | `text DEFAULT NULL` |

Indexes:

```text
PRIMARY KEY (id)
KEY idx_failed_logins (ip_address, attempt_time)
```

Foreign keys: `NOT PRESENT`.

---

## 13. Table: `licenses`

Purpose: primary software-license state.

| Column | Definition |
|---|---|
| `id` | `int(11) NOT NULL AUTO_INCREMENT` |
| `license_key` | `varchar(50) NOT NULL` |
| `encrypted_key` | `text NOT NULL` |
| `created_by` | `int(11) DEFAULT NULL` |
| `notes` | `text DEFAULT NULL` |
| `app_scope` | `varchar(120) DEFAULT NULL` |
| `api_key_id` | `int(11) DEFAULT NULL` |
| `created_at` | `timestamp NOT NULL DEFAULT current_timestamp()` |
| `expires_at` | `datetime NOT NULL` |
| `device_limit` | `int(11) DEFAULT 1` |
| `status` | `enum('active','suspended','expired') DEFAULT 'active'` |
| `total_devices` | `int(11) DEFAULT 0` |
| `updated_at` | `datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()` |

Indexes:

```text
PRIMARY KEY (id)
UNIQUE KEY license_key (license_key)
UNIQUE KEY license_key_2 (license_key)
KEY idx_license_key (license_key)
KEY idx_status_expires (status, expires_at)
KEY created_by (created_by)
KEY idx_status (status)
KEY idx_expires_at (expires_at)
```

Foreign key:

```text
licenses_ibfk_1: created_by -> admin_users.id ON DELETE SET NULL
```

Foreign key for `api_key_id`: `NOT PRESENT`.

Trigger:

```text
update_license_timestamp
BEFORE UPDATE ON licenses
SET NEW.updated_at = NOW()
```

---

## 14. Table: `logs`

Purpose: general administrative and license activity history.

| Column | Definition |
|---|---|
| `id` | `int(11) NOT NULL AUTO_INCREMENT` |
| `license_id` | `int(11) DEFAULT NULL` |
| `admin_id` | `int(11) DEFAULT NULL` |
| `action` | `varchar(100) NOT NULL` |
| `details` | `text DEFAULT NULL` |
| `ip_address` | `varchar(45) DEFAULT NULL` |
| `created_at` | `timestamp NOT NULL DEFAULT current_timestamp()` |

Indexes:

```text
PRIMARY KEY (id)
KEY idx_license_logs (license_id, created_at)
KEY idx_admin_logs (admin_id, created_at)
KEY idx_created_at (created_at)
KEY idx_action (action)
```

Foreign keys:

```text
logs_ibfk_1: license_id -> licenses.id ON DELETE CASCADE
logs_ibfk_2: admin_id -> admin_users.id ON DELETE SET NULL
```

---

## 15. Table: `rate_limits`

Purpose: API request counters by IP address and endpoint.

| Column | Definition |
|---|---|
| `id` | `int(11) NOT NULL AUTO_INCREMENT` |
| `ip_address` | `varchar(45) NOT NULL` |
| `endpoint` | `varchar(100) NOT NULL` |
| `request_count` | `int(11) DEFAULT 1` |
| `first_request` | `datetime DEFAULT current_timestamp()` |
| `last_request` | `datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()` |

Indexes:

```text
PRIMARY KEY (id)
KEY idx_rate_limit (ip_address, endpoint, last_request)
```

Foreign keys: `NOT PRESENT`.

---

## 16. Table: `settings`

Purpose: persistent key/value application settings.

| Column | Definition |
|---|---|
| `id` | `int(11) NOT NULL AUTO_INCREMENT` |
| `setting_key` | `varchar(100) NOT NULL` |
| `setting_value` | `text DEFAULT NULL` |
| `updated_at` | `timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()` |

Indexes:

```text
PRIMARY KEY (id)
UNIQUE KEY setting_key (setting_key)
KEY idx_setting_key (setting_key)
```

Foreign keys: `NOT PRESENT`.

---

## 17. Table: `audit_trail`

Purpose: structured entity-level audit history.

| Column | Definition |
|---|---|
| `id` | `int(11) NOT NULL AUTO_INCREMENT` |
| `admin_id` | `int(11) DEFAULT NULL` |
| `entity_type` | `varchar(60) NOT NULL` |
| `entity_id` | `int(11) DEFAULT NULL` |
| `action` | `varchar(120) NOT NULL` |
| `details` | `text DEFAULT NULL` |
| `ip_address` | `varchar(45) DEFAULT NULL` |
| `user_agent` | `text DEFAULT NULL` |
| `created_at` | `timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP` |

Indexes:

```text
PRIMARY KEY (id)
KEY idx_audit_admin (admin_id)
KEY idx_audit_entity (entity_type, entity_id)
KEY idx_audit_created (created_at)
```

Foreign keys: `NOT PRESENT`.

---

## 18. Migration files

```text
migration.sql
migration-v4.sql
migration-v5.sql
migration-v5-fix.sql
migration-v5-hotfix.sql
```

### Migration snapshot

- `migration.sql`: clears reversible `api_key_encrypted` values without schema changes.
- `migration-v4.sql`: adds administrator role, API application/scope fields, license app scope, audit trail and settings.
- `migration-v5.sql`: adds API application/scope fields and `licenses.api_key_id`, then inserts binding settings.
- `migration-v5-fix.sql`: additive V5 App/Scope column hotfix and binding settings.
- `migration-v5-hotfix.sql`: additive V5 App/Scope save hotfix.
- Rollback migration files: `NOT PRESENT`.

---

# Folder Snapshot

## 19. Complete directory tree

```text
Licora/
├── .editorconfig
├── .gitattributes
├── .github/
│   ├── ISSUE_TEMPLATE/
│   │   ├── bug_report.yml
│   │   ├── config.yml
│   │   └── feature_request.yml
│   ├── dependabot.yml
│   ├── pull_request_template.md
│   └── workflows/
│       └── ci.yml
├── .gitignore
├── .htaccess
├── CHANGELOG-v4.txt
├── CHANGELOG-v5-fix.txt
├── CHANGELOG-v5-hotfix-2.txt
├── CHANGELOG-v5-ui-admin-update.txt
├── CHANGELOG-v5.txt
├── CHANGELOG.md
├── CODE_OF_CONDUCT.md
├── CONTRIBUTING.md
├── LICENSE
├── NOTICE
├── README.md
├── RELEASE_NOTES.md
├── RELEASE_NOTES-v5.0.1.md
├── REPOSITORY_METADATA.md
├── ROADMAP.md
├── SECURITY.md
├── SUPPORT.md
├── admin/
│   ├── admins.php
│   ├── api_keys.php
│   ├── assets/
│   │   ├── css/
│   │   │   └── admin-ui.css
│   │   └── js/
│   │       └── admin-ui.js
│   ├── audit.php
│   ├── backup.php
│   ├── device.php
│   ├── health.php
│   ├── includes/
│   │   └── navbar.php
│   ├── index.php
│   ├── license.php
│   ├── login.php
│   ├── logout.php
│   ├── logs.php
│   └── settings.php
├── api/
│   ├── check_license.php
│   └── verify.php
├── assets/
│   ├── banner.svg
│   └── screenshots/
│       └── README.md
├── audit/
│   ├── CHANGE_REPORT.md
│   ├── DEPENDENCY_REPORT.md
│   ├── FINAL_FILE_INVENTORY.csv
│   ├── FORENSIC_AUDIT_REPORT.md
│   ├── ORIGINAL_ARCHIVE_SHA256.txt
│   ├── ORIGINAL_FILE_INVENTORY.csv
│   ├── PHP_SYMBOL_INVENTORY.md
│   ├── PRIVACY_VALIDATION_REPORT.md
│   ├── RELEASE_CHECKLIST.md
│   ├── STATIC_INVENTORY.json
│   ├── UNIFIED_DIFF.patch
│   └── VALIDATION_REPORT.md
├── config.sample.php
├── cron/
│   ├── .htaccess
│   ├── check_expiring.php
│   └── cleanup.php
├── database.sql
├── docs/
│   ├── API.md
│   ├── ARCHITECTURE.md
│   ├── BUILD.md
│   ├── CODING_STANDARDS.md
│   ├── CONFIGURATION.md
│   ├── DATABASE.md
│   ├── DEVELOPMENT.md
│   ├── FEATURE_MATRIX.md
│   ├── FOLDER_STRUCTURE.md
│   ├── INSTALLATION.md
│   ├── MAINTENANCE.md
│   ├── MIGRATIONS.md
│   ├── RELEASE.md
│   ├── SECURITY_DEPLOYMENT.md
│   └── TROUBLESHOOTING.md
├── includes/
│   ├── .htaccess
│   ├── admin_helpers.php
│   ├── auth.php
│   ├── config.php
│   ├── database.php
│   ├── functions.php
│   ├── security.php
│   └── validation.php
├── index.php
├── install.php
├── migration-v4.sql
├── migration-v5-fix.sql
├── migration-v5-hotfix.sql
├── migration-v5.sql
├── migration.sql
├── scripts/
│   ├── package-release.sh
│   └── validate.sh
└── tests/
    └── security_smoke.php
```

---

# Feature Snapshot

## 20. Existing feature inventory

| # | Existing feature | Current implementation location |
|---:|---|---|
| 1 | Restricted root landing page | `index.php` |
| 2 | Web installation flow | `install.php` |
| 3 | Database creation/selection and schema import | `install.php`, `database.sql` |
| 4 | Local configuration-file generation | `install.php`, `config.sample.php` |
| 5 | Administrator login | `admin/login.php`, `includes/auth.php` |
| 6 | Administrator logout | `admin/logout.php`, `includes/auth.php` |
| 7 | Failed-login IP tracking | `failed_logins`, `includes/auth.php` |
| 8 | Administrator account lockout | `admin_users`, `includes/auth.php` |
| 9 | Bcrypt password hashing | `includes/security.php` |
| 10 | Legacy MD5/SHA-1 password migration | `includes/security.php`, `includes/auth.php` |
| 11 | Administrator CRUD | `admin/admins.php` |
| 12 | Roles: super admin, manager, viewer | `admin_users.role`, `AdminHelpers` |
| 13 | Admin dashboard | `admin/index.php` |
| 14 | License statistics | `LicenseSystem::getStats()` |
| 15 | API-key statistics | `admin/index.php` |
| 16 | Dashboard API request chart | `admin/index.php`, Chart.js |
| 17 | Dashboard expiry chart | `admin/index.php`, Chart.js |
| 18 | Top-used license display | `admin/index.php` |
| 19 | Recent API-call display | `admin/index.php` |
| 20 | License creation | `admin/license.php`, `LicenseSystem::createLicense()` |
| 21 | Bulk license creation | `admin/license.php` |
| 22 | License search/listing | `admin/license.php` |
| 23 | License activation | `admin/license.php`, `updateLicenseStatus()` |
| 24 | License suspension | `admin/license.php`, `updateLicenseStatus()` |
| 25 | License extension | `admin/license.php`, `extendLicense()` |
| 26 | License blacklist | `admin/license.php`, `blacklistLicense()` |
| 27 | License deletion | `admin/license.php` |
| 28 | Bulk license activate/suspend/extend/export/delete | `admin/license.php` |
| 29 | License CSV export | `admin/license.php`, `AdminHelpers::csv()` |
| 30 | Application/scope binding | `licenses.app_scope`, `api_keys.app_name`, `scope_label` |
| 31 | API-key-specific license binding | `licenses.api_key_id` |
| 32 | Random segmented license-key generation | `LicenseSystem::generateLicenseKey()` |
| 33 | Reversible license-key encryption copy | `Security::encrypt()` |
| 34 | Active-license validation | `LicenseSystem::verifyLicense()` |
| 35 | Expiry validation | `LicenseSystem::verifyLicense()` |
| 36 | License/device/IP blacklist validation | `LicenseSystem::isBlacklisted()` |
| 37 | Device registration | `LicenseSystem::registerDevice()` |
| 38 | Device reconnection/activity update | `LicenseSystem::updateDeviceActivity()` |
| 39 | Active-device limit enforcement | `LicenseSystem::verifyLicense()` |
| 40 | Device list and filtering by license | `admin/device.php` |
| 41 | Device logout | `admin/device.php` |
| 42 | Device blacklist | `admin/device.php` |
| 43 | Device deletion | `admin/device.php` |
| 44 | Old-device cleanup from admin | `admin/device.php` |
| 45 | License risk scoring | `LicenseSystem::getLicenseRiskScore()` |
| 46 | API-key generation | `admin/api_keys.php` |
| 47 | SHA-256 API-key lookup | `Security::validateAPIKey()` |
| 48 | Reversible encrypted API-key copy | `admin/api_keys.php`, `Security` |
| 49 | API-key display/copy | `admin/api_keys.php` |
| 50 | API-key endpoint test | `admin/api_keys.php` |
| 51 | API-key activation/deactivation | `admin/api_keys.php` |
| 52 | API-key deletion | `admin/api_keys.php` |
| 53 | API-key application/scope edit | `admin/api_keys.php` |
| 54 | Full authenticated verification API | `/api/verify.php` |
| 55 | Legacy/simple verification API | `/api/check_license.php` |
| 56 | Exact-origin CORS on full API | `api/verify.php` |
| 57 | IP/endpoint API rate limiting | `Security::checkRateLimit()` |
| 58 | API request counter | `api_keys.request_count` |
| 59 | API request logs | `api_logs` |
| 60 | General operational logs | `logs` |
| 61 | Structured audit trail | `audit_trail`, `AdminHelpers::audit()` |
| 62 | Activity-log viewer/filter/cleanup | `admin/logs.php` |
| 63 | Audit-trail viewer | `admin/audit.php` |
| 64 | Persistent settings panel | `admin/settings.php` |
| 65 | CSV exports for licenses/devices/logs | `admin/backup.php` |
| 66 | Full SQL database backup output | `admin/backup.php` |
| 67 | Authenticated health page | `admin/health.php` |
| 68 | Scheduled license expiry status update | `cron/cleanup.php` |
| 69 | Scheduled inactive-device handling | `cron/cleanup.php` |
| 70 | Scheduled log/rate-limit/login cleanup | `cron/cleanup.php` |
| 71 | Expiring-license console report | `cron/check_expiring.php` |
| 72 | CSRF tokens for admin mutations | `Security` and admin pages |
| 73 | Session ID regeneration | `Auth::adminLogin()` |
| 74 | Session user-agent binding | `Auth::isAdminLoggedIn()` |
| 75 | Session inactivity-check method | `Auth::checkSessionValidity()` |
| 76 | HTML escaping helper | `Security::escape()` |
| 77 | PDO singleton with native prepares | `Database` |
| 78 | Responsive Bootstrap/Tailwind admin UI | `admin/*.php`, CSS |
| 79 | Dark-mode preference | `admin-ui.js`, localStorage |
| 80 | Client-side filters and pagination | `admin-ui.js` |
| 81 | Confirmation modal, toasts and loader | `admin-ui.js` |
| 82 | Copy-to-clipboard controls | `admin-ui.js` |
| 83 | Additive V4/V5 schema compatibility | migrations, `AdminHelpers` |
| 84 | Repository static validation | `scripts/validate.sh` |
| 85 | Security smoke test | `tests/security_smoke.php` |
| 86 | GitHub Actions PHP matrix validation | `.github/workflows/ci.yml` |
| 87 | GitHub issue/PR templates | `.github/` |
| 88 | GitHub Actions Dependabot updates | `.github/dependabot.yml` |

---

## 21. Existing limitations

The following current-state limitations are documented only:

- Legacy `/api/check_license.php` does not require an API key.
- Bearer-token parsing on `/api/verify.php` is inconsistent.
- The session inactivity method is not the normal admin-page guard.
- Two-factor database fields and a settings toggle exist, but challenge/enrollment/recovery are not implemented.
- Password reset, email verification, registration and remember-login are not present.
- Offline licenses and cryptographic license signatures are not present.
- Domain lock and verified hardware attestation are not present.
- JWT issuance and validation are not present.
- Formal controller/model/middleware/queue/event systems are not present.
- No Composer or npm package manifest/lockfile is present.
- No database, HTTP, browser or concurrency automated integration suite is present.
- Several stored settings are not connected to runtime enforcement.
- Admin list pages generally load all matching records and paginate in the browser.
- License risk scoring performs per-license device queries.
- SQL backup reads full table rows into PHP memory.
- External frontend resources are loaded from CDNs.
- The current database schema contains duplicate license-key indexes and overlapping additive migrations.
- Current live deployment state is not part of this source snapshot.

---

# Configuration Snapshot

## 22. Configuration files

```text
includes/config.php
config.sample.php
includes/config.local.php   # optional, ignored, not committed
.htaccess
includes/.htaccess
cron/.htaccess
.gitignore
```

## 23. Configuration loading order

1. `includes/config.php` sets session-cookie parameters and starts the session.
2. `includes/config.local.php` is loaded when present.
3. Environment variables fill constants not already defined.
4. Source defaults are used last.
5. PHP timezone, error behavior, error handler, exception handler and autoloader are registered.

## 24. Constants and environment variables

| Runtime constant | Environment variable | Source default |
|---|---|---|
| `DB_HOST` | `LICENSE_DB_HOST`, fallback `DB_HOST` | `localhost` |
| `DB_NAME` | `LICENSE_DB_NAME`, fallback `DB_NAME` | empty |
| `DB_USER` | `LICENSE_DB_USER`, fallback `DB_USER` | empty |
| `DB_PASS` | `LICENSE_DB_PASS`, fallback `DB_PASS` | empty |
| `APP_NAME` | `APP_NAME` | `License System` |
| `APP_URL` | `APP_URL` | `http://localhost` |
| `APP_VERSION` | `APP_VERSION` | `2.0` |
| `ENVIRONMENT` | `APP_ENV` | `production` |
| `ENCRYPTION_KEY` | `LICENSE_ENCRYPTION_KEY` | empty |
| `CSRF_SECRET` | `LICENSE_CSRF_SECRET` | empty |
| `JWT_SECRET` | `LICENSE_JWT_SECRET` | empty |
| `API_RATE_LIMIT` | `API_RATE_LIMIT` | `1000` |
| `API_VERSION` | `API_VERSION` | `v1` |
| Allowed API origin | `LICENSE_ALLOWED_ORIGIN` | `APP_URL` |

## 25. Stored setting keys

```text
system_name
timezone
default_license_hours
default_device_limit
api_rate_limit
log_retention_days
enable_two_factor
maintenance_mode
api_base_url
license_key_prefix
license_min_hours
license_max_hours
device_inactive_minutes
api_timeout_seconds
dashboard_rows
admin_session_timeout_minutes
enable_api_logging
show_server_version
auto_detect_base_url
system_root_url
support_email
license_warning_days
enable_audit_trail
risk_high_device_threshold
risk_high_ip_threshold
backup_enabled
viewer_readonly
license_api_key_binding_enabled
license_app_scope_enforcement
```

## 26. Runtime configuration behavior

- PHP timezone is set to `Asia/Dhaka` by source.
- Production disables displayed PHP errors.
- Development displays PHP errors and enables API debug logging/response fields.
- PDO uses `ERRMODE_EXCEPTION`, associative fetches and native prepares.
- Session cookie settings:
  - lifetime `0`
  - path `/`
  - domain empty
  - Secure only when HTTPS is detected
  - HttpOnly enabled
  - SameSite `Lax`
- API full-endpoint CORS origin defaults to `APP_URL` unless `LICENSE_ALLOWED_ORIGIN` is set.
- `.env` parsing code is not present; values are read through `getenv()`.
- `includes/config.local.php` is excluded by `.gitignore`.

---

# Security Baseline

## 27. Authentication

- Administrator authentication uses username and password.
- Current password hashes use bcrypt with cost 12.
- Legacy 32-character MD5 and 40-character SHA-1 hashes are accepted and rehashed after successful login.
- Failed attempts are stored by IP and account.
- Five account failures trigger a 30-minute lock.
- Session ID is regenerated after successful login.
- Registration, password reset, email verification and remember-login are not present.
- Two-factor fields/settings exist; full 2FA flow is not present.

## 28. Authorization

Roles:

```text
super_admin
manager
viewer
```

Permission helpers:

```text
AdminHelpers::canManage()
AdminHelpers::canDelete()
AdminHelpers::requireManage()
AdminHelpers::requireDelete()
```

- Super administrators can perform delete-level operations.
- Managers can perform management operations.
- Viewers are intended to be read-only.
- The current license bulk-export branch does not invoke `requireManage()` before export.

## 29. Encryption

Algorithm:

```text
AES-256-CBC
```

Format:

1. Derive key material using SHA-256 of configured/fallback key material.
2. Generate a 16-byte random IV.
3. Call OpenSSL AES-256-CBC encryption with default OpenSSL base64 output.
4. Concatenate raw IV bytes and the OpenSSL ciphertext string.
5. Base64-encode the combined value.

Authentication tag or MAC: `NOT PRESENT`.

If `ENCRYPTION_KEY` is empty, fallback key material is derived from source path, `APP_URL` and `DB_NAME`.

## 30. Hashing

| Data | Current algorithm |
|---|---|
| Administrator password | bcrypt, cost 12 |
| Legacy password compatibility | MD5 and SHA-1 accepted |
| API-key lookup | SHA-256 |
| Device hash | SHA-256 |
| Generated tokens | `random_bytes()` and hexadecimal encoding |
| Generated license key | Four uppercase hexadecimal segments from `random_bytes()` |

## 31. Rate limiting

- Applies to both public API scripts.
- Counter identity: IP address plus endpoint.
- Window: one hour.
- Default limit: `API_RATE_LIMIT`, source default `1000`.
- Storage: `rate_limits`.
- Stale counters are deleted before checking.
- Table does not enforce a unique IP/endpoint key.

## 32. Session

Session keys:

```text
admin_id
admin_username
admin_email
admin_role
admin_logged_in
login_time
session_ip
session_user_agent
csrf_token
new_api_key
```

- User agent is checked by `isAdminLoggedIn()`.
- Session IP is stored but is not checked by that method.
- A 30-minute inactivity method exists separately.
- The inactivity method is not the standard guard used by inspected admin pages.

## 33. CSRF

- CSRF token is random session state.
- Comparison uses `hash_equals()`.
- Login and most state-changing admin actions validate the token.
- Several mutation actions use GET URLs carrying a CSRF token.
- Logout uses GET without CSRF validation.

## 34. Logging

Current destinations:

```text
PHP/server error log
logs
audit_trail
api_logs
failed_logins
cron stdout
```

- `logs` stores action, details, optional license/admin IDs, IP and time.
- `audit_trail` stores entity/action details, IP, user agent and time.
- `api_logs` stores API key ID, endpoint, license key, IP, user agent and response code.
- `failed_logins` stores IP, username, user agent and time.
- Expiring-license cron output prints full license keys.

## 35. Verified security findings

| ID | Severity | Current verified finding |
|---|---|---|
| `SB-01` | High | Legacy verification endpoint lacks API authentication |
| `SB-02` | High | Advertised Bearer-token parsing is defective |
| `SB-03` | High | Viewer role can export license keys through bulk export |
| `SB-04` | High | Public schema includes a known temporary administrator credential |
| `SB-05` | High | Installer is unauthenticated and has no CSRF token before setup completion |
| `SB-06` | High | AES-CBC storage has no authentication tag/MAC and has deterministic fallback key material |
| `SB-07` | High | API keys are stored reversibly and displayed to administrators |
| `SB-08` | Medium | State-changing admin actions use GET URLs containing CSRF tokens |
| `SB-09` | Medium | Session inactivity timeout is not the common admin guard |
| `SB-10` | Medium | Rate-limit counter has concurrency and uniqueness gaps |
| `SB-11` | Medium | Device-limit enforcement has a concurrent count/check/insert gap |
| `SB-12` | Medium | API-key test performs an administrator-controlled server-side HTTP request |
| `SB-13` | Medium | Development diagnostics expose request/API metadata to logs |
| `SB-14` | Medium | Cron scripts lack an internal CLI-only check |
| `SB-15` | Medium | External frontend resources lack SRI/CSP and include unpinned URLs |
| `SB-16` | Medium | Domain license creation does not enforce all documented numeric limits |
| `SB-17` | Medium | Full license identifiers are written to API/cron logs |
| `SB-18` | Medium | Legacy MD5/SHA-1 password hashes remain accepted |
| `SB-19` | Low | OS/browser detection order can misclassify clients |
| `SB-20` | Low | Schema contains duplicate indexes and overlapping migrations |
| `SB-21` | Low | SQL backup loads complete tables into PHP memory |
| `SB-22` | Low | Dashboard subsystem-status labels are not independent health checks |
| `SB-23` | Low | Logout is a GET action without CSRF |
| `SB-24` | Info | Multiple stored settings are not enforced at runtime |
| `SB-25` | Info | Dormant/unreachable methods and configuration constants exist |
| `SB-26` | Info | Database/HTTP/browser automated integration tests are not present |

No fixes are included in Phase 0.

---

# Compatibility Contract

Everything in this section is frozen as a public or operational contract.

## 36. API URLs

```text
/api/verify.php
/api/check_license.php
```

## 37. API JSON contract

### Request keys that must remain recognizable

```text
license_key
device_hash
app_id
app_version
api_key
```

### Full success response keys

```text
success
license
license.key
license.expires
license.device_limit
license.devices_used
license.status
license.created_at
device_hash
timestamp
server_time
server_version
message
warning
days_remaining
debug
```

### Failure response keys

```text
success
message
timestamp
server_version
debug
```

## 38. License format

Generated format:

```text
XXXXXXXX-XXXXXXXX-XXXXXXXX-XXXXXXXX
```

Generated characters are uppercase hexadecimal. Validation currently accepts uppercase alphanumeric characters.

## 39. License validation behavior

The current order is:

1. Active license lookup by exact plaintext `license_key`.
2. API-key ID binding when `licenses.api_key_id` is populated.
3. Application/scope binding when `licenses.app_scope` is populated.
4. Expiration check.
5. License/device/IP blacklist check.
6. Active-device count.
7. Existing-device reconnection or new-device registration.
8. Device-limit rejection when at capacity.
9. Activity/counter/log updates.

## 40. License status values

```text
active
suspended
expired
```

## 41. Blacklist type values

```text
device
ip
license
```

## 42. Administrator role values

```text
super_admin
manager
viewer
```

## 43. Database schema contract

Tables that must retain identity:

```text
admin_users
api_keys
api_logs
blacklist
devices
failed_logins
licenses
logs
rate_limits
settings
audit_trail
```

Columns, indexes, foreign keys and triggers are frozen in Sections 7–18.

## 44. Encryption storage format

Existing encrypted values depend on:

```text
AES-256-CBC
16-byte IV
SHA-256-derived key material
base64(IV + OpenSSL-default-base64-ciphertext)
```

Changing the algorithm, IV size, key derivation or encoding would affect stored values.

## 45. API-key storage format

```text
Raw generated API key: 64 lowercase hexadecimal characters
Lookup value: SHA-256 hexadecimal digest
Optional reversible copy: Security::encrypt() output
```

## 46. Configuration keys

Constants/environment keys frozen in this baseline:

```text
DB_HOST
DB_NAME
DB_USER
DB_PASS
LICENSE_DB_HOST
LICENSE_DB_NAME
LICENSE_DB_USER
LICENSE_DB_PASS
APP_NAME
APP_URL
APP_VERSION
APP_ENV
ENVIRONMENT
ENCRYPTION_KEY
LICENSE_ENCRYPTION_KEY
CSRF_SECRET
LICENSE_CSRF_SECRET
JWT_SECRET
LICENSE_JWT_SECRET
API_RATE_LIMIT
API_VERSION
LICENSE_ALLOWED_ORIGIN
```

Stored setting keys are frozen in Section 25.

## 47. Cron entry points

```text
php cron/cleanup.php
php cron/check_expiring.php
```

## 48. Validation commands

```text
php tests/security_smoke.php
bash scripts/validate.sh
bash scripts/package-release.sh
```

## 49. Session structure

The names of session keys listed in Section 32 form the current compatibility contract.

## 50. Public class/method contract

### `Database`

```text
getInstance()
close()
```

### `Security`

```text
sanitize()
escape()
generateToken()
hashPassword()
verifyPassword()
passwordNeedsRehash()
encrypt()
decrypt()
generateCSRFToken()
validateCSRFToken()
requireCSRFToken()
getClientIP()
generateDeviceHash()
validateLicenseFormat()
validateAPIKey()
checkRateLimit()
```

### `Auth`

```text
adminLogin()
isAdminLoggedIn()
adminLogout()
logAdminAction()
getUserId()
getRole()
canManage()
canDelete()
getUsername()
checkSessionValidity()
```

### `LicenseSystem`

```text
createLicense()
verifyLicense()
updateLicenseStatus()
extendLicense()
blacklistLicense()
addLog()
getStats()
getLicenseRiskScore()
```

### `Validation`

```text
validateLicenseData()
validateLoginData()
validateAPIRequest()
getErrors()
getError()
getFormattedErrors()
```

### `AdminHelpers`

```text
tableExists()
columnExists()
ensureColumn()
ensureV5Schema()
role()
canManage()
canDelete()
requireManage()
requireDelete()
audit()
csv()
```

## 51. Critical file locations

```text
database.sql
migration.sql
migration-v4.sql
migration-v5.sql
migration-v5-fix.sql
migration-v5-hotfix.sql
config.sample.php
includes/config.php
includes/database.php
includes/security.php
includes/auth.php
includes/functions.php
includes/validation.php
includes/admin_helpers.php
api/verify.php
api/check_license.php
admin/
admin/assets/css/admin-ui.css
admin/assets/js/admin-ui.js
cron/cleanup.php
cron/check_expiring.php
scripts/validate.sh
tests/security_smoke.php
```

---

## 52. Baseline immutability statement

The frozen application source is commit:

```text
c3db5759ac2539ab1525c73530ef5984b4b73ed6
```

Phase 0 documentation does not authorize any runtime, schema, API, route, UI, authentication, encryption, license-generation or feature change. Future comparison must use the frozen commit, this document, `BASELINE_CHECKSUMS.sha256`, `RELEASE_NOTES_BASELINE.md`, and `BASELINE_MANIFEST.md`.
