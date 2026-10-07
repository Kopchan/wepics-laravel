# Wepics PHP-FPM image
# Build args:
#   WITH_NODE=true|false  (default: false) — install nodejs+npm for local Browsershot fallback
#   PHP_VERSION=8.4       (default: 8.4)   — PHP major.minor
#
# Usage:
#   docker build --build-arg WITH_NODE=true .
#   docker build -t wepics:no-node .

ARG PHP_VERSION=8.4
ARG WITH_NODE=false

# Base image is php-fpm on Debian 12
FROM php:${PHP_VERSION}-fpm-bookworm AS base

# System deps and php plugins
RUN apt-get update && apt-get install -y --no-install-recommends \
    git curl zip unzip \
    ffmpeg \
    libpng-dev libjpeg-dev libwebp-dev libavif-dev libzip-dev libicu-dev libonig-dev libxml2-dev \
    && docker-php-ext-configure gd --with-jpeg --with-webp --with-avif \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql mbstring exif pcntl bcmath intl opcache zip gd \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# www-public folder default
WORKDIR /var/www/html

# Composer (app) deps
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist

# App code, exec deps code, unix rights managment
COPY . .
RUN composer dump-autoload --optimize \
    && php artisan package:discover --ansi || true \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# Conditional Node + puppeteer for OG screenshots
ARG WITH_NODE
RUN if [ "$WITH_NODE" = "true" ]; then \
      curl -fsSL https://deb.nodesource.com/setup_24.x | bash - \
      && apt-get install -y --no-install-recommends nodejs \
      && PUPPETEER_SKIP_CHROMIUM_DOWNLOAD=true npm install -g puppeteer@^25 \
      && npm cache clean --force \
      && apt-get clean && rm -rf /var/lib/apt/lists/*; \
    fi

# Embed script executable and configs on starting docker image
COPY docker/php/php.ini /usr/local/etc/php/conf.d/99-wepics.ini
COPY docker/php/entrypoint.sh /usr/local/bin/wepics-entrypoint.sh
RUN chmod +x /usr/local/bin/wepics-entrypoint.sh

# Share FastCGI port for bind
EXPOSE 9000

# Launch script on startup
ENTRYPOINT ["wepics-entrypoint.sh"]

# After execute binary of base image on www-public folder
CMD ["php-fpm"]
