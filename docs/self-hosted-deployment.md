# Self-Hosted Deployment

This runbook deploys Blogravel with the repository's Docker Compose stack. It
assumes a Linux host with Docker Compose v2, a DNS name, and a reverse proxy or
TLS terminator in front of the application.

## Before You Start

The host should have:

- Docker Engine and the Docker Compose plugin
- At least one persistent filesystem for the Compose volumes
- A DNS name pointing to the host
- A TLS certificate for the public domain
- An SMTP account for production email
- A backup destination separate from the host when possible

The stack includes Laravel/FrankenPHP, PostgreSQL, Redis, a queue worker, and a
scheduler. PostgreSQL and Redis are internal services and are not published to
the host. pgAdmin and Mailpit are development-only Compose services.

## Get a Release

Use a tagged release rather than the moving default branch:

```bash
git clone https://github.com/Azab-Inc/blogravel.git
cd blogravel
git checkout <release-tag>
```

The Compose file and environment file are under `blogravel/`.

## Configure the Environment

```bash
cp blogravel/.env.example blogravel/.env
```

Edit `blogravel/.env` before starting the stack. At minimum, set:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://example.com
APP_PORT=8080
APP_KEY=<unique-secret>

DB_DATABASE=blogravel
DB_USERNAME=blogravel
DB_PASSWORD=<unique-strong-password>

TENANCY_PLATFORM_DOMAIN=example.com
SESSION_DOMAIN=.example.com

MAIL_MAILER=smtp
MAIL_HOST=<smtp-host>
MAIL_PORT=587
MAIL_USERNAME=<smtp-user>
MAIL_PASSWORD=<smtp-password>
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@example.com

BILLING_ENABLED=false
BACKUP_DISK=<local-or-s3-disk>
BACKUP_PATH=backups
```

Use a secret manager or an equivalent protected deployment mechanism for
`APP_KEY`, database credentials, SMTP credentials, and storage credentials.
Do not commit `.env` or production secrets.

Self-hosted deployments should keep billing disabled. The current application
uses the platform-domain tenancy routing by default; `TENANCY_MODE` is not an
active configuration key in the current codebase and must not be added as if it
changes runtime behavior.

## Start the Stack

Run these commands from the repository root:

```bash
docker compose --env-file blogravel/.env -f blogravel/compose.yaml config
docker compose --env-file blogravel/.env -f blogravel/compose.yaml up -d --build
```

The Compose file requires `DB_PASSWORD` and waits for healthy PostgreSQL and
Redis services before starting the app, queue, and scheduler services.

Run the application setup once on a new installation:

```bash
docker compose --env-file blogravel/.env -f blogravel/compose.yaml exec laravel.test composer setup
```

`composer setup` installs PHP and frontend dependencies, generates an app key
if needed, runs migrations, and builds assets. For an existing production
installation, do not run it as an upgrade command; use the upgrade procedure
below instead.

Create an administrator using the application's normal admin/user flow. Do
not run development seeders against production unless the release explicitly
documents that requirement.

## DNS and HTTPS

Point both the root domain and wildcard tenant domain at the host:

- `example.com` -> the reverse proxy
- `*.example.com` -> the reverse proxy

Provision a certificate covering both `example.com` and `*.example.com`.
Terminate HTTPS at the reverse proxy and forward HTTP traffic to the published
Compose app port, normally `8080`. Preserve the original `Host` header and
configure forwarded-proto headers so Laravel knows the request was HTTPS.

The root domain is used for authentication and administration. A tenant with
slug `acme` is served at `https://acme.example.com`. Keep the leading dot in
`SESSION_DOMAIN` so authenticated sessions work across the root and tenant
hosts.

Do not expose PostgreSQL, Redis, the Octane admin port, pgAdmin, or Mailpit to
the public network.

## Health and Logs

Laravel exposes `/up` as its health endpoint. Configure external monitoring to
request `https://example.com/up` and alert on non-2xx responses. Compose also
checks the app TCP listener, PostgreSQL readiness, Redis ping, queue process,
and scheduler process.

Inspect service state and logs with:

```bash
docker compose --env-file blogravel/.env -f blogravel/compose.yaml ps
docker compose --env-file blogravel/.env -f blogravel/compose.yaml logs --tail=200 laravel.test
docker compose --env-file blogravel/.env -f blogravel/compose.yaml logs --tail=200 queue scheduler
```

Check scheduled tasks and failed queue jobs from the application container:

```bash
docker compose --env-file blogravel/.env -f blogravel/compose.yaml exec laravel.test php artisan schedule:list
docker compose --env-file blogravel/.env -f blogravel/compose.yaml exec laravel.test php artisan queue:failed
docker compose --env-file blogravel/.env -f blogravel/compose.yaml exec laravel.test php artisan queue:retry all
```

Only retry failed jobs after confirming the underlying cause. Keep host-level
container and disk monitoring enabled; Docker health checks do not provide
alerting by themselves.

## Backups and Restore

Configure backup rules in the Filament admin panel. Scheduled backup jobs are
dispatched by the scheduler and run through the queue worker. `BACKUP_DISK` and
`BACKUP_PATH` determine where encrypted backup archives are stored.

For production, prefer a durable S3-compatible disk or copy local archives to a
separate host. Ensure the container can write to the configured storage and
that the backup encryption key is retained separately from the database and
application host.

The application currently creates encrypted archives but does not provide a
dedicated restore Artisan command. A restore must therefore be tested and
performed as an operator procedure using the archive's documented encryption
key, PostgreSQL tooling, and application storage contents. Do not consider a
backup valid until a restore has been tested on a disposable instance.

## Upgrade

Take a verified backup before upgrading, then deploy the next release:

```bash
git fetch --tags
git checkout <new-release-tag>
docker compose --env-file blogravel/.env -f blogravel/compose.yaml up -d --build
docker compose --env-file blogravel/.env -f blogravel/compose.yaml exec laravel.test php artisan migrate --force
docker compose --env-file blogravel/.env -f blogravel/compose.yaml exec laravel.test php artisan optimize:clear
docker compose --env-file blogravel/.env -f blogravel/compose.yaml restart laravel.test queue scheduler
```

Verify `/up`, the admin login, a tenant URL, queue health, and scheduler state
after every upgrade. Review the release notes before applying migrations.

If an upgrade fails, stop at the failed release, inspect logs, and restore the
previous release tag and database backup only after confirming the migration is
safe to reverse. Never assume a database migration can be rolled back without
checking its release notes.

## Stop and Start

```bash
docker compose --env-file blogravel/.env -f blogravel/compose.yaml stop
docker compose --env-file blogravel/.env -f blogravel/compose.yaml start
```

Do not use `docker compose down -v` on a production host. The `-v` option
removes named database and Redis volumes.
