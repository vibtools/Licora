# Licora Coolify Production Deployment Guide

## 1. Server Requirements
- A server running [Coolify](https://coolify.io).
- At least 1GB RAM (2GB recommended for production).
- A connected domain name pointing to the server.

## 2. Docker Architecture Summary
This deployment uses a **Multi-Container Architecture (Option B)** for the following reasons:
- **Security:** Isolates the database, application code, and web server into separate environments. Strict Nginx rules prevent access to private keys and configuration files.
- **Maintainability:** Using standard `php-fpm` and `nginx` images makes upgrading underlying components simple.
- **Scalability:** The PHP application can be scaled independently of the Nginx proxy and MariaDB database.
- **Coolify Compatibility:** Docker Compose is a first-class citizen in Coolify and natively supports persistent volumes and environment variable injection.

**Services Created:**
1. `app`: PHP 8.2 FPM container running the Licora backend.
2. `web`: Nginx 1.25 Alpine reverse proxy handling static files and forwarding PHP requests to `app`.
3. `db`: MariaDB 10.11 database with automatic schema initialization.
4. `cron`: A background worker running periodic maintenance tasks (`check_expiring.php`, `cleanup.php`).

## 3. Coolify Setup Steps
1. In Coolify, create a new **Project** and select **Docker Compose**.
2. Connect your Git repository containing the Licora source code.
3. Coolify will automatically parse the `docker-compose.yml` file.
4. Set the Domains for the `web` service to your public domain (e.g., `https://licora.example.com`).
5. Configure the Environment Variables (see below).
6. Click **Deploy**.

## 4. Environment Variables
You must define the following Environment Variables in the Coolify dashboard for the deployment to succeed. **Never hardcode these in version control.**

```env
APP_ENV=production
APP_URL=https://licora.yourdomain.com

# Database Connection
LICENSE_DB_HOST=db
LICENSE_DB_PORT=3306
LICENSE_DB_NAME=licora_prod
LICENSE_DB_USER=licora_user
LICENSE_DB_PASS=generate_a_secure_password_here
MARIADB_ROOT_PASSWORD=generate_a_secure_root_password_here

# Cryptographic Secrets (Generate random secure strings for these)
LICENSE_APP_KEY=your_64_character_random_string_here
LICENSE_ENCRYPTION_KEY=your_32_character_random_string_here
LICENSE_CSRF_SECRET=your_random_csrf_secret_here
LICENSE_JWT_SECRET=your_random_jwt_secret_here
```
*Note: The API V2 RSA Keypair (`private.pem`, `public.pem`) is automatically generated into a persistent volume on first startup if it does not exist.*

## 5. Database Setup
The `db` service utilizes the official MariaDB image. On the very first startup, it will automatically execute `database.sql` to initialize the tables. Because the database is pre-initialized, the Licora installer UI is safely bypassed.

## 6. Domain & SSL Setup
Coolify manages SSL certificates automatically via Let's Encrypt or Traefik/Caddy. Ensure your domain's DNS A-record points to your Coolify server's IP address and that the Domain is correctly mapped to the `web` service in the Coolify UI.

## 7. Backup Strategy
- **Database:** Configure Coolify's built-in scheduled backups for the `db` service.
- **Keys:** The V2 RSA keys are stored in the `keys_volume` Docker volume. Ensure this volume is backed up, or manually export the keys from the container to store securely in a password manager. If lost, existing V2 API clients will fail signature verification until updated.

## 8. Update Procedure
The built-in Licora web updater (`includes/updater/`) has been explicitly disabled to preserve container immutability. To update Licora:
1. Push the new Licora code to your Git repository.
2. Coolify will automatically trigger a new Docker build and perform a rolling deployment.
3. If database migrations are required, run them manually via the container shell or an entrypoint script.

## 9. Rollback Procedure
If an update fails:
1. In Coolify, navigate to the Deployment History.
2. Select the previous successful deployment and click "Redeploy".
3. If the database schema was modified, restore the database from the automated Coolify backup.

## 10. Troubleshooting
- **Database Connection Error:** Verify `LICENSE_DB_PASS` matches `MARIADB_PASSWORD` and `LICENSE_DB_USER` matches `MARIADB_USER`.
- **502 Bad Gateway:** Ensure the `app` service is running and successfully started PHP-FPM. Check the `app` container logs for PHP syntax errors.
- **API V2 Signature Errors:** Ensure the `keys_volume` is properly mounted and both `app` and `cron` services have access to it. You can check keys using `docker exec <container_id> ls -l /var/www/keys/`.
- **Cron Not Running:** Check the logs of the `cron` service in Coolify to ensure `crond` started successfully without permission issues.
