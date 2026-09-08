FROM php:8.2-fpm-alpine

# Install required system dependencies
RUN apk add --no-cache \
    openssl \
    libzip-dev \
    curl-dev \
    oniguruma-dev \
    bash

# Install PHP extensions required by Licora
RUN docker-php-ext-install \
    pdo \
    pdo_mysql \
    mysqli \
    zip \
    curl \
    mbstring

# Copy application files
WORKDIR /var/www/html
COPY . /var/www/html/

# Copy configuration files
COPY docker/php.ini /usr/local/etc/php/conf.d/licora.ini
COPY docker/startup.sh /usr/local/bin/startup.sh

# Setup Crontab
RUN echo "0 * * * * php /var/www/html/cron/check_expiring.php" > /etc/crontabs/www-data && \
    echo "0 2 * * * php /var/www/html/cron/cleanup.php" >> /etc/crontabs/www-data

# Set correct permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod +x /usr/local/bin/startup.sh

# Expose PHP-FPM port
EXPOSE 9000

# Set entrypoint
ENTRYPOINT ["/usr/local/bin/startup.sh"]
CMD ["php-fpm"]
