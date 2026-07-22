# Troubleshooting

## Database connection error

Confirm `LICENSE_DB_HOST`, `LICENSE_DB_NAME`, `LICENSE_DB_USER`, and `LICENSE_DB_PASS`; verify `pdo_mysql`; test the database user from the same host; inspect private PHP logs.

## Installer cannot write configuration

Make `includes/` writable only for the installation step, then restore restrictive ownership and permissions. Do not make the full project world-writable.

## Admin login fails after import

For the sanitized schema, use the temporary local credentials and change them immediately. Confirm the `role` column exists and the database import reached the additive migration section.

## API key is rejected

Use `X-API-Key`, remove whitespace, confirm the key is active and not expired, and ensure the stored hash corresponds to the original plaintext key. Bearer parsing is a known issue in this release.

## License is valid but verification fails

Check status, expiry, blacklist entries, device limit, direct `api_key_id` binding, and `app_scope` versus the API key's `app_name` or `scope_label`.

## Scoped license fails through simple endpoint

The simple endpoint supplies no API-key context. Use the full verification endpoint.

## Device limit is reached

The current code blocks a new device rather than automatically logging out the oldest one. Revoke/clear an existing device from the admin panel or increase the limit.

## Cron returns database errors

Run it from the project context with the same environment variables as the web process. Confirm CLI PHP has `pdo_mysql` and access to private configuration.

## Protected files are visible

Apache may not be honoring `.htaccess`, or the server may be Nginx. Apply explicit deny rules from [SECURITY_DEPLOYMENT.md](SECURITY_DEPLOYMENT.md) immediately.
