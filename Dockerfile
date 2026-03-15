# -----------------------------------------------------------------------------
# Stage 1 — Composer dependencies (builder)
# -----------------------------------------------------------------------------
FROM composer:2.7 AS composer-builder
WORKDIR /app

COPY composer.json composer.lock* ./
RUN composer install --no-dev --no-scripts --no-autoloader --ignore-platform-reqs

COPY . .
RUN composer dump-autoload --optimize --no-dev

# -----------------------------------------------------------------------------
# Stage 2 — Production PHP-FPM image
# -----------------------------------------------------------------------------
FROM php:8.3-fpm-alpine AS production

# System dependencies (gd, zip, intl, tzdata)
RUN apk add --no-cache \
    libpng-dev \
    libjpeg-turbo-dev \
    libwebp-dev \
    freetype-dev \
    libzip-dev \
    icu-dev \
    linux-headers \
    tzdata \
    $PHPIZE_DEPS

# Configure and install GD with JPEG, PNG, WebP
RUN docker-php-ext-configure gd --with-jpeg --with-webp --with-freetype \
    && docker-php-ext-install -j$(nproc) gd

# Install remaining extensions
RUN docker-php-ext-install -j$(nproc) \
    pdo_mysql \
    zip \
    bcmath \
    intl \
    exif \
    pcntl \
    sockets \
    opcache

# mbstring is usually enabled by default; ensure it
RUN docker-php-ext-install -j$(nproc) mbstring || true

# Redis via PECL
RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps

# Clean build deps and keep only runtime libs for gd/zip/icu
RUN apk del libpng-dev libjpeg-turbo-dev libwebp-dev freetype-dev libzip-dev icu-dev linux-headers $PHPIZE_DEPS 2>/dev/null || true \
    && apk add --no-cache libpng libjpeg-turbo libwebp libzip libicu libfreetype \
    && rm -rf /var/cache/apk/* /tmp/pear

# Timezone
ENV TZ=UTC

# Log directory for PHP
RUN mkdir -p /var/log/php && chown www-data:www-data /var/log/php

# PHP production ini
COPY docker/php-production.ini /usr/local/etc/php/conf.d/99-production.ini
COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/opcache.ini
COPY docker/php-fpm-www.conf /usr/local/etc/php-fpm.d/www.conf

# Application from builder
COPY --from=composer-builder /app /var/www/html

# Laravel storage and bootstrap/cache (writable at runtime via volumes or chown)
RUN mkdir -p /var/www/html/storage/framework/{sessions,views,cache,testing} \
    /var/www/html/storage/logs \
    /var/www/html/storage/app/private \
    /var/www/html/bootstrap/cache \
    && chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

WORKDIR /var/www/html

COPY docker-entrypoint.sh /docker-entrypoint.sh
RUN chmod +x /docker-entrypoint.sh

ENTRYPOINT ["/docker-entrypoint.sh"]

# -----------------------------------------------------------------------------
# Stage 3 — Queue worker with Supervisor (optional: use --target worker)
# -----------------------------------------------------------------------------
FROM production AS worker
RUN apk add --no-cache supervisor
RUN mkdir -p /var/log/supervisor
COPY supervisord.conf /etc/supervisord.conf
COPY docker-entrypoint-worker.sh /docker-entrypoint-worker.sh
RUN chmod +x /docker-entrypoint-worker.sh
ENTRYPOINT ["/docker-entrypoint-worker.sh"]
