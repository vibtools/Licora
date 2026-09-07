# Dockerization Preparation Plan

To successfully migrate Licora to a Coolify/Docker environment without modifying its source code or sacrificing backward compatibility, the following Dockerization plan must be executed.

## 1. Goal
Create a standalone `Dockerfile` (Option B) that packages Licora with PHP-FPM, Nginx, and Supervisord (for cron), converting stateful file-based operations into stateless environment-variable-driven operations.

## 2. Component Strategy

### Web Server (Nginx)
- **Role:** Reverse proxy and static file server.
- **Action:** Write a custom `nginx.conf`.
- **Critical Requirements:** 
  - Route PHP requests to `php-fpm`.
  - Serve static assets directly.
  - Implement the **CRITICAL** security fix: explicitly deny access to `includes/`, `cron/`, `scripts/`, `.htaccess`, and sensitive file extensions (`.pem`, `.key`, `.env`, `.sql`).

### Application Server (PHP-FPM)
- **Role:** Executes Licora's core logic.
- **Action:** Base the Docker image on `php:8.2-fpm-alpine` (or similar).
- **Critical Requirements:**
  - Install required extensions: `pdo_mysql`, `openssl`, `json`.
  - Copy the source code to `/var/www/html`.
  - Set permissions correctly (`chown -R www-data:www-data`).

### Background Tasks (Supervisord or Cron)
- **Role:** Periodically execute maintenance scripts.
- **Action:** Install `supervisord` or `crond` in the container.
- **Critical Requirements:**
  - Define a crontab that runs `php /var/www/html/cron/check_expiring.php` and `cleanup.php`.

## 3. Handling State & Secrets (The Environment Variable Strategy)

Because Licora's `includes/config.php` utilizes `env_value()`, we can completely bypass the interactive web installer and the need for a writable `includes/` directory.

- **Action:** Document the required environment variables in Coolify.
- **Action:** Instead of allowing the app to generate `.licora-v2-signing-private.pem` locally, we will inject the key pair via Docker Secrets, Coolify Secrets, or mounted volumes, and map them using the `LICENSE_V2_SIGNING_PRIVATE_KEY_PATH` variable.
- **Action:** Supply a pre-generated `APP_KEY`, `ENCRYPTION_KEY`, `CSRF_SECRET`, and `JWT_SECRET` via Coolify environment variables.

## 4. Disabling the Updater
Since Docker containers are immutable, allowing the application to download and overwrite its own source code will result in ephemeral changes that disappear on the next deploy.

- **Action:** Map or define an environment variable (e.g., `LICORA_UPDATE_CHECK_INTERVAL=0`) to silently disable update checks, or simply rely on the fact that the container filesystem will be read-only/ephemeral for core files, causing the updater to fail gracefully if invoked.

## 5. Next Steps for Execution
1. Create `docker/nginx.conf`.
2. Create `docker/entrypoint.sh` (to handle initial database migrations/seeding if needed via `database.sql`, or rely on a one-time init script).
3. Create `Dockerfile`.
4. Create `.dockerignore`.
