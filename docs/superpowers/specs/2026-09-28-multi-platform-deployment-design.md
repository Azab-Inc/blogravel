# Multi-Platform Deployment Design

## Purpose

Provide independently deployable Hostinger VPS, Laravel Cloud, and Google Cloud
Run targets while retaining tagged releases as the immutable deployment source.
The existing Docker Compose deployment remains the self-hosted baseline.

## Decisions

- Deploy manually from Jenkins using a validated annotated SemVer tag.
- Keep separate provider-specific Jenkins pipelines, with shared release
  validation in `ci/validate-release.sh`.
- Use Hostinger VPS with the existing Compose stack, Redis queue, and Horizon
  direction. Do not introduce Terraform for the VPS.
- Use Laravel Cloud deploy hooks, managed queues, Laravel Cloud Object Storage,
  and Laravel Cloud-managed secrets. Terraform does not manage Laravel Cloud.
- Use Google Cloud Run in `us-central1`, Cloud SQL for PostgreSQL, a database
  queue consumed by a continuously running Cloud Run worker service, Cloud
  Storage for media and backups, and Cloud Scheduler to invoke a Cloud Run Job
  running `schedule:run` every minute.
- Store GCP Terraform state in a private, versioned GCS bucket. State is never
  committed to the repository.

## Release Architecture

`ci/validate-release.sh` is the one reusable release gate. Each deployment
pipeline invokes it before authenticating to or changing its target platform.
It must resolve an annotated `vMAJOR.MINOR.PATCH` tag to a commit, check out
that commit, and run the existing application quality checks:

- Composer dependency installation and linting
- Pest test suite
- Frontend build
- Docker Compose configuration validation

The root `Jenkinsfile` remains the CI quality pipeline. The new provider
pipelines are intentionally separate to prevent platform credentials, release
steps, and rollback procedures from being coupled in a parameterized pipeline.

### Hostinger VPS

`Jenkinsfile.hostinger` deploys the validated release tag through the existing
password-based SSH/`sshpass` mechanism. It updates the tagged checkout on the
VPS, runs the existing Compose upgrade sequence, performs migrations, clears
optimized caches, restarts the application, queue, and scheduler services, and
checks `/up`.

The pipeline must take all host, deployment-path, and credential values from
Jenkins credentials or parameters. It must not write a production `.env` into
the repository or expose PostgreSQL, Redis, pgAdmin, Mailpit, or Octane admin
ports.

### Laravel Cloud

`Jenkinsfile.laravel-cloud` posts the tag's resolved commit SHA to a
Jenkins-stored Laravel Cloud deploy hook. The Laravel Cloud environment tracks
the configured branch, while the hook's `commit_hash` guarantees that the
selected release commit is deployed rather than a moving branch head.

Laravel Cloud provisions and operates the managed queue and Object Storage.
The Laravel Cloud queue dashboard is the failed-job and retry interface; Horizon
is not used for this target. Application and third-party credentials are stored
as Laravel Cloud environment secrets.

### Google Cloud

GCP uses a production container image built once per release and stored in
Artifact Registry. Jenkins deploys the same immutable image digest to both
Cloud Run services:

- The public web service runs the Laravel HTTP runtime.
- The internal worker service keeps one instance warm and runs an HTTP listener
  plus `php artisan queue:work database` under a supervisor. An HTTP listener
  is required because Cloud Run services must serve a container port.

A Cloud Run Job runs `php artisan schedule:run`. Cloud Scheduler invokes that
job every minute using an authenticated Google API request. This avoids an
always-on scheduler container.

Terraform provisions:

- Required GCP services and IAM identities
- Artifact Registry
- Cloud Run web and worker services
- Cloud Run scheduler job and Cloud Scheduler trigger
- Private Cloud SQL PostgreSQL
- Cloud Storage bucket for media and encrypted backups
- Secret Manager secret containers and service-account access
- Private, versioned GCS state bucket and per-environment state prefix

The public web service remains the only public Cloud Run endpoint. The worker,
scheduler, Cloud SQL, buckets, and Terraform state remain private. Cloud Run
and Cloud SQL sizing must be explicit and conservative; the under-$25/month
goal is a budget target, not a guarantee because Cloud SQL and a minimum-one
worker create baseline costs.

## DNS, Data, and Operations

Every target must support both the root domain and wildcard tenant subdomains,
preserve the `Host` and forwarded HTTPS headers, and set `SESSION_DOMAIN` to
the parent domain with a leading dot.

Hostinger keeps its existing Redis-backed queue and Compose health checks.
Laravel Cloud uses its native queue observability. GCP uses database-queue
observability through application logs and a documented failed-job/retry
operator procedure.

Durable media and backup data use Laravel Cloud Object Storage on Laravel Cloud
and GCS on GCP. Each runbook documents initial provisioning, secret setup,
DNS, backup/restore, smoke checks, and a rollback procedure. Database rollback
is always an explicit operator decision after reviewing migrations and restore
readiness.

## Tickets and Validation

Create one parent GitHub issue with linked Hostinger, Laravel Cloud, and GCP
child issues. Add all four issues to GitHub Project #17 and mirror their
statuses in `tickets.md`. Link, rather than replace, existing issue #29.

Test the shared validation script and deployment configuration. Run the
existing PHP checks, frontend build, Compose validation, Terraform formatting
and validation, and applicable Playwright smoke tests. Live deployments require
provider credentials and are documented, not performed by this change.

## Non-Goals

- Replacing the existing self-hosted Compose deployment
- Terraform management of the Hostinger VPS or Laravel Cloud resources
- Committing secrets, Terraform state, live URLs, fixed production IPs, or
  credential identifiers
- Automated database rollback
