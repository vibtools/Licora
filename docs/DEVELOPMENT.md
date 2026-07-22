# Developer Guide

## Local setup

1. Clone the repository.
2. Import `database.sql` into a disposable database.
3. Copy `config.sample.php` to `includes/config.local.php` and replace placeholders.
4. Start a local PHP server only for development:

```bash
php -S 127.0.0.1:8080
```

5. Open `http://127.0.0.1:8080/admin/login.php`.

The PHP built-in server does not enforce Apache `.htaccess`; do not use it for production or expose protected files publicly.

## Core classes

- `Database`: PDO singleton.
- `Security`: escaping, password hashing, encryption, CSRF, IP/device helpers, API-key validation, and rate limiting.
- `Auth`: admin login, lockout, session metadata, role helpers, and audit logging.
- `LicenseSystem`: license lifecycle, device enforcement, blacklist checks, statistics, and risk scoring.
- `Validation`: admin and API input validation.
- `AdminHelpers`: schema compatibility, authorization helpers, audit writes, and CSV output.

## Change discipline

- Prefer `__DIR__` for new include paths.
- Use prepared statements for all values.
- Escape all HTML output with `Security::escape()`.
- Require CSRF tokens for state changes.
- Do not add new state-changing GET actions.
- Add additive migrations and rollback notes.
- Keep public error messages generic and log server details privately.

## Tests

```bash
php tests/security_smoke.php
bash scripts/validate.sh
```

Database-backed tests are not yet included. Use a disposable database and never run development tests against production data.
