<?php

function laravelCloudDeploymentPipeline(): string
{
    return file_get_contents(dirname(__DIR__, 4).'/Jenkinsfile.laravel-cloud');
}

it('deploys a validated release commit through the Laravel Cloud deploy hook', function (): void {
    expect(laravelCloudDeploymentPipeline())
        ->toContain("label 'php85'")
        ->toContain('disableConcurrentBuilds()')
        ->toContain('RELEASE_TAG')
        ->toContain('RELEASE_TAG = "${params.RELEASE_TAG}"')
        ->toContain("sh './ci/validate-release.sh'")
        ->toContain('RELEASE_COMMIT = sh(')
        ->toContain('git rev-parse "${RELEASE_TAG}^{commit}"')
        ->toContain('withCredentials([string(')
        ->toContain('LARAVEL_CLOUD_DEPLOY_HOOK')
        ->toContain('curl --fail --silent --show-error --request POST "${LARAVEL_CLOUD_DEPLOY_HOOK}?commit_hash=${RELEASE_COMMIT}"')
        ->not->toContain('php artisan horizon')
        ->not->toContain('terraform')
        ->not->toMatch('/https:\/\/cloud\.laravel\.com\/[^$]/');
});
