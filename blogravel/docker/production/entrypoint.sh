#!/usr/bin/env sh
set -eu

case "${CONTAINER_ROLE:-web}" in
    web)
        exec frankenphp run --config /etc/caddy/Caddyfile
        ;;
    worker)
        exec /usr/bin/supervisord -c /etc/supervisor/supervisord.conf
        ;;
    scheduler)
        exec php artisan schedule:run --no-interaction
        ;;
    *)
        printf 'Unsupported CONTAINER_ROLE: %s\n' "${CONTAINER_ROLE}" >&2
        exit 1
        ;;
esac
