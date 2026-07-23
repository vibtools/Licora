# Compatibility Matrix

## Status terminology

- **Automated:** Covered by repository or CI tests.
- **Manual:** Must be confirmed in a disposable deployment before release.
- **Configuration review:** Server rules remain operator-managed.

## PHP

| Runtime | Status | Notes |
|---|---|---|
| PHP 8.0 | Automated compatibility retention | Existing support retained |
| PHP 8.1 | Automated target | Phase 2.1 target |
| PHP 8.2 | Automated target and local XAMPP coverage | Phase 2.1 target |
| PHP 8.3 | Automated target | Phase 2.1 target |
| PHP 8.4 | Automated forward-compatibility check | Not the minimum target |

Required extensions: `pdo`, `pdo_mysql`, `openssl`, and `json`.

## Web servers

| Server | Status | Notes |
|---|---|---|
| Apache | Manual | Included `.htaccess` |
| Nginx | Configuration review + manual | Reproduce deny rules |
| LiteSpeed/OpenLiteSpeed | Configuration review + manual | Verify `.htaccess` compatibility or equivalent rules |

## Databases

| Database | Status | Notes |
|---|---|---|
| MariaDB | Manual | Verify schema, triggers, and advisory locks |
| MySQL | Manual | Verify schema, triggers, and advisory locks |

## Installation scenarios

| Scenario | Automated coverage | Manual release gate |
|---|---|---|
| Fresh production installation | Validation helpers | Required |
| Fresh DEMO installation | Demo helper and smoke markers | Required |
| Existing installation | Lock and compatibility tests | Required |
| Interrupted installation | Cleanup/static assertions | Required |
| Invalid database credentials | Safe error contract | Required |
| Invalid administrator credentials | Validation tests | Required |
| Read-only includes directory | Requirement test | Required |
| Existing lock file | Lock decision tests | Required |
| Legacy upgrade path | Compatibility assertions | Required |
