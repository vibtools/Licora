# Frequently Asked Questions

## Does v5.1.1 require a database migration?

No. The schema and every migration file remain unchanged.

## Should an existing v5.1.0 installation run the installer?

No. Preserve private configuration, the installation flag, and encryption-key material. Replace source files and follow `UPGRADE_GUIDE.md`.

## Why does the installer reject a URL containing `?query` or `#fragment`?

The Base URL represents the application root. Query parameters and fragments are request-specific and can retain tokens or produce invalid generated URLs.

## Are subdirectory installations supported?

Yes. Values such as `https://example.com/licora` remain valid.

## Why is an absolute directory path no longer shown?

Production paths reveal server layout. The installer reports only whether the required location is writable.

## Does v5.1.1 change API JSON or license keys?

No. Primary and legacy API contracts, license format, generation, validation, encryption compatibility, and device behavior remain unchanged.

## Which PHP versions are targeted?

PHP 8.1, 8.2, and 8.3. CI also retains PHP 8.0 and 8.4 checks.

## Are Apache, Nginx, and LiteSpeed supported?

The PHP application is server-agnostic. Apache uses the included `.htaccess` rules. Nginx and LiteSpeed require equivalent deny rules documented in the deployment guides.

## Which database servers are supported?

Licora uses PDO MySQL and is designed for MySQL and MariaDB.

## What directories must be writable?

For a fresh wizard installation, `includes/` must be writable temporarily. Licora currently defines no separate upload, cache, or storage directory.

## Can I delete historical logs during upgrade?

No. v5.1.1 does not delete or rewrite historical application, API, authentication, license, or audit logs.
