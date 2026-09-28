#!/usr/bin/env bash
set -euo pipefail

: "${RELEASE_TAG:?RELEASE_TAG must name an annotated vMAJOR.MINOR.PATCH tag}"
[[ "$RELEASE_TAG" =~ ^v[0-9]+\.[0-9]+\.[0-9]+$ ]] || {
    printf '%s\n' 'RELEASE_TAG must match vMAJOR.MINOR.PATCH' >&2
    exit 1
}
git rev-parse --verify --quiet "$RELEASE_TAG" >/dev/null || {
    printf '%s\n' 'RELEASE_TAG must name an existing tag' >&2
    exit 1
}
[[ "$(git cat-file -t "$RELEASE_TAG")" == 'tag' ]] || {
    printf '%s\n' 'RELEASE_TAG must be an annotated tag' >&2
    exit 1
}
git checkout --detach "$RELEASE_TAG^{commit}"

cd blogravel
composer install --no-interaction --prefer-dist --no-progress
npm ci
composer lint:check
php artisan test --compact
npm run build
docker compose --env-file .env.example config --quiet
