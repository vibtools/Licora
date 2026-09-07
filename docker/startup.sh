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

# Execute the main container command
exec "$@"
