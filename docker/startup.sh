#!/bin/sh
set -e

# Make sure the keys directory exists and has correct permissions
mkdir -p /var/www/keys
chown -R www-data:www-data /var/www/keys

# Generate V2 RSA keys if they don't exist
if [ ! -f /var/www/keys/private.pem ]; then
    echo "Generating new RSA keypair for API V2..."
    openssl genpkey -algorithm RSA -out /var/www/keys/private.pem -pkeyopt rsa_keygen_bits:3072
    openssl rsa -pubout -in /var/www/keys/private.pem -out /var/www/keys/public.pem
    chown www-data:www-data /var/www/keys/*.pem
    chmod 600 /var/www/keys/private.pem
    chmod 644 /var/www/keys/public.pem
fi

# Check for required environment variables at runtime instead of build-time
echo "Verifying required environment variables..."
REQUIRED_VARS="APP_ENV APP_URL LICENSE_DB_NAME LICENSE_DB_USER LICENSE_DB_PASS LICENSE_APP_KEY LICENSE_ENCRYPTION_KEY LICENSE_CSRF_SECRET LICENSE_JWT_SECRET"

for VAR in $REQUIRED_VARS; do
    # Use eval to get the value of the variable name stored in VAR
    eval VAL=\$$VAR
    if [ -z "$VAL" ]; then
        echo "FATAL ERROR: Environment variable $VAR is required but missing or empty."
        exit 1
    fi
done
echo "Environment verification passed."

# Auto-initialize database if tables are missing
php /var/www/html/docker/init-db.php

# Execute the main container command
exec "$@"
