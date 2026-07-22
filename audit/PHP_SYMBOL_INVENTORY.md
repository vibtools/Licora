# PHP Symbol Inventory

Generated from the untouched uploaded source before release preparation. Static line numbers refer to that original tree.

- Executable `.php` files: **27**
- Extensionless PHP-like tree note cataloged separately: **1**
- Classes: **6**
- Functions/methods: **82**

## `admin/admins.php`
- Classes: none
- Functions/methods:
  - `valid_admin_role($role)` — global, line 14
- Request handling: POST=yes; CSRF mentions=10; auth mentions=2

## `admin/api_keys.php`
- Classes: none
- Functions/methods:
  - `detected_api_base_url()` — global, line 21
  - `testAPIKey($apiKey, $db, $url)` — global, line 164
  - `copyApiKey()` — global, line 298
  - `openTestModal(id,key)` — global, line 299
- Request handling: POST=yes; CSRF mentions=20; auth mentions=2

## `admin/audit.php`
- Classes: none
- Functions/methods: none
- Request handling: POST=no; CSRF mentions=0; auth mentions=2

## `admin/backup.php`
- Classes: none
- Functions/methods: none
- Request handling: POST=no; CSRF mentions=0; auth mentions=2

## `admin/device.php`
- Classes: none
- Functions/methods: none
- Request handling: POST=no; CSRF mentions=16; auth mentions=2

## `admin/health.php`
- Classes: none
- Functions/methods: none
- Request handling: POST=no; CSRF mentions=0; auth mentions=2

## `admin/includes/navbar.php`
- Classes: none
- Functions/methods: none
- Request handling: POST=no; CSRF mentions=0; auth mentions=1

## `admin/index.php`
- Classes: none
- Functions/methods: none
- Request handling: POST=no; CSRF mentions=0; auth mentions=2

## `admin/license.php`
- Classes: none
- Functions/methods: none
- Request handling: POST=yes; CSRF mentions=30; auth mentions=2

## `admin/login.php`
- Classes: none
- Functions/methods: none
- Request handling: POST=yes; CSRF mentions=4; auth mentions=2

## `admin/logout.php`
- Classes: none
- Functions/methods: none
- Request handling: POST=no; CSRF mentions=0; auth mentions=1

## `admin/logs.php`
- Classes: none
- Functions/methods: none
- Request handling: POST=no; CSRF mentions=4; auth mentions=2

## `admin/settings.php`
- Classes: none
- Functions/methods:
  - `detected_api_base_url()` — global, line 17
  - `detected_root_url()` — global, line 26
  - `save_setting($db, $key, $value)` — global, line 35
- Request handling: POST=yes; CSRF mentions=4; auth mentions=3

## `api/check_license.php`
- Classes: none
- Functions/methods: none
- Request handling: POST=no; CSRF mentions=0; auth mentions=0

## `api/verify.php`
- Classes: none
- Functions/methods: none
- Request handling: POST=no; CSRF mentions=0; auth mentions=7

## `config.sample.php`
- Classes: none
- Functions/methods: none
- Request handling: POST=no; CSRF mentions=3; auth mentions=0

## `cron/check_expiring.php`
- Classes: none
- Functions/methods: none
- Request handling: POST=no; CSRF mentions=0; auth mentions=0

## `cron/cleanup.php`
- Classes: none
- Functions/methods: none
- Request handling: POST=no; CSRF mentions=0; auth mentions=0

## `includes/admin_helpers.php`
- Classes: `AdminHelpers`
- Functions/methods:
  - `tableExists($table)` — public static, line 6
  - `columnExists($table, $col)` — public static, line 16
  - `ensureColumn($table, $column, $definition)` — public static, line 47
  - `ensureV5Schema()` — public static, line 63
  - `role()` — public static, line 72
  - `canManage()` — public static, line 76
  - `canDelete()` — public static, line 80
  - `requireManage()` — public static, line 84
  - `requireDelete()` — public static, line 91
  - `audit($type, $id, $action, $details = '')` — public static, line 98
  - `csv($name, $headers, $rows)` — public static, line 128
- Request handling: POST=no; CSRF mentions=0; auth mentions=0

## `includes/auth.php`
- Classes: `Auth`
- Functions/methods:
  - `__construct()` — public, line 9
  - `adminLogin($username, $password)` — public, line 15
  - `isAdminLoggedIn()` — public, line 73
  - `adminLogout()` — public, line 84
  - `checkFailedLogins()` — private, line 98
  - `logFailedLogin($username, $reason)` — private, line 114
  - `incrementFailedAttempts($username)` — private, line 127
  - `resetFailedAttempts($username)` — private, line 151
  - `logAdminAction($admin_id, $action, $details)` — public, line 161
  - `getUserId()` — public, line 175
  - `getRole()` — public, line 178
  - `canManage()` — public, line 180
  - `canDelete()` — public, line 181
  - `getUsername()` — public, line 183
  - `checkSessionValidity()` — public, line 188
- Request handling: POST=no; CSRF mentions=2; auth mentions=7

## `includes/config.php`
- Classes: none
- Functions/methods:
  - `env_value($key, $default = '')` — global, line 24
- Request handling: POST=no; CSRF mentions=3; auth mentions=0

## `includes/database.php`
- Classes: `Database`
- Functions/methods:
  - `__construct()` — private, line 7
  - `getInstance()` — public static, line 24
  - `close()` — public static, line 31
- Request handling: POST=no; CSRF mentions=0; auth mentions=0

## `includes/functions.php`
- Classes: `LicenseSystem`
- Functions/methods:
  - `__construct()` — public, line 9
  - `createLicense($hours, $device_limit = 1, $admin_id = null, $notes = '', $app_scope = '', $api_key_id = null)` — public, line 15
  - `verifyLicense($license_key, $device_hash = null, $api_key_id = null)` — public, line 80
  - `registerDevice($license_id, $device_hash)` — private, line 202
  - `logoutOldestDevice($license_id)` — private, line 240
  - `updateDeviceActivity($device_id)` — private, line 258
  - `getActiveDeviceCount($license_id)` — private, line 270
  - `getDeviceByHash($license_id, $device_hash)` — private, line 284
  - `generateLicenseKey()` — private, line 301
  - `updateTotalDevices($license_id)` — private, line 310
  - `updateLicenseStatus($license_id, $status)` — public, line 331
  - `extendLicense($license_id, $additional_hours)` — public, line 350
  - `blacklistLicense($license_key, $reason, $admin_id)` — public, line 372
  - `suspendLicenseByKey($license_key)` — private, line 395
  - `isBlacklisted($license_key, $device_hash = null)` — private, line 407
  - `addLog($license_id, $admin_id, $action, $details)` — public, line 430
  - `detectOS()` — private, line 447
  - `detectBrowser()` — private, line 460
  - `getStats()` — public, line 473
  - `getLicenseRiskScore($license_id)` — public, line 514
- Request handling: POST=no; CSRF mentions=0; auth mentions=0

## `includes/security.php`
- Classes: `Security`
- Functions/methods:
  - `sanitize($input)` — public static, line 3
  - `escape($data)` — public static, line 9
  - `generateToken($length = 32)` — public static, line 16
  - `hashPassword($password)` — public static, line 20
  - `verifyPassword($password, $hash)` — public static, line 24
  - `passwordNeedsRehash($hash)` — public static, line 38
  - `encryptionKey()` — private static, line 42
  - `encrypt($data)` — public static, line 47
  - `decrypt($data)` — public static, line 54
  - `generateCSRFToken()` — public static, line 69
  - `validateCSRFToken($token)` — public static, line 76
  - `requireCSRFToken($token)` — public static, line 80
  - `getClientIP()` — public static, line 87
  - `generateDeviceHash()` — public static, line 92
  - `validateLicenseFormat($key)` — public static, line 103
  - `validateAPIKey($apiKey)` — public static, line 107
  - `checkRateLimit($ip, $endpoint, $limit = 100)` — public static, line 119
- Request handling: POST=no; CSRF mentions=10; auth mentions=0

## `includes/validation.php`
- Classes: `Validation`
- Functions/methods:
  - `validateLicenseData($data)` — public, line 6
  - `validateLoginData($data)` — public, line 21
  - `validateAPIRequest($data)` — public, line 40
  - `getErrors()` — public, line 53
  - `getError($field)` — public, line 58
  - `getFormattedErrors()` — public, line 63
- Request handling: POST=no; CSRF mentions=0; auth mentions=0

## `index.php`
- Classes: none
- Functions/methods: none
- Request handling: POST=no; CSRF mentions=0; auth mentions=0

## `install.php`
- Classes: none
- Functions/methods:
  - `installer_dsn($host, $dbName = '')` — global, line 11
- Request handling: POST=yes; CSRF mentions=3; auth mentions=0
