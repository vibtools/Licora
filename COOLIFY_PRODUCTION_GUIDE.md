# Licora Coolify Production Guide

## Server Requirements
- Operating System: Ubuntu 22.04 LTS (Recommended) or Debian 12.
- Memory: Minimum 1GB RAM (2GB+ recommended for database stability).
- Management: Coolify version 4+ installed.

## Initial Deployment
1. Navigate to your Coolify Dashboard.
2. Create a new **Project** -> **Environment** -> **Add New Resource**.
3. Select **Docker Compose** -> **From Repository**.
4. Connect the Licora GitHub repository. Coolify will automatically parse the `docker-compose.yml` file.

## Environment Variables
Before clicking "Deploy", you **must** configure the environment variables in the Coolify UI. Failure to provide them will cause the deployment to fail due to strict requirement checks (`?Required`).

```env
APP_ENV=production
APP_URL=https://licora.yourdomain.com

# Database Connection
LICENSE_DB_NAME=licora_prod
LICENSE_DB_USER=licora_user
LICENSE_DB_PASS=generate_a_secure_password_here
MARIADB_ROOT_PASSWORD=generate_a_secure_root_password_here

# Cryptographic Secrets
LICENSE_APP_KEY=your_64_character_random_string_here
LICENSE_ENCRYPTION_KEY=your_32_character_random_string_here
LICENSE_CSRF_SECRET=your_random_csrf_secret_here
LICENSE_JWT_SECRET=your_random_jwt_secret_here
```

## Domain Setup
1. In the Coolify UI, navigate to the `web` service inside your new Docker Compose stack.
2. In the "Domains" field, enter your public URL (e.g., `https://licora.example.com`).

## SSL Setup
Coolify automatically provisions Let's Encrypt SSL certificates. Ensure your DNS A-record points to the Coolify server's IP address before assigning the domain to the `web` service.

## Database Initialization
The database initializes automatically. The `docker-compose.yml` maps `database.sql` to the `db` service's `/docker-entrypoint-initdb.d/init.sql`. On the first startup, MariaDB will execute this schema.
- **Result:** You completely skip the Web Installer, ensuring secure provisioning.

## First Admin Creation
The database schema includes a default admin user (`admin` / `ChangeMe!2026`).
1. Immediately upon successful deployment, navigate to `https://licora.yourdomain.com/admin/login.php`.
2. Login with username `admin` and password `ChangeMe!2026`.
3. Go to Settings -> Administrators and **immediately change this password**.

## Cron Setup
Cron is managed natively within the Docker Compose stack. A dedicated `cron` service runs the Alpine `dcron` daemon continuously in the foreground. It automatically executes:
- Hourly: `check_expiring.php` (Suspends expired licenses).
- Daily: `cleanup.php` (Removes old logs, clears expired tokens).

## Backup Setup
Please refer to [BACKUP_AND_RECOVERY.md](docs/BACKUP_AND_RECOVERY.md) for detailed commands.
- Setup Coolify Scheduled Backups for the `db` service.
- Manually backup the `/var/www/keys` volume containing API V2 signatures.

## Update Process
Because Docker containers are immutable, the in-app `includes/updater/` is safely disabled.
1. Pull or merge the latest Licora release into your GitHub repository.
2. Coolify will detect the webhook and trigger an automated rolling deployment.
3. Your configuration and keys remain safe in volumes/environment variables.

## Rollback Process
1. In Coolify, navigate to the "Deployments" tab for your project.
2. Find the previously known good deployment.
3. Click "Redeploy".
4. If a database migration caused corruption, restore the SQL dump from your automated Coolify backups.
