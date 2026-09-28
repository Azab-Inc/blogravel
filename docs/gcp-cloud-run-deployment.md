# GCP Cloud Run Deployment

This runbook deploys Blogravel to Google Cloud Run in `us-central1`. Terraform
provisions the GCP resources; Jenkins builds one immutable image and deploys it
to the web, worker, scheduler, and migration targets.

## Architecture

- `blogravel-production-web`: public Cloud Run web service
- `blogravel-production-worker`: private Cloud Run service with one warm instance
  running the database queue worker
- `blogravel-production-scheduler`: Cloud Run Job invoked by Cloud Scheduler every minute
- `blogravel-production-migrate`: Cloud Run Job executed by Jenkins after infrastructure apply
- Cloud SQL PostgreSQL with private IP
- Private versioned GCS bucket for media and encrypted backups
- Artifact Registry for release images

Cloud SQL and a minimum-one worker establish baseline cost. The `$25/month`
target is a sizing target, not a billing guarantee. Configure billing alerts
before applying infrastructure.

## Bootstrap Terraform State

Create or select a GCP project and configure billing. Authenticate locally with
an identity allowed to create the bootstrap resources. Choose a globally unique
state bucket name that does not contain secrets or tenant data.

Run the bootstrap stack:

```bash
terraform -chdir=infrastructure/gcp/bootstrap init
terraform -chdir=infrastructure/gcp/bootstrap apply \
  -var='project_id=<gcp-project-id>' \
  -var='state_bucket_name=<globally-unique-state-bucket>'
```

The state bucket has uniform access, public-access prevention, and object
versioning. Never commit `.tfstate`, plan files, service-account keys, or secret
values.

## Configure Secrets and Database

Create the application key and database password secrets referenced by the
Terraform variables, then grant the runtime service account access through the
Terraform stack. Add the secret values outside Git:

```bash
gcloud secrets versions add <app-key-secret-id> --data-file=-
gcloud secrets versions add <db-password-secret-id> --data-file=-
gcloud secrets versions add blogravel-storage-access-key --data-file=-
gcloud secrets versions add blogravel-storage-secret-key --data-file=-
```

Create the application database user using the Cloud SQL operator procedure,
with the same username and password stored in Secret Manager. Terraform does
not write password values into state. The Cloud SQL instance uses private IP;
Cloud Run services use the managed VPC network interface to reach it.

## Apply Application Infrastructure

From the repository root, initialize the app stack against the state bucket:

```bash
terraform -chdir=infrastructure/gcp/app init \
  -backend-config='bucket=<state-bucket>' \
  -backend-config='prefix=blogravel/production'
terraform -chdir=infrastructure/gcp/app plan \
  -var='project_id=<gcp-project-id>' \
  -var='environment=production' \
  -var='region=us-central1' \
  -var='image_digest=<artifact-registry-image-digest>' \
  -var='domain=example.com' \
  -var='storage_bucket_name=<globally-unique-media-bucket>' \
  -var='app_key_secret_id=<app-key-secret-id>' \
  -var='db_password_secret_id=<db-password-secret-id>'
```

Review the plan before applying it. Do not make the worker public. The web
service is the only resource with unauthenticated invocation permission. Do not
put database or object-storage credentials in Terraform variables.

## Jenkins Deployment

Configure `Jenkinsfile.gcp` with:

- `RELEASE_TAG`: annotated `vMAJOR.MINOR.PATCH` tag
- `GCP_PROJECT_ID`, `GCP_ENVIRONMENT`, and `GCP_REGION`
- Artifact Registry repository and GCS state bucket names
- Root domain and media/backup bucket name
- Secret Manager IDs for `APP_KEY` and database password
- Secret Manager IDs for the GCS S3-compatible access and secret keys
- A Jenkins file credential containing a GCP service-account key

The pipeline validates the tag, builds the production image, pushes it to
Artifact Registry, resolves its immutable digest, applies Terraform with that
digest, executes the migration job, and probes the web service `/up` endpoint.
The same digest configures the web and worker services.

## DNS and Runtime Configuration

Map the root domain and wildcard tenant domain to the Cloud Run web service's
custom-domain target. Provision certificates for both host patterns. Configure
the following application values through Terraform/Secret Manager:

```dotenv
APP_ENV=production
APP_DEBUG=false
TENANCY_PLATFORM_DOMAIN=example.com
SESSION_DOMAIN=.example.com
QUEUE_CONNECTION=database
FILESYSTEM_DISK=s3
BACKUP_DISK=s3
```

GCS media and backup storage uses the S3-compatible storage settings expected by
the existing application. The storage access and secret keys are injected from
Secret Manager. Keep the bucket private and retain the backup encryption key
separately.

## Verification and Operations

After deployment, verify the Terraform outputs and then:

```bash
curl --fail --silent --show-error https://example.com/up
gcloud run services describe blogravel-production-web --region=us-central1
gcloud run services describe blogravel-production-worker --region=us-central1
gcloud run jobs executions list --job=blogravel-production-scheduler --region=us-central1
```

Also verify root-domain admin login, a tenant URL, Cloud SQL connectivity,
media upload, backup creation, scheduler history, and failed database queue
jobs in application logs. Retry failed jobs only after diagnosing their cause.

## Backups and Rollback

Keep Cloud SQL automated backups and point-in-time recovery enabled. Store
encrypted application backup archives in the private GCS bucket. Test a restore
on a disposable environment before treating the backup as valid.

For an application-only rollback, rerun `Jenkinsfile.gcp` with the previous
release tag. It rebuilds or resolves the previous image digest and redeploys the
web, worker, and migration configuration. Review database migrations before
running a rollback; restore the verified database and storage backup if the
failed release changed schema or data.
