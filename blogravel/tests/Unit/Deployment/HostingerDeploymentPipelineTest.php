<?php

function hostingerDeploymentPipeline(): string
{
    return file_get_contents(dirname(__DIR__, 4).'/Jenkinsfile.hostinger');
}

it('deploys a validated release tag to Hostinger with Jenkins-bound SSH credentials', function (): void {
    expect(hostingerDeploymentPipeline())
        ->toContain("label 'php85'")
        ->toContain('disableConcurrentBuilds()')
        ->toContain('RELEASE_TAG')
        ->toContain('RELEASE_TAG="${params.RELEASE_TAG}" ./ci/validate-release.sh')
        ->toContain('withCredentials([usernamePassword(')
        ->toContain('sshpass')
        ->toContain('git fetch --tags')
        ->toContain('git checkout')
        ->toContain('docker compose --env-file blogravel/.env -f blogravel/compose.yaml up -d --build')
        ->toContain('php artisan migrate --force')
        ->toContain('php artisan optimize:clear')
        ->toContain('docker compose --env-file blogravel/.env -f blogravel/compose.yaml restart laravel.test queue scheduler')
        ->toContain('curl --fail --silent --show-error http://127.0.0.1:${APP_PORT:-8080}/up');
});
