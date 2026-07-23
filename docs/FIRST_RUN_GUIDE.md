# First-Run Guide

## Before opening the installer

- Use a private staging environment.
- Confirm PHP 8.0+ and required extensions.
- Create a database account limited to the intended database.
- Make `includes/` writable for the web process temporarily.
- Confirm HTTPS before production exposure.

## Wizard steps

### 1. Welcome and checks

Required checks must show PASS. HTTPS may show WARNING in local development but is mandatory for production.

### 2. Database

Enter host, port, database name, username, and password. Leave Table Prefix blank. The wizard validates server connectivity without writing partial configuration.

### 3. Administrator

Enter name, email, username, and a strong password. The first user is created with the existing `super_admin` role. No role or permission table is added.

### 4. Application

Set application name, timezone, locale, base URL, and mail-from name. The wizard generates application, encryption, CSRF, and JWT secrets. Values are never displayed.

### 5. Database initialization

Review the target. The database must not already contain Licora tables. The unchanged repository schema is the only initialization source.

### 6. Demo data

Leave unchecked for production. When selected, records are created only in existing tables and clearly marked DEMO.

### 7. Installation lock

Confirm that installer execution will be disabled after completion. Installer files remain on disk.

### 8. Finalize

The wizard initializes the schema, replaces the temporary seeded administrator, writes private configuration, and activates the lock.

### 9. Success

Review version, username, application URL, admin URL, and API URL. Passwords and tokens are not shown.

### 10. Login redirect

The browser redirects to the admin login. Licora does not auto-login.

## After first login

- Verify the administrator email and role.
- Enable HTTPS and secure cookies.
- Restrict access to private configuration and cron paths.
- Configure cron.
- Test API authentication and license verification.
- Test backups and restoration.
- Remove optional demo data before production.

## Recovery

The installer is intentionally locked when either private configuration or the installation flag exists.

For a deliberate recovery:

1. Make the site private.
2. Back up the database and all private configuration/key files.
3. Diagnose the deployment before changing any lock file.
4. Never point the installer at a production database containing Licora tables.
5. Restore the lock and verify normal boot before reopening access.

A temporary database outage is not an installation failure and must be handled through the existing database recovery process.
