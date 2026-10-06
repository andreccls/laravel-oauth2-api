# syntax=docker/dockerfile:1
# Stages: base -> dev (tests/lint/coverage) | vendor -> octane (default runtime) | fpm (benchmark baseline)

FROM php:8.4-fpm-bookworm AS base
RUN apt-get update \
 && apt-get install -y --no-install-recommends git unzip libicu-dev \
 && docker-php-ext-install pdo_mysql pcntl sockets opcache intl \
 && pecl install redis && docker-php-ext-enable redis \
 && rm -rf /var/lib/apt/lists/* /tmp/pear
COPY docker/php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
WORKDIR /app

# ---- dev: used by `make test|lint|coverage` (source is bind-mounted, vendor in a named volume)
FROM base AS dev
RUN pecl install pcov && docker-php-ext-enable pcov
ENV COMPOSER_ALLOW_SUPERUSER=1 PHP_MEMORY_LIMIT=1G
CMD ["bash"]

# ---- vendor: production dependencies only
FROM base AS vendor
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction
COPY . .
RUN composer dump-autoload --no-dev --classmap-authoritative --no-scripts \
 && php artisan package:discover --ansi

# ---- runtime shared bits (non-root, writable dirs)
FROM base AS runtime
RUN useradd --uid 1000 --create-home app
COPY --from=vendor --chown=app:app /app /app
# RoadRunner binary from the official image (pinned; `rr get-binary` hits the GitHub API and gets rate-limited in CI)
COPY --from=ghcr.io/roadrunner-server/roadrunner:2025.1.15 /usr/bin/rr /usr/local/bin/rr
COPY docker/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint \
 && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
 && touch .rr.yaml && chown -R app:app storage bootstrap/cache .rr.yaml  # Octane regenerates .rr.yaml at start
USER app
ENTRYPOINT ["entrypoint"]

# ---- octane: default runtime (RoadRunner)
FROM runtime AS octane
ENV OCTANE_WORKERS=4 OCTANE_MAX_REQUESTS=1000
EXPOSE 8000
HEALTHCHECK --interval=5s --timeout=3s --retries=20 CMD php -r 'exit(@file_get_contents("http://127.0.0.1:8000/up") === false ? 1 : 0);'
CMD ["sh", "-c", "exec php artisan octane:start --server=roadrunner --host=0.0.0.0 --port=8000 --workers=$OCTANE_WORKERS --max-requests=$OCTANE_MAX_REQUESTS"]

# ---- fpm: baseline for the benchmark only (nginx in front, see docker-compose.yml profile `bench`)
FROM runtime AS fpm
COPY docker/php-fpm.conf /usr/local/etc/php-fpm.d/zz-app.conf
EXPOSE 9000
CMD ["php-fpm", "--nodaemonize"]
