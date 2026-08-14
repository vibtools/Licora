# Maintenance Guide

## Daily or continuous

- Monitor verification failures, unusual request rates, and admin login failures.
- Confirm database and disk health.
- Review the Super Admin **Updates** badge/status; automatic checks are cached and do not auto-install releases.
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

## In-app updater operations

From v5.3.0 onward, prefer **Admin → Updates** for compatible signed releases. Run Preflight before Install, keep the live deployment log open when convenient, and retain the generated diagnostics/rollback backup until the new release is accepted. If the browser disconnects, reopening Updates resumes the persistent job; do not manually overwrite source files while a job is `running` or `rollback_running`.

The updater's critical lock is intentionally temporary and filesystem-based. If an orphaned lock points to a missing or terminal job, authenticated updater bootstrap removes it; a genuinely running job is never stolen simply because it has been paused for a long time.
