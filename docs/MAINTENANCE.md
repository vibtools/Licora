# Maintenance Guide

## Daily or continuous

- Monitor verification failures, unusual request rates, and admin login failures.
- Confirm database and disk health.
- Protect and rotate application logs.

## Scheduled

- Run `cron/cleanup.php` at an interval appropriate to device activity.
- Run `cron/check_expiring.php` daily if expiry reporting is needed.
- Create encrypted backups and verify completion.

## Monthly

- Restore a backup in isolation.
- Review admin accounts and roles.
- Review active API keys, expiry, and request counts.
- Review blacklist entries and retention settings.
- Check PHP, database, web-server, and CDN dependency advisories.

## Before upgrades

- Take a backup.
- Read every migration.
- Test against a clone.
- Record rollback commands.
- Confirm older clients continue to use existing endpoint contracts.

## Data retention

The cleanup script uses `log_retention_days` for general logs but not every audit/API table. Define separate retention controls at the database or application level until the runtime supports them directly.
