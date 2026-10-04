FROM dunglas/frankenphp:1-php8.5-bookworm AS runtime-base

RUN apt-get update \
    && apt-get install --no-install-recommends --yes supervisor \
    && rm -rf /var/lib/apt/lists/* \
    && install-php-extensions intl pdo_pgsql redis zip

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

FROM runtime-base AS composer-dependencies

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-interaction --no-progress --optimize-autoloader --no-scripts

FROM node:22-bookworm-slim AS frontend

WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY . .
COPY --from=composer-dependencies /app/vendor ./vendor
RUN npm run build

FROM runtime-base AS production

COPY --from=composer-dependencies /app/vendor ./vendor
COPY --from=frontend /app/public/build ./public/build
COPY . .
COPY docker/production/Caddyfile /etc/caddy/Caddyfile
COPY docker/production/entrypoint.sh /usr/local/bin/blogravel-entrypoint
COPY docker/production/supervisord.conf /etc/supervisor/conf.d/blogravel.conf

RUN chmod +x /usr/local/bin/blogravel-entrypoint \
    && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs \
    && useradd --create-home --shell /usr/sbin/nologin appuser \
    && chown -R appuser:appuser /app /config /data

USER appuser
ENV APP_ENV=production
ENV APP_DEBUG=false
ENV XDG_CONFIG_HOME=/config
ENV XDG_DATA_HOME=/data

ENTRYPOINT ["/usr/local/bin/blogravel-entrypoint"]
