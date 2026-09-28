# GCP Cloud Run Deployment Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Provision and deploy the GCP Cloud Run production target in `us-central1` using immutable images, private Cloud SQL, database queues, GCS storage, and GCS-backed Terraform state.

**Architecture:** Terraform owns GCP infrastructure. Jenkins builds a production Laravel image once, pushes it to Artifact Registry, applies Terraform, and deploys the immutable image digest to public web and internal worker services. Cloud Scheduler invokes a Cloud Run Job for Laravel scheduling every minute.

**Tech Stack:** Terraform, Google Cloud provider, Cloud Run, Cloud SQL PostgreSQL, Cloud Storage, Secret Manager, Artifact Registry, Cloud Scheduler, Jenkins, Docker, Pest 4.

**Spec:** `docs/superpowers/specs/2026-09-28-multi-platform-deployment-design.md`

## Global Constraints

- Implement `docs/superpowers/plans/2026-09-28-deployment-foundation.md` first.
- Use `us-central1` and a private, versioned GCS backend with environment-specific state keys.
- Provision only GCP resources; do not model Hostinger or Laravel Cloud in Terraform.
- Run `QUEUE_CONNECTION=database` on GCP and keep one worker instance warm.
- The web service is public; worker, scheduler, Cloud SQL, media bucket, and state bucket are private.
- Treat under `$25/month` as a sizing target, not a billing guarantee.

---

## File Structure

- Create `blogravel/docker/production/Dockerfile`: production Laravel/FrankenPHP runtime image.
- Create `blogravel/docker/production/Caddyfile`: listens on `$PORT` and serves Laravel through FrankenPHP.
- Create `blogravel/docker/production/supervisord.conf`: starts the listener and database queue worker in worker mode.
- Create `blogravel/docker/production/entrypoint.sh`: selects the `web`, `worker`, or `scheduler` runtime role.
- Create `Jenkinsfile.gcp`: validates a tag, builds/pushes image, applies Terraform, deploys digest, migrates, and smoke tests.
- Create `infrastructure/gcp/bootstrap/`: one-time state-bucket Terraform configuration and instructions.
- Create `infrastructure/gcp/app/`: version-pinned provider, variables, IAM, Artifact Registry, Cloud SQL, GCS, Secret Manager, Cloud Run, scheduler and migration Cloud Run Jobs, and Cloud Scheduler resources.
- Create `blogravel/tests/Unit/Deployment/GcpDeploymentConfigurationTest.php`: verifies image, Jenkins, and Terraform contract files.
- Create `docs/gcp-cloud-run-deployment.md`: bootstrap, secret, DNS, cost, validation, backup, and rollback runbook.

### Task 1: Create and test the production image contract

**Files:**
- Create: `blogravel/docker/production/Dockerfile`
- Create: `blogravel/docker/production/Caddyfile`
- Create: `blogravel/docker/production/supervisord.conf`
- Create: `blogravel/docker/production/entrypoint.sh`
- Test: `blogravel/tests/Unit/Deployment/GcpDeploymentConfigurationTest.php`

**Interfaces:**
- Consumes: application source under `blogravel/`, `PORT`, runtime environment variables, and command role `web|worker|scheduler`.
- Produces: an image that starts FrankenPHP for web, FrankenPHP plus `queue:work database` for worker, or `schedule:run` for a job.

- [ ] **Step 1: Write failing configuration assertions**

Assert the Dockerfile uses a pinned PHP 8.5 FrankenPHP base, installs Composer dependencies with `--no-dev`, builds Vite assets with `npm ci` and `npm run build`, copies no `.env`, and exposes `$PORT`. Assert the supervisor runs exactly `php artisan queue:work database --sleep=3 --tries=3 --timeout=0`. Assert the Caddy configuration reads `{$PORT}`.

- [ ] **Step 2: Run the focused test to verify it fails**

Run: `cd blogravel && php artisan test --compact tests/Unit/Deployment/GcpDeploymentConfigurationTest.php`

Expected: FAIL because production runtime files are absent.

- [ ] **Step 3: Implement the multi-stage image**

Use separate Composer and Node build stages, then copy only `vendor/` and `public/build/` into the PHP 8.5 FrankenPHP runtime. Set `APP_ENV=production`, use a non-root runtime user, and supply an entrypoint that switches on a fixed `CONTAINER_ROLE` environment value. Reject any role other than `web`, `worker`, or `scheduler`.

- [ ] **Step 4: Validate the image contract**

Run the focused Pest test, then build locally:

```bash
docker build --file blogravel/docker/production/Dockerfile --tag blogravel:gcp-contract blogravel
```

Expected: PASS and a successful Docker build without secrets in build arguments.

- [ ] **Step 5: Commit image runtime files and test**

```bash
git add blogravel/docker/production blogravel/tests/Unit/Deployment/GcpDeploymentConfigurationTest.php
git commit -m "Feature: add GCP production container runtime"
```

### Task 2: Bootstrap and implement GCP Terraform

**Files:**
- Create: `infrastructure/gcp/bootstrap/main.tf`
- Create: `infrastructure/gcp/bootstrap/variables.tf`
- Create: `infrastructure/gcp/bootstrap/outputs.tf`
- Create: `infrastructure/gcp/app/backend.tf`
- Create: `infrastructure/gcp/app/versions.tf`
- Create: `infrastructure/gcp/app/variables.tf`
- Create: `infrastructure/gcp/app/main.tf`
- Create: `infrastructure/gcp/app/outputs.tf`

**Interfaces:**
- Consumes: `project_id`, `environment`, `region=us-central1`, immutable `image_digest`, secret IDs, and domain values as CI-supplied Terraform variables.
- Produces: private versioned state storage and all runtime resources defined in the design.

- [ ] **Step 1: Write Terraform configuration assertions**

Extend `GcpDeploymentConfigurationTest.php` to assert `backend "gcs"`, bucket versioning, the Google provider version constraint, Artifact Registry, Cloud SQL PostgreSQL, Cloud Run web/worker services, scheduler and migration Cloud Run Jobs, Cloud Scheduler, a media/backup bucket, Secret Manager, and IAM members are present. Assert no Hostinger/Laravel Cloud provider appears.

- [ ] **Step 2: Run the focused test to verify it fails**

Run: `cd blogravel && php artisan test --compact tests/Unit/Deployment/GcpDeploymentConfigurationTest.php`

Expected: FAIL because Terraform files are absent.

- [ ] **Step 3: Implement the bootstrap stack**

Create a separate, locally initialized bootstrap configuration that creates a globally unique state bucket with uniform bucket-level access, versioning, public-access prevention, and a lifecycle rule for old state versions. Output the bucket name. Do not configure an app backend until this bucket exists.

- [ ] **Step 4: Implement the application stack**

Pin Terraform and `hashicorp/google`; leave the GCS backend block empty and have Jenkins run `terraform init -backend-config="bucket=$GCP_TERRAFORM_STATE_BUCKET" -backend-config="prefix=blogravel/$GCP_ENVIRONMENT"`. Create the resources in the design with explicit minimum-small sizing, private Cloud SQL networking, separate runtime and Jenkins deployment service accounts, least-privilege IAM, and service-account access to only the intended Secret Manager and storage resources. Configure web public invoker access only; worker remains authenticated. Make every image reference use `var.image_digest`.

- [ ] **Step 5: Format and validate Terraform**

Run:

```bash
terraform -chdir=infrastructure/gcp/bootstrap fmt -check -recursive
terraform -chdir=infrastructure/gcp/bootstrap init -backend=false
terraform -chdir=infrastructure/gcp/bootstrap validate
terraform -chdir=infrastructure/gcp/app fmt -check -recursive
terraform -chdir=infrastructure/gcp/app init -backend=false
terraform -chdir=infrastructure/gcp/app validate
cd blogravel && php artisan test --compact tests/Unit/Deployment/GcpDeploymentConfigurationTest.php
```

Expected: all commands pass without contacting a live GCP project.

- [ ] **Step 6: Commit the Terraform stacks**

```bash
git add infrastructure/gcp blogravel/tests/Unit/Deployment/GcpDeploymentConfigurationTest.php
git commit -m "Feature: add GCP Cloud Run infrastructure"
```

### Task 3: Add the GCP Jenkins deployment pipeline

**Files:**
- Create: `Jenkinsfile.gcp`
- Modify: `blogravel/tests/Unit/Deployment/GcpDeploymentConfigurationTest.php`

**Interfaces:**
- Consumes: `RELEASE_TAG`, `GCP_PROJECT_ID`, `GCP_ENVIRONMENT`, Artifact Registry repository, Terraform variable values, and a Jenkins-bound GCP service-account credential.
- Produces: one Artifact Registry digest deployed to both Cloud Run services, migrations, and a public `/up` smoke check.

- [ ] **Step 1: Write failing Jenkinsfile assertions**

Assert the pipeline calls `ci/validate-release.sh`, authenticates with a file credential rather than literal JSON, builds and pushes the image, captures a digest using `gcloud artifacts docker images describe`, runs `terraform init`, `terraform apply`, deploys the same digest to `blogravel-web` and `blogravel-worker`, executes migrations as a Cloud Run Job, and fails on `/up` non-2xx.

- [ ] **Step 2: Run the focused test to verify it fails**

Run: `cd blogravel && php artisan test --compact tests/Unit/Deployment/GcpDeploymentConfigurationTest.php`

Expected: FAIL because `Jenkinsfile.gcp` is absent.

- [ ] **Step 3: Implement the declarative pipeline**

Use explicit parameters and Jenkins file credentials. Authenticate through `gcloud auth activate-service-account --key-file="$GOOGLE_APPLICATION_CREDENTIALS"`, configure Docker for Artifact Registry, tag images with the tag commit SHA, and pass the immutable digest to Terraform as `-var="image_digest=$IMAGE_DIGEST"`. Run plan output review before apply, then run the migration job and curl the configured public URL's `/up` endpoint. Do not log credentials or Terraform secret values.

- [ ] **Step 4: Run focused configuration validation and commit**

Run the focused Pest test and both Terraform validation sequences. Then commit:

```bash
git add Jenkinsfile.gcp blogravel/tests/Unit/Deployment/GcpDeploymentConfigurationTest.php
git commit -m "Feature: add GCP tagged deployment pipeline"
```

### Task 4: Document GCP provisioning and operations

**Files:**
- Create: `docs/gcp-cloud-run-deployment.md`

**Interfaces:**
- Consumes: Terraform, Jenkins, and Cloud Run contracts from Tasks 1-3.
- Produces: reproducible bootstrap/deploy/operate/rollback instructions.

- [ ] **Step 1: Document state and identity bootstrap**

Document creating the GCP project, billing budget alerts, bootstrap Terraform apply, state bucket output, Jenkins service-account credential creation, and least-privilege access. Keep all exact project IDs and service-account emails as operator-supplied values, never repository defaults.

- [ ] **Step 2: Document application configuration**

List Secret Manager values for app key, database password, mail, S3-compatible GCS credentials, and domains. Specify `QUEUE_CONNECTION=database`, Cloud SQL private connectivity, `FILESYSTEM_DISK=s3`, root/wildcard domain mapping, and Cloud Scheduler's authenticated job invocation.

- [ ] **Step 3: Document validation, backup, cost, and rollback**

Require Terraform plan review, Jenkins result, Cloud Run revision health, `/up`, admin and tenant-host smoke checks, queue failure inspection, scheduler job history, and a restore test from GCS backups. State the Cloud SQL plus min-one worker budget caveat. Roll back a prior image digest while treating database rollback as an explicit restore/migration decision.

- [ ] **Step 4: Commit the runbook**

```bash
git add docs/gcp-cloud-run-deployment.md
git commit -m "Docs: add GCP Cloud Run deployment runbook"
```
