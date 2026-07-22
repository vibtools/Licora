# Change Report

## Purpose

Prepare the uploaded License Management System for a professional public GitHub release while preserving existing application behavior.

## Behavior-preserving source changes

1. `includes/config.php`
   - Replaced deployment-specific `APP_URL` fallback with `http://localhost`.
   - Environment override behavior is unchanged.
2. `database.sql`
   - Preserved the schema and additive compatibility statements.
   - Removed 2,746 deployment rows containing operational/private data.
   - Added a temporary local administrator seed and neutral local API URL.
3. `cron/.htaccess`
   - Added Apache deny rules for direct web requests to CLI maintenance scripts.

## Cleanup

Removed generated lint reports and the non-executable `licensesystem` tree-note. No runtime PHP, JavaScript, CSS, SQL migration, or feature page was removed.

## Repository engineering added

- MIT license and trademark/brand notice.
- Professional README, architecture, installation, API, database, configuration, development, maintenance, release, security, migration, troubleshooting, and coding-standard documentation.
- Contributing, security policy, code of conduct, support, roadmap, and repository metadata.
- GitHub Actions CI across PHP 8.0–8.4.
- Dependabot configuration for GitHub Actions.
- Pull-request template and structured issue forms.
- Security smoke test, validation script, and deterministic release packaging script.
- Original archive hash, complete file inventory, PHP symbol inventory, static audit JSON, unified diff, and validation evidence.

## Contract intentionally not changed

- Endpoint paths, HTTP methods, request keys, and response keys.
- License generation and verification logic.
- API-key binding and scope behavior.
- Device-limit enforcement behavior.
- Admin roles and page-level permissions.
- Database table/column names and historical migration files.
- UI page implementation and existing local assets.
