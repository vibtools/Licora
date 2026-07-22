# Database

## Public schema

`database.sql` is a sanitized public schema. It contains no operational license keys, API keys, encrypted key copies, devices, IP addresses, request logs, or production administrative activity.

## Tables

| Table | Purpose |
|---|---|
| `admin_users` | Admin identity, password hash, role, lockout, and login metadata. |
| `api_keys` | API-key hashes, optional legacy encrypted copies, scope metadata, activation, expiry, and counters. |
| `api_logs` | Verification request metadata and response codes. |
| `blacklist` | License, device, or IP deny entries. |
| `devices` | License-bound device hashes, client metadata, OS/browser labels, and activity. |
| `failed_logins` | IP-based failed admin login history. |
| `licenses` | License keys, encrypted copies, ownership, scope/binding, expiry, device limits, and status. |
| `logs` | General operational and admin action history. |
| `rate_limits` | IP/endpoint request counters. |
| `settings` | Admin-configurable key/value settings. |
| `audit_trail` | Structured entity-level audit events. |

## Relationships

- `api_keys.user_id` → `admin_users.id`
- `api_logs.api_key_id` → `api_keys.id`
- `blacklist.banned_by` → `admin_users.id`
- `devices.license_id` → `licenses.id`
- `licenses.created_by` → `admin_users.id`
- `logs.license_id` → `licenses.id`
- `logs.admin_id` → `admin_users.id`

## Seed data

The public schema seeds configuration defaults and one temporary local admin. It intentionally contains no sample licenses or API keys.

## Backup handling

Admin SQL exports contain sensitive operational data. Encrypt backups at rest, restrict access, test restoration in an isolated database, and define retention. Never commit backups to Git.

## Schema observations

The historical dump includes additive migrations at the end, redundant license-key indexes, and overlapping migration files. These are preserved for compatibility. Normalize them only through a versioned migration with a verified rollback.
