# Hostinger VPS Deployment

This runbook operates the Hostinger VPS deployment performed by
`Jenkinsfile.hostinger`. It does not provision the VPS with Terraform or copy
production secrets into the repository.

Create and validate release tags with the [Self-Hosted Release Process](self-hosted-release-process.md).
For the required production environment values, HTTPS behavior, and backup
configuration, use [Self-Hosted Deployment](self-hosted-deployment.md).

## Provisioning Checklist

- Install Docker Engine with the Docker Compose v2 plugin, Git, and curl on the
  VPS. Confirm the deployment user can run Docker commands.
- Provide persistent storage for the bind-mounted `blogravel/docker/volumes/pgsql`
  and `blogravel/docker/volumes/redis` directories.
- Allow SSH only for the Jenkins deployment account. Publish the application
  port only to the reverse proxy; do not expose PostgreSQL, Redis, the Octane
  admin port, pgAdmin, or Mailpit.
- Create DNS records for both `example.com` and `*.example.com` that point to
  the reverse proxy. Configure TLS for both names, preserve the `Host` header,
  and send forwarded-proto headers to the application.
- Create the bind-mounted runtime directories before the first start and ensure
  the application process can write them:

  ```bash
  mkdir -p blogravel/storage blogravel/bootstrap/cache blogravel/docker/volumes/{pgsql,redis}
  sudo chown -R 1000:1000 blogravel/storage blogravel/bootstrap/cache
  ```

  PostgreSQL and Redis initialize their own data-directory permissions when
  their bind mounts are first created.
- Use `compose.yaml` in production. pgAdmin and Mailpit exist only in
  `compose.dev.yaml` and are not part of the VPS service graph.

Run the following from the VPS deployment directory after installation:

```bash
docker --version
docker compose version
git --version
curl --version
```

## VPS Checkout and Environment

The deployment path is the repository root, not the `blogravel/` application
directory. Clone a tagged checkout once, replacing the example path and tag:

```bash
git clone https://github.com/Azab-Inc/blogravel.git /srv/blogravel
cd /srv/blogravel
git checkout --detach <release-tag>
cp blogravel/.env.example blogravel/.env
chmod 600 blogravel/.env
```

Edit `blogravel/.env` on the VPS only. It is ignored by Git and must never be
committed, uploaded to Jenkins, or copied from a workstation. Set the
production values listed in [Self-Hosted Deployment](self-hosted-deployment.md#configure-the-environment),
including `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL`, `APP_KEY`, the
database and SMTP credentials, `TENANCY_PLATFORM_DOMAIN`, `SESSION_DOMAIN`,
and backup storage credentials.

Set `APP_PORT=8080` unless the deployment user's remote shell exports the same
alternative value. The pipeline probes `http://127.0.0.1:${APP_PORT:-8080}/up`
after deployment; it does not source `blogravel/.env` before expanding that
shell variable.

Validate the production-shaped Compose configuration before the initial start:

```bash
docker compose --env-file blogravel/.env -f blogravel/compose.yaml config
docker compose --env-file blogravel/.env -f blogravel/compose.yaml up -d --build --wait
docker compose --env-file blogravel/.env -f blogravel/compose.yaml exec -T laravel.test php artisan migrate --force
```

The production Compose preparation services install PHP dependencies and build
frontend assets into the bind-mounted checkout during startup. Keep `APP_KEY`
and all other production secrets in the VPS `.env` file.

## Jenkins Job Setup

Create a Jenkins Pipeline job that checks out this repository and uses
`Jenkinsfile.hostinger`. The job requires the `php85` agent label. Its release
validation runs Composer, npm, Docker Compose configuration validation, and the
application test suite, so that agent must provide those tools and `sshpass`.

For each manual deployment, provide these pipeline parameters:

| Parameter | Value |
| --- | --- |
| `RELEASE_TAG` | An existing annotated `vMAJOR.MINOR.PATCH` tag created with the release process. |
| `HOSTINGER_HOST` | The Hostinger VPS hostname or IP reachable by SSH. |
| `HOSTINGER_DEPLOY_PATH` | The absolute VPS repository path, for example `/srv/blogravel`. |
| `HOSTINGER_SSH_CREDENTIALS_ID` | The ID of the Jenkins username-with-password credential for the VPS deployment account. |

The job binds that credential as `HOSTINGER_SSH_USERNAME` and
`HOSTINGER_SSH_PASSWORD`, then passes it to `sshpass`. Keep the password only in
Jenkins Credentials and on the VPS account; never place it in a job parameter,
repository file, or build log. The first connection uses
`StrictHostKeyChecking=accept-new`; verify the VPS host key out of band before
the first production deployment.

`RELEASE_TAG` is validated and resolved to its commit before SSH. The VPS
therefore receives a detached checkout of that resolved commit rather than a
moving branch.

## Deploy a Release

Before starting Jenkins, read the release notes, confirm the VPS has a verified
backup, and record the currently deployed tag as the rollback target. Start the
job with the parameters above. The remote command runs this exact upgrade
sequence from `HOSTINGER_DEPLOY_PATH`:

```bash
git fetch --tags
git checkout --detach <resolved-release-commit>
docker compose --env-file blogravel/.env -f blogravel/compose.yaml up -d --build --wait
docker compose --env-file blogravel/.env -f blogravel/compose.yaml exec -T laravel.test php artisan migrate --force
docker compose --env-file blogravel/.env -f blogravel/compose.yaml exec -T laravel.test php artisan optimize:clear
docker compose --env-file blogravel/.env -f blogravel/compose.yaml restart laravel.test queue scheduler
curl --fail --silent --show-error http://127.0.0.1:${APP_PORT:-8080}/up
```

Do not run a separate manual upgrade at the same time: the pipeline disables
concurrent builds, but it cannot prevent an operator from running conflicting
commands on the VPS.

## Verify and Diagnose

After Jenkins reports success, verify the VPS services and inspect both public
and tenant routing. Run these commands from the repository root on the VPS:

```bash
docker compose --env-file blogravel/.env -f blogravel/compose.yaml ps
docker compose --env-file blogravel/.env -f blogravel/compose.yaml logs --tail=200 laravel.test
docker compose --env-file blogravel/.env -f blogravel/compose.yaml logs --tail=200 queue scheduler
docker compose --env-file blogravel/.env -f blogravel/compose.yaml exec -T laravel.test php artisan queue:failed
docker compose --env-file blogravel/.env -f blogravel/compose.yaml exec -T laravel.test php artisan schedule:list
curl --fail --silent --show-error http://127.0.0.1:${APP_PORT:-8080}/up
```

Also check `https://example.com/up`, root-domain administrator authentication,
and a real tenant URL such as `https://acme.example.com`. Investigate the cause
of failed queue jobs before choosing to retry them.

## Backup and Restore Readiness

Configure the application's backup rules in Filament and ensure the scheduler
and queue services remain healthy; scheduled backup jobs are dispatched through
those services. Prefer a durable S3-compatible backup destination, or copy
local archives to a separate host. Retain the backup encryption key separately
from the VPS.

Before every deployment, confirm that a backup of PostgreSQL and application
storage completed successfully and that its restore procedure has been tested
on a disposable instance. The application has no dedicated restore Artisan
command, so use the archive's documented encryption key, PostgreSQL tooling,
and the application storage contents for a tested operator restore.

## Roll Back

Preserve logs from the failed release. If release notes and migration review
confirm that no schema or data restoration is required, roll back the
application to the recorded previous tag from the VPS repository root:

```bash
git fetch --tags
git checkout --detach <previous-release-tag>
docker compose --env-file blogravel/.env -f blogravel/compose.yaml up -d --build
docker compose --env-file blogravel/.env -f blogravel/compose.yaml restart laravel.test queue scheduler
curl --fail --silent --show-error http://127.0.0.1:${APP_PORT:-8080}/up
```

Schema or data rollback is not automatic. Review the failed release's
migrations and release notes first. Restore the verified pre-upgrade database
and application-storage backup only after the procedure succeeds on a
disposable instance. Never run `docker compose down -v` on the VPS, and never
delete the bind-mounted `blogravel/docker/volumes/` directories: they contain
the PostgreSQL and Redis data.
