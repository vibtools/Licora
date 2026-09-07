# Licora Backup and Recovery Strategy

## 1. Database Backup
The database contains all critical licensing data, audit logs, and configuration state.

- **Schedule:** Automated daily backups are strongly recommended.
- **Command:** Execute the following via Coolify's built-in scheduled tasks or directly on the host:
  ```bash
  docker exec <db_container_id> /usr/bin/mysqldump -u licora_user -p"licora_password" licora > /path/to/backups/licora_$(date +\%F).sql
  ```
  *Note: Replace credentials with your `LICENSE_DB_USER` and `LICENSE_DB_PASS`.*
- **Restore Procedure:**
  ```bash
  cat /path/to/backups/licora_YYYY-MM-DD.sql | docker exec -i <db_container_id> /usr/bin/mysql -u licora_user -p"licora_password" licora
  ```

## 2. Application Backup
The application logic is stateless in the container, but critical secrets exist in volumes and environment variables.

- **Configuration:** Back up the Coolify Environment Variables (e.g., `.env` string). If these are lost, encrypted database fields (like V1 API keys) cannot be decrypted.
- **Encryption Keys:** `LICENSE_ENCRYPTION_KEY`, `LICENSE_APP_KEY`, `LICENSE_JWT_SECRET`.
- **Signing Keys:** The API V2 RSA Keypair (`private.pem`, `public.pem`) stored in the `keys_volume`.
  - **Backup Command:** `docker cp <app_container_id>:/var/www/keys/ /path/to/backups/keys/`
- **Volumes:** `db_data` (handled by DB backup) and `keys_volume`.

## 3. Recovery Scenarios

### Scenario 1: Database Failure
- **Impact:** Licenses cannot be verified; admin cannot log in.
- **Recovery:** Create a fresh database container. Execute the Restore Procedure (Section 1) using the latest SQL dump. Ensure environment variables point to the new database.

### Scenario 2: Container Failure (App/Nginx)
- **Impact:** Service downtime.
- **Recovery:** Click "Restart" or "Redeploy" in Coolify. Since the containers are stateless (relying on DB and Environment Variables), they will recover immediately upon recreation.

### Scenario 3: Server Failure (Total Loss)
- **Impact:** Complete system offline.
- **Recovery:** 
  1. Spin up a new Coolify server.
  2. Reconnect the Git repository and input the backed-up Environment Variables.
  3. Deploy the application to initialize the empty database.
  4. Restore the SQL backup (Section 1).
  5. Restore the `/var/www/keys/` folder using `docker cp` to the new `app` container, then restart the `app` container so the new V2 keys are recognized.

### Scenario 4: Accidental Deletion of V2 Keys
- **Impact:** V2 API Signature verification will fail for all active client apps. They will be unable to activate or refresh tokens until they download a new client application with the new Public Key.
- **Recovery:** Restore `private.pem` and `public.pem` from backup to `/var/www/keys/` and restart the container.
