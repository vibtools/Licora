# Licora API v2 Migration

## Existing v5.1.0 deployment

1. Back up the database and current private configuration.
2. Deploy the v5.2.0 source while preserving `includes/config.local.php`, `.licora-encryption.key`, installation state and operational data.
3. Run:

```bash
php scripts/setup-v2.php
```

4. Confirm Admin → Client Apps loads without a migration warning.
5. Register the required App ID(s).
6. Assign new or selected licenses to the matching API v2 app scope.
7. Integrate clients against API v2 only after the server-side deployment is verified.

`migration-v5.2.0-api-v2.sql` is additive. It creates only the five API v2 tables and does not drop, rename, or replace API v1 schema objects.

## Fresh installation

The v5.2.0 `database.sql` includes the same additive API v2 schema. The existing first-run installer remains the schema executor and generates the deployment-specific RSA signing key pair during successful fresh installation. `scripts/setup-v2.php` is for existing deployments upgrading from v5.1.0, or for explicit setup verification when the v2 schema/keys are not yet provisioned.

## Rollback

API v1 remains operational and unchanged. If API v2 must be disabled before clients depend on it, disable the affected Client App(s) and stop routing `/api/v2/`. Do not delete v2 tables or signing keys as an emergency rollback step; retain them for forensic/recovery consistency.
