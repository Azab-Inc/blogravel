<?php

function hostingerDeploymentPipeline(): string
{
    return file_get_contents(dirname(__DIR__, 4).'/Jenkinsfile.hostinger');
}

it('deploys a validated release commit to Hostinger with Jenkins-bound SSH credentials', function (): void {
    expect(hostingerDeploymentPipeline())
        ->toContain("label 'php85'")
        ->toContain('disableConcurrentBuilds()')
        ->toContain('RELEASE_TAG')
        ->toContain('RELEASE_TAG = "${params.RELEASE_TAG}"')
        ->toContain("sh './ci/validate-release.sh'")
        ->not->toContain('RELEASE_TAG="${params.RELEASE_TAG}"')
        ->not->toContain('[[')
        ->not->toContain('=~')
        ->toContain('RELEASE_COMMIT = sh(')
        ->toContain('git rev-parse "${RELEASE_TAG}^{commit}"')
        ->toContain('withCredentials([usernamePassword(')
        ->toContain('sshpass')
        ->toContain('git fetch --tags')
        ->toContain('git checkout --detach "$2"')
        ->toContain('docker compose --env-file blogravel/.env -f blogravel/compose.yaml up -d --build')
        ->toContain('php artisan migrate --force')
        ->toContain('php artisan optimize:clear')
        ->toContain('docker compose --env-file blogravel/.env -f blogravel/compose.yaml restart laravel.test queue scheduler')
        ->toContain('curl --fail --silent --show-error http://127.0.0.1:${APP_PORT:-8080}/up');
});
