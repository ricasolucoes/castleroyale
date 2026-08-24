# syntax=docker/dockerfile:1

# Project Dominion API.
#
# `development` mounts the source and runs the built-in server.
# `production` bakes the source and runs Octane on FrankenPHP.

# ---------------------------------------------------------------------------
FROM php:8.4-cli-alpine AS base

RUN apk add --no-cache \
        git curl unzip icu-dev libzip-dev postgresql-dev \
        oniguruma-dev linux-headers $PHPIZE_DEPS \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_pgsql pgsql bcmath intl zip opcache pcntl sockets \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del $PHPIZE_DEPS \
    && rm -rf /tmp/pear

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_MEMORY_LIMIT=-1

# ---------------------------------------------------------------------------
FROM base AS development

# Xdebug is intentionally not installed: it costs roughly half the request
# throughput and is rarely what a developer actually needs here. Add it in a
# local override when stepping through something.
RUN echo "memory_limit=512M" > /usr/local/etc/php/conf.d/dominion.ini \
    && echo "opcache.enable=1" >> /usr/local/etc/php/conf.d/dominion.ini \
    && echo "opcache.validate_timestamps=1" >> /usr/local/etc/php/conf.d/dominion.ini

EXPOSE 8000

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]

# ---------------------------------------------------------------------------
FROM base AS vendor

COPY apps/api/composer.json apps/api/composer.lock ./
RUN composer install \
        --no-dev --no-scripts --no-autoloader \
        --prefer-dist --no-interaction

# ---------------------------------------------------------------------------
FROM base AS production

RUN echo "memory_limit=256M" > /usr/local/etc/php/conf.d/dominion.ini \
    && echo "opcache.enable=1" >> /usr/local/etc/php/conf.d/dominion.ini \
    && echo "opcache.validate_timestamps=0" >> /usr/local/etc/php/conf.d/dominion.ini \
    && echo "opcache.jit_buffer_size=64M" >> /usr/local/etc/php/conf.d/dominion.ini \
    && echo "expose_php=0" >> /usr/local/etc/php/conf.d/dominion.ini

COPY --from=vendor /app/vendor ./vendor
COPY apps/api ./

RUN composer dump-autoload --optimize --classmap-authoritative \
    && php artisan config:cache \
    && php artisan route:cache \
    && php artisan event:cache

# Never run as root in production.
RUN addgroup -g 1000 dominion && adduser -D -u 1000 -G dominion dominion \
    && chown -R dominion:dominion /app/storage /app/bootstrap/cache
USER dominion

EXPOSE 8000

HEALTHCHECK --interval=30s --timeout=5s --start-period=30s --retries=3 \
    CMD curl -fsS http://localhost:8000/api/v1/health || exit 1

CMD ["php", "artisan", "octane:start", "--server=frankenphp", "--host=0.0.0.0", "--port=8000"]
