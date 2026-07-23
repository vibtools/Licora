# Installer Architecture

## Scope

The v5.1.0 installer is an independent wrapper around the existing Licora installation process. It does not change the license engine, license validation, API contracts, admin panel, schema, migrations, or existing routes.

## Request flow

```text
HTTP request
    |
    v
includes/config.php
    |
    v
Installation guard
    |-- no configuration ----------------> /install
    |-- incomplete fresh schema ----------> /install
    |-- valid legacy install --------------> backfill flag -> normal boot
    |-- valid flagged install -------------> normal boot
    `-- configured database outage --------> existing database error flow
```

The guard is bypassed for CLI execution and installer routes.

## Preserved and additive routes

- Preserved: `/install.php`
- Added alias: `/install` through `install/index.php`

No route is renamed or removed.

## Ten-step wizard

1. Welcome and server requirements
2. Database configuration and connection validation
3. Administrator setup
4. Application configuration and secret generation
5. Existing schema initialization review
6. Optional DEMO data selection
7. Installation-lock confirmation
8. Atomic finalization
9. Success screen
10. Redirect to admin login without auto-login

The wizard stores in-progress data in the server-side session. Administrator passwords are converted to a password hash before being stored in wizard state. Raw generated API credentials are never displayed.

## Atomic finalization

```text
Validate all state
    -> connect to server
    -> create/select target database
    -> snapshot pre-existing tables
    -> write temporary private config
    -> write temporary lock
    -> parse and execute unchanged database.sql
    -> replace temporary seeded administrator
    -> insert application settings
    -> optionally insert marked DEMO records
    -> atomically activate config.local.php
    -> atomically activate .licora-installed
    -> clear installer session
```

If installation fails before activation:

- A database created by the installer is removed when possible.
- In an existing target database, only tables created during the failed attempt are removed.
- Unrelated pre-existing tables are never removed.
- Temporary private files are deleted.
- Credentials and secrets are not logged.

## SQL execution

The installer parser supports the `DELIMITER` directives used by the existing schema triggers. It executes the repository's unchanged `database.sql`, including existing indexes, constraints, triggers, and additive migrations.

## Installation flag

`includes/.licora-installed` contains only:

```json
{
  "product": "Licora",
  "version": "5.1.0",
  "installed_at": "ISO-8601 timestamp"
}
```

No secrets are stored in the flag.

## Legacy installation compatibility

An older valid deployment may not have `APP_KEY`, `DB_PORT`, or an installation flag. Compatibility rules are:

- Existing usable encryption, CSRF, or JWT secrets qualify the legacy deployment as configured.
- Missing `DB_PORT` defaults to `3306`.
- Missing flag is backfilled only after database and required-table validation succeeds.
- Database outage never causes an installed deployment to reopen the installer.

## Table prefix

The wizard displays the requested optional field, but only a blank value is accepted. Existing schema and runtime queries use fixed table names, so non-empty prefixes would violate the compatibility contract.
