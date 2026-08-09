# Licora API v2 Migration

## Existing v5.1.0 deployment

1. Back up the database and current private configuration.
2. Deploy the current v5.2.1 source while preserving `includes/config.local.php`, `.licora-encryption.key`, installation state and operational data.
3. Initialize/verify API v2 using one of the equivalent provisioning paths:

   - cPanel/shared hosting without shell access: **Admin → Client Apps → Initialize API v2**.
   - CLI-capable hosting: `php scripts/setup-v2.php`.

4. Confirm Admin → Client Apps reports the schema and signing key pair ready.
5. Register the required App ID(s).
6. Assign new or selected licenses to the matching API v2 app scope.
7. Integrate clients against API v2 only after the server-side deployment is verified.

`migration-v5.2.0-api-v2.sql` is additive. It creates only the five API v2 tables and does not drop, rename, or replace API v1 schema objects.

## Fresh installation

The v5.2.1 `database.sql` retains the same additive API v2 schema introduced in v5.2.0. The first-run installer remains the schema executor and generates the deployment-specific RSA signing key pair during successful fresh installation. Existing deployments use the shared `V2Provisioner` through either the authenticated Client Apps action or `scripts/setup-v2.php`. No v5.2.1 database migration is required.

## Rollback

API v1 remains operational and unchanged. If API v2 must be disabled before clients depend on it, disable the affected Client App(s) and stop routing `/api/v2/`. Do not delete v2 tables or signing keys as an emergency rollback step; retain them for forensic/recovery consistency.
