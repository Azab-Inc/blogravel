# Laravel Cloud Deployment

This runbook deploys a validated Blogravel release tag through Laravel Cloud.
Laravel Cloud owns the application runtime, managed queue, Object Storage, and
application secrets. Terraform does not manage this target.

## Prerequisites

- A Laravel Cloud organization, application, and production environment
- The `Azab-Inc/blogravel` repository connected to the environment's tracked
  branch
- Root and wildcard DNS records for the production domain
- A Jenkins credential containing the Laravel Cloud deploy-hook URL
- A tagged release created with the [Self-Hosted Release Process](self-hosted-release-process.md)

The deployed tag commit must be reachable from the environment's tracked
branch. Laravel Cloud deploy hooks accept a `commit_hash` from that branch;
the Jenkins pipeline sends the commit resolved from the selected immutable tag.

## Configure Laravel Cloud

Create the production environment in the same region as its Laravel Cloud
resources. Configure build and deploy commands through Laravel Cloud. Enable a
deploy hook under **Settings > Deployments** and save its full URL as a Jenkins
secret-text credential.

Configure the root host and wildcard tenant host:

- `example.com`
- `*.example.com`

Point the required Cloudflare DNS records at the Laravel Cloud custom-hostname
targets. Provision certificates for both host patterns. Tenant routes require
the original `Host` header and HTTPS forwarding information to reach Laravel.

Set environment values in Laravel Cloud, not in the repository:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://example.com
TENANCY_PLATFORM_DOMAIN=example.com
SESSION_DOMAIN=.example.com
QUEUE_CONNECTION=cloud
FILESYSTEM_DISK=s3
BACKUP_DISK=s3
BACKUP_PATH=backups
```

Store the application key, database credentials, mail credentials, Object
Storage credentials, and any enabled third-party credentials as Laravel Cloud
environment secrets. Do not create a production `.env` in the repository.

## Object Storage

Create Laravel Cloud Object Storage for the environment and configure the
S3-compatible disk values it provides:

```dotenv
AWS_ACCESS_KEY_ID=<Laravel Cloud Object Storage access key>
AWS_SECRET_ACCESS_KEY=<Laravel Cloud Object Storage secret>
AWS_DEFAULT_REGION=<Laravel Cloud Object Storage region>
AWS_BUCKET=<Laravel Cloud Object Storage bucket>
AWS_ENDPOINT=<Laravel Cloud Object Storage endpoint>
AWS_USE_PATH_STYLE_ENDPOINT=true
```

Use this disk for media and encrypted backup archives. Retain the backup
encryption key separately and test restoration on a disposable environment.

## Managed Queue

Create one standard Flex managed queue and set it as the environment default.
The current Laravel `^13.7` dependency satisfies Laravel Cloud's managed-queue
version requirement. `QUEUE_CONNECTION=cloud` sends jobs without an explicit
connection to this queue.

Do not configure Horizon, a Redis queue worker, or `php artisan queue:work` for
this target. Use Laravel Cloud's **Queues** dashboard to inspect, retry, or
delete failed jobs and to observe queue processing.

## Jenkins Deployment

Create a manual Jenkins pipeline using `Jenkinsfile.laravel-cloud`. Supply:

- `RELEASE_TAG`: an annotated `vMAJOR.MINOR.PATCH` release tag
- `LARAVEL_CLOUD_DEPLOY_HOOK_CREDENTIALS_ID`: Jenkins secret-text credential ID
  containing the Laravel Cloud deploy-hook URL

The pipeline runs `ci/validate-release.sh`, resolves the tag commit SHA, and
posts it to Laravel Cloud's deploy hook. Laravel Cloud then builds and deploys
that exact commit with its zero-downtime release process.

## Verification

After Laravel Cloud reports a successful deployment, verify:

```bash
curl --fail --silent --show-error https://example.com/up
```

Also verify the root-domain admin login, a tenant URL such as
`https://acme.example.com`, deployment logs, Object Storage access, and the
managed-queue dashboard.

## Rollback

Select a previously validated release tag in the same Jenkins pipeline. The
pipeline redeploys that tag's resolved commit through the deploy hook. Preserve
the failed deployment logs before rollback.

Database/schema rollback is an operator decision. Review the relevant
migrations and restore procedure first; restore a verified pre-deployment
database and Object Storage backup only when the failed release requires it.
