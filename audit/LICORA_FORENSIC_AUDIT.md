# Licora Final Audit Report

## Executive Summary
A comprehensive forensic audit of the Licora licensing system was conducted to determine its readiness for production deployment on Coolify. Licora is a robust, highly stateful PHP monolith utilizing direct file routing and a relational database. While application-level security (SQLi, XSS, Authentication) is strong, its architecture—specifically its reliance on `.htaccess` for file protection and an in-app updater that mutates local source code—presents critical security and architectural conflicts when migrating to modern, immutable container environments like Coolify. This audit outlines the path forward without modifying the underlying source code.

## Architecture Review
- **Language/Runtime:** PHP 8.0+.
- **Pattern:** Legacy direct file execution (no unified front-controller routing).
- **Core Components:** 
  - `admin/`: UI and administrative endpoints.
  - `api/`: V1 legacy endpoints and V2 RSA-signed API endpoints.
  - `cron/`: Scheduled maintenance tasks.
  - `includes/`: Core logic, security wrappers, database abstraction, and the in-app updater.
- **Statefulness:** The system generates and stores cryptographic material (`.licora-encryption.key`, `.licora-v2-signing-private.pem`) and configuration state (`.licora-installed`, `config.local.php`) on the local filesystem.

## Security Review
- **Application Security:** High. PDO is utilized universally to prevent SQL injection. CSRF protection is in place. Passwords use Bcrypt.
- **Rate Limiting:** Implemented at the database level using MySQL advisory locks (`GET_LOCK`).
- **[CRITICAL RISK] Nginx Configuration Bypass:** The application relies on Apache `.htaccess` to block access to the `includes/` directory. Nginx ignores `.htaccess`, meaning a default Nginx configuration will serve the private encryption and signing keys as static text, compromising the entire system.
- **[MEDIUM RISK] Public Cron:** Maintenance scripts in `cron/` are vulnerable to external HTTP triggering.

## Database Review
- **Engine:** MySQL/MariaDB (Requires `utf8mb4`).
- **Structure:** Heavily relational with foreign keys and database-level triggers (e.g., updating timestamps).
- **Initialization:** Created from a frozen `database.sql` file via the web installer.
- **Constraint:** The database user **MUST** have the `TRIGGER` privilege.

## Deployment Review
- **Required Extensions:** `pdo`, `pdo_mysql`, `openssl`, `json`, `curl`.
- **Environment Parity:** The application fully supports reading configuration and secrets via environment variables (e.g., `LICENSE_DB_HOST`, `LICENSE_APP_KEY`), which provides a clean pathway to bypass the file-generating web installer.

## Coolify Compatibility Review
- **Automatic Nixpacks (Option A):** **DANGEROUS.** Nixpacks uses Nginx by default without custom routing rules, exposing the critical private keys to the public internet. It also provides an ephemeral filesystem, breaking the in-app updater and locally generated configuration.
- **Dockerfile Deployment (Option B):** **HIGHLY RECOMMENDED.** Creating a custom `Dockerfile` allows us to enforce strict Nginx security rules, package a cron daemon, inject secrets via environment variables, and enforce container immutability by bypassing the built-in updater.

## Risks
1. **Key Exposure:** Deploying behind standard Nginx without custom location blocks will leak RSA and AES keys.
2. **Immutability Violation:** The in-app updater requires write access to the source code, which conflicts with Docker best practices.
3. **Trigger Privileges:** If the Coolify managed database restricts `TRIGGER` creation, the database initialization will fail.

## Recommendations
1. **Adopt Option B:** Proceed with a custom Dockerfile deployment.
2. **Nginx Hardening:** Implement strict `location` rules denying access to `includes/`, `cron/`, `scripts/`, and all sensitive extensions (`.pem`, `.key`, `.env`, `.sql`).
3. **Environment Variable Injection:** Generate all required secrets externally (Coolify Secrets) and map them to the corresponding `LICENSE_*` environment variables.
4. **Disable Updater:** Rely on Docker image rebuilds for future updates; ignore the built-in updater.

## Dockerization Preparation Plan
1. **Create `docker/nginx.conf`** with strict security rules and PHP-FPM routing.
2. **Create `docker/entrypoint.sh`** to handle startup tasks (if any) and start Supervisor.
3. **Create `docker/supervisord.conf`** to manage PHP-FPM, Nginx, and the cron daemon simultaneously.
4. **Create `Dockerfile`** using a multi-stage approach or standard `php:8.2-fpm-alpine` image to package the application securely.
