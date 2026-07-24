# Licora Phase 2.1 Quality Improvement Summary

## Release identity

- Current stable version: `v5.1.0`
- Target version: `v5.1.1`
- Base commit: `372fb35f602b16f6dd684b59a36114b7b941c3fe`
- Branch: `feature/v5.1.1-quality-stability`
- Mode: Zero Freedom Development

## Quality improvements

| Area | Improvement | Compatibility effect |
|---|---|---|
| Version | Runtime and installer metadata updated to v5.1.1; preserved v5.1.0 private configuration can no longer pin the upgraded runtime version | Environment overrides remain supported |
| Error handling | Generic public exception responses in every environment | Existing production error JSON preserved |
| Installer errors | Safe allowlisted error mapping | No database, credential, SQL, or path disclosure |
| Permissions | Absolute server path removed from requirements screen | Requirement behavior unchanged |
| Base URL | Credentials, query, and fragment rejected | HTTP/HTTPS subdirectory deployments preserved |
| Mail name | CR/LF rejected | Existing field and normal values preserved |
| Secrets | Installer-generated 64-hex secrets verified | Legacy configuration acceptance unchanged |
| Testing | Dedicated quality/stability suite added | No runtime business behavior changed |
| Documentation | Installation, upgrade, troubleshooting, security, FAQ, and matrix updated | Deployment guidance only |

## Files intentionally unchanged

- `database.sql` and every migration SQL file
- `includes/functions.php`, `includes/security.php`, and `includes/auth.php`
- Primary and legacy API endpoints
- Every admin route and cron entry point
- CSS and JavaScript assets

## Database changes

None.

## API changes

None.

## License-engine changes

None.

## UI changes

None.

## Regression status

The update package runs syntax, compatibility, security, installer, and quality/stability tests before it permits a commit. Browser and database-backed release validation remains mandatory before merge and tag.

## Known limitations

- Nginx and LiteSpeed deny rules remain operator-managed.
- Web-server and database runtime results depend on hosting modules, permissions, and SQL privileges.
- PHP 8.1–8.3 are the target matrix; CI retains PHP 8.0 and 8.4 compatibility checks.
- No dedicated upload, cache, or storage directory exists in the frozen repository contract, so none is introduced.
- The legacy API remains available unchanged for compatibility.
