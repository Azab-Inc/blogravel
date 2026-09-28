<?php

function deploymentFile(string $path): string
{
    return file_get_contents(dirname(__DIR__, 4).'/'.$path);
}

it('defines a production container runtime for Cloud Run roles', function (): void {
    expect(deploymentFile('blogravel/docker/production/Dockerfile'))
        ->toContain('dunglas/frankenphp:1-php8.5-bookworm')
        ->toContain('composer install --no-dev')
        ->toContain('npm ci')
        ->toContain('npm run build')
        ->toContain('COPY --from=frontend')
        ->not->toContain('COPY .env')
        ->toContain('ENTRYPOINT ["/usr/local/bin/blogravel-entrypoint"]');

    expect(deploymentFile('blogravel/docker/production/entrypoint.sh'))
        ->toContain('CONTAINER_ROLE')
        ->toContain('web')
        ->toContain('worker')
        ->toContain('scheduler')
        ->toContain('exit 1');

    expect(deploymentFile('blogravel/docker/production/supervisord.conf'))
        ->toContain('php artisan queue:work database --sleep=3 --tries=3 --timeout=0');

    expect(deploymentFile('blogravel/docker/production/Caddyfile'))
        ->toContain('{$PORT:8080}');
});

it('defines only the approved GCP Terraform resources', function (): void {
    $terraform = deploymentFile('infrastructure/gcp/app/main.tf');

    expect(deploymentFile('infrastructure/gcp/app/backend.tf'))->toContain('backend "gcs"');
    expect(deploymentFile('infrastructure/gcp/bootstrap/main.tf'))
        ->toContain('uniform_bucket_level_access = true')
        ->toContain('versioning');
    expect($terraform)
        ->toContain('google_artifact_registry_repository')
        ->toContain('google_sql_database_instance')
        ->toContain('google_cloud_run_v2_service')
        ->toContain('google_cloud_run_v2_job')
        ->toContain('google_cloud_scheduler_job')
        ->toContain('google_storage_bucket')
        ->toContain('google_secret_manager_secret')
        ->toContain('google_service_account')
        ->not->toContain('hostinger')
        ->not->toContain('laravel_cloud');
});

it('deploys the same immutable image through the GCP pipeline', function (): void {
    $pipeline = deploymentFile('Jenkinsfile.gcp');

    expect($pipeline)
        ->toContain("sh './ci/validate-release.sh'")
        ->toContain('file(')
        ->toContain('gcloud auth activate-service-account')
        ->toContain('gcloud artifacts docker images describe')
        ->toContain('terraform -chdir=infrastructure/gcp/app init')
        ->toContain('terraform -chdir=infrastructure/gcp/app apply')
        ->toContain('image_digest=$IMAGE_DIGEST')
        ->toContain('gcloud run jobs execute')
        ->toContain('/up');
});
