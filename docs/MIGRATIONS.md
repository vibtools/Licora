# Migrations

The repository includes historical additive SQL files:

1. `migration.sql` — removes reversible stored API-key copies.
2. `migration-v4.sql` — roles, API metadata, application scope, audit trail, and settings.
3. `migration-v5.sql` — API/application binding additions and settings.
4. `migration-v5-fix.sql` — idempotent compatibility columns and settings.
5. `migration-v5-hotfix.sql` — additional binding compatibility.
6. `migration-v5.2.0-api-v2.sql` — additive Secure API v2 tables.
7. `migration-v5.3.0-updater.sql` — additive updater job/event/migration-ledger tables and updater settings.

`database.sql` already incorporates the historical schema and additive changes for a new installation. Existing deployments should inspect their current columns before selecting migrations.

## Procedure

1. Back up the database.
2. Test the migration against a clone.
3. Apply one file at a time.
4. Inspect warnings and schema state.
5. Run the admin pages that call `ensureV5Schema()`.
6. Verify old and new license behavior.
7. Keep a rollback script for every destructive change.

The supplied historical migrations do not include formal down migrations.

## v5.3.0 automatic migration ledger

The Secure Update Center introduces `app_migrations`. Future updater-managed migrations are listed in the signed release manifest with a unique ID and checksum. Non-destructive migrations must be explicitly idempotent; destructive migrations require a signed rollback path and database safety backup. The updater will not blindly replay an already applied migration with a matching checksum and rejects reuse of a migration ID with a different checksum. Historical pre-v5.3.0 migrations remain documentation/upgrade artifacts and are not retroactively inserted into the ledger.
