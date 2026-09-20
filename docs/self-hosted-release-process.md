# Self-Hosted Release Process

This process publishes a repeatable, tagged Blogravel release for self-hosted
installations. Release automation is intentionally outside this process; the
release owner performs these steps from a clean local checkout.

## Versioning and Tags

Use Semantic Versioning with a leading `v`:

- `vMAJOR.MINOR.PATCH` for every release, for example `v1.2.0`
- Increment `MAJOR` for incompatible deployment, configuration, or data changes
- Increment `MINOR` for backwards-compatible features
- Increment `PATCH` for backwards-compatible fixes and documentation-only changes

Create an annotated tag only after the release candidate passes the checklist:

```bash
git switch main
git pull --ff-only origin main
git status --short
git tag -a v1.2.0 -m "Release v1.2.0"
git push origin v1.2.0
```

The tag must point to the exact commit that was validated. Do not move or reuse
published release tags. If a release must be corrected, publish the next
appropriate patch version.

GitHub release notes must include the tag, a concise change summary, migration
notes, configuration changes, upgrade instructions, and rollback warnings.

## Release Contents

A self-hosted release is the tagged repository checkout. It must include:

- The `blogravel/` Laravel application source
- `blogravel/composer.json` and `blogravel/composer.lock`
- `blogravel/package.json` and `blogravel/package-lock.json`
- `blogravel/compose.yaml`
- `blogravel/.env.example`
- Docker and application configuration required by the Compose stack
- Database migrations and application assets
- `README.md` and the deployment documentation under `docs/`
- Release notes describing application, migration, and configuration changes

Do not include or publish:

- `blogravel/.env` or any secret, credential, API key, or private certificate
- Database, Redis, backup, log, cache, or uploaded-media data from a live host
- `blogravel/vendor/` or `blogravel/node_modules/`
- Local Docker volumes, development exports, test data, or IDE files
- Build output or generated runtime files that are recreated during deployment

Operators should use the tagged checkout directly. The Compose stack builds the
application image from the checked-out source, so the checkout and its tag are
the deployment provenance for the running release.

## Release Checklist

Run this checklist from the repository root before creating the tag.

### Repository and tests

- [ ] Worktree is clean except for intentional release preparation changes.
- [ ] The release branch contains the intended commits and no local-only files.
- [ ] The application test suite passes.
- [ ] Formatting and lint checks pass.
- [ ] Composer and npm lockfiles are present and match their manifests.

### Compose and environment

- [ ] `blogravel/.env.example` contains every required production variable and no real secret.
- [ ] Development-only services remain behind Compose profiles.
- [ ] Compose configuration validates with a production-shaped environment file.
- [ ] Production services have health checks, restart policies, and persistent data volumes.
- [ ] Database, Redis, Octane admin, pgAdmin, and Mailpit are not publicly exposed.

Validate the Compose file with:

```bash
docker compose --env-file blogravel/.env -f blogravel/compose.yaml config
```

### Documentation and migrations

- [ ] README setup and self-hosted links are accurate.
- [ ] Release notes list migration and configuration changes explicitly.
- [ ] Any required manual migration or data conversion is documented.
- [ ] Rollback warnings identify migrations that are not safely reversible.
- [ ] Upgrade commands match the current Compose service names.

### Smoke test

Validate the candidate in a clean checkout or disposable host, not against a
live installation:

```bash
cp blogravel/.env.example blogravel/.env
docker compose --env-file blogravel/.env -f blogravel/compose.yaml config
docker compose --env-file blogravel/.env -f blogravel/compose.yaml up -d --build
docker compose --env-file blogravel/.env -f blogravel/compose.yaml exec laravel.test composer setup
curl --fail http://localhost:${APP_PORT:-8080}/up
docker compose --env-file blogravel/.env -f blogravel/compose.yaml ps
```

Confirm the smoke test can reach the health endpoint, the admin login, a tenant
URL, the queue worker, and the scheduler. Confirm that development-only
services are absent unless their profiles are explicitly enabled. Tear down the
disposable environment without deleting any production volumes.

## Upgrade Procedure

Read the release notes and take a verified backup before upgrading. Keep the
current release tag available until the new release passes its smoke checks.

```bash
git fetch --tags
git checkout <new-release-tag>
docker compose --env-file blogravel/.env -f blogravel/compose.yaml up -d --build
docker compose --env-file blogravel/.env -f blogravel/compose.yaml exec laravel.test php artisan migrate --force
docker compose --env-file blogravel/.env -f blogravel/compose.yaml exec laravel.test php artisan optimize:clear
docker compose --env-file blogravel/.env -f blogravel/compose.yaml restart laravel.test queue scheduler
```

After the upgrade, verify:

- `/up` returns a successful response
- Admin authentication works
- A tenant URL serves the expected site
- Queue and scheduler containers are healthy
- Scheduled tasks and failed jobs are understood before retrying anything
- Configuration changes from the release notes are applied

Do not run `composer setup` as an upgrade command. It is intended for initial
installation and may install development dependencies or rebuild state that
should be controlled separately during production upgrades.

## Rollback Considerations

If the release fails, stop at the failed release and preserve its logs. Do not
assume a database migration can be rolled back safely. Check the release notes
and migration implementation first.

For an application-only rollback, check out the previous release tag and
rebuild the services:

```bash
git checkout <previous-release-tag>
docker compose --env-file blogravel/.env -f blogravel/compose.yaml up -d --build
docker compose --env-file blogravel/.env -f blogravel/compose.yaml restart laravel.test queue scheduler
```

If the failed release changed the database schema or data, restore the verified
pre-upgrade database and application storage backup only after confirming the
restore procedure on a disposable instance. Never run `docker compose down -v`
on a production host because it removes named database and Redis volumes.

## Release Candidate Record

For each release, retain the following with the GitHub release notes:

- The release tag and commit SHA
- The date and operator who performed validation
- The test and Compose validation results
- The environment-template and documentation review result
- The smoke-test host or disposable environment identifier
- Migration, configuration, and rollback notes

The candidate is ready to publish only when every checklist item is complete
or the release notes explicitly record an accepted exception.
