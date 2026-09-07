# Licora Security Findings

A complete forensic security review was conducted on the Licora application architecture, primarily focusing on its deployment into a containerized/Coolify environment.

## 1. Executive Security Posture
Overall, the PHP codebase exhibits strong application-level security primitives:
- **SQL Injection:** Strongly mitigated by uniform use of PDO prepared statements across the codebase.
- **XSS:** Mitigated through `Security::escape()` (`htmlspecialchars`).
- **CSRF:** Implemented correctly using session tokens.
- **Authentication:** Follows best practices using Bcrypt for password hashing.
- **Brute Force Protection:** IP-based tracking via `failed_logins` with automatic account lockouts.

## 2. Forensic Findings

### [CRITICAL] Nginx Configuration Bypass Exposes Private Keys
- **Problem:** The application relies exclusively on an Apache `.htaccess` file to restrict web access to the `includes/` directory and sensitive files. Nginx (the default proxy in Coolify's Nixpacks) ignores `.htaccess`.
- **Affected File:** `.htaccess`, `includes/` directory.
- **Security Impact:** An attacker can directly request `https://example.com/includes/.licora-encryption.key` or `https://example.com/includes/.licora-v2-signing-private.pem`. Serving these files as static text results in the complete cryptographic compromise of the licensing system.
- **Recommended Fix:** Implement explicit Nginx `location` rules in the deployment configuration to deny all requests to `^/(includes|cron|scripts)/.*` and specifically block files matching `\.(pem|key|env|sql|lock|log|ini|sh)$`.

### [HIGH] Mutable Source Code via In-App Updater
- **Problem:** The `includes/updater/` logic pulls archives from GitHub and overwrites running PHP scripts directly on the filesystem.
- **Affected File:** `includes/updater/*`
- **Security Impact:** The web server process requires write access to the entire application root. If an unrelated Remote Code Execution (RCE) or arbitrary file upload vulnerability is ever discovered, the attacker immediately gains the ability to rewrite core logic or implant backdoors permanently. Furthermore, this violates container immutability.
- **Recommended Fix:** Explicitly disable the in-app updater in containerized/Docker deployments. Rely on Docker image rebuilds triggered by Coolify/CI to update the codebase.

### [MEDIUM] Publicly Accessible Cron Scripts
- **Problem:** The files in the `cron/` directory (`cleanup.php`, `check_expiring.php`) do not explicitly restrict execution to CLI-only.
- **Affected File:** `cron/*.php`
- **Security Impact:** A malicious actor could repeatedly request these URLs to trigger heavy database queries, potentially leading to a Denial of Service (DoS) or masking malicious activity.
- **Recommended Fix:** Add Nginx routing rules to block web access to the `cron/` directory. Cron jobs should be executed strictly via a CLI sidecar container or `supervisord`.

### [LOW] Rate Limiting Advisory Lock Fallback
- **Problem:** Rate limiting in `Security::checkRateLimit` attempts to acquire a MySQL advisory lock (`GET_LOCK`). If it fails, it falls back to a non-atomic `INSERT`/`UPDATE` process.
- **Affected File:** `includes/security.php`
- **Security Impact:** In a highly concurrent distributed attack, minor race conditions could allow a few extra requests to bypass the strict limit before the state converges.
- **Recommended Fix:** No immediate action required, but noted as a structural artifact. The fallback provides adequate baseline protection.
