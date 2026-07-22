# Migrations

The repository includes historical additive SQL files:

1. `migration.sql` — removes reversible stored API-key copies.
2. `migration-v4.sql` — roles, API metadata, application scope, audit trail, and settings.
3. `migration-v5.sql` — API/application binding additions and settings.
4. `migration-v5-fix.sql` — idempotent compatibility columns and settings.
5. `migration-v5-hotfix.sql` — additional binding compatibility.

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
