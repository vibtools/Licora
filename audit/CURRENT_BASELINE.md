# Current Baseline: Licora v5.8.4

## 1. Current Architecture
- **Language/Runtime:** PHP 8.0+
- **Pattern:** Direct script execution (no front-controller / framework router). Web requests directly target `index.php`, `admin/login.php`, `api/verify.php`, `api/v2/activate.php`, etc.
- **Database:** MySQL/MariaDB accessed via pure PDO abstraction (`includes/database.php`). Heavy use of SQL schemas, triggers, and foreign key constraints.
- **Authentication (Admin):** Session-based with Bcrypt password hashing.
- **Authentication (API):** 
  - V1: Bearer token or custom header (`X-API-Key`).
  - V2: Advanced cryptographic signatures using RSA keypairs and short-lived/long-lived tokens (JWT style without full JWT standard).
- **State Management:** Highly stateful.
  - Configuration is persisted to `includes/config.local.php`.
  - Cryptographic keys (`.licora-encryption.key`, `.licora-v2-signing-private.pem`) are generated on the filesystem.
  - Installer state is tracked via `includes/.licora-installed`.
- **In-App Updater:** The application has a self-updating mechanism (`includes/updater/`) that pulls from GitHub and overwrites files on the disk directly.

## 2. Current Files (Key Structures)
- `admin/`: Admin UI, styles, and AJAX controllers.
- `api/`: Public API entry points (`verify.php`, `v2/activate.php`).
- `cron/`: Maintenance scripts designed to run periodically (`check_expiring.php`, `cleanup.php`).
- `includes/`: Core classes, functions, security logic, config files, and the updater engine.
- `install/`: Directory holding the installation wizard.
- `database.sql`: The frozen base schema combined with additive migration SQL.
- `config.sample.php`: Base configuration template.
- `api/admin/v1/`: Dedicated, HMAC-authenticated server-to-server license/device control endpoints.
- `includes/admin_api/`: Admin API request validation, authorization, ownership and lifecycle services.
- `migration-v5.8.3-admin-license-api.sql`: Additive Admin API persistence migration.
- `SDK/python/`: Optional production Python Secure API v2 public-client package.

## 3. Current Behavior
- **Initialization:** An unconfigured application redirects to the installer. The installer sets up the database, creates the first admin user, generates cryptographic keys in the `includes/` directory, and locks itself.
- **API Flow:** 
  - V1 requests check rate limits via MySQL locks (`GET_LOCK`), then validate the provided API key against the database, check the license, log the usage, and return JSON.
  - V2 requests validate RSA signatures, process activation/deactivation, and issue ephemeral access tokens.
- **Admin Flow:** Admins authenticate, triggering session variables. Rate limiting blocks brute-force login attempts (15-minute IP lock on 5 failures).
- **Admin License API Flow:** Dedicated hashed keys are constrained by exact existing applications, granular scopes, optional IP/CIDR, rate and expiry policies. Signed timestamp/nonce requests create idempotent order-owned licenses or manage only those licenses/devices.
- **Python SDK Flow:** Public configuration selects an existing App ID and pins the Licora signing key. The SDK owns device proof, token verification, secure state, recovery and background validation without changing the server protocol.
- **Updater Flow:** The background script or admin panel triggers an update check. If available, it downloads a package, validates it, and replaces running code.

## 4. Features That Must Not Change
- **API backward compatibility:** Existing client applications relying on `api/verify.php` (V1) or `api/v2/activate.php` (V2) must continue functioning without changes.
- **Database Schema:** The frozen V4 database schema and V5 additive migrations must not be altered, removed, or refactored.
- **Security Primitives:** Cryptographic operations (AES-256-CBC encryption, RSA signatures) must continue to work exactly as they do to maintain data integrity.
- **Core Functionality:** All existing license verification, device locking, administration, and rate-limiting logic must remain intact.
