<?php

use Symfony\Component\Process\Process;

function releaseValidationScriptPath(): string
{
    return dirname(__DIR__, 4).'/ci/validate-release.sh';
}

function temporaryReleaseRepository(): string
{
    $repositoryPath = sys_get_temp_dir().'/blogravel-release-validation-'.bin2hex(random_bytes(8));

    mkdir($repositoryPath.'/blogravel', 0o755, true);
    mkdir($repositoryPath.'/bin', 0o755, true);
    file_put_contents($repositoryPath.'/blogravel/.env.example', "APP_ENV=testing\n");

    foreach (['composer', 'php', 'npm', 'docker'] as $command) {
        $commandPath = $repositoryPath.'/bin/'.$command;
        file_put_contents($commandPath, <<<'BASH'
#!/usr/bin/env bash
set -euo pipefail

if [[ "$(basename "$0")" == 'docker' ]]; then
    touch "$RELEASE_VALIDATION_SUCCESS_MARKER"
fi
BASH);
        chmod($commandPath, 0o755);
    }

    $commands = [
        ['git', 'init'],
        ['git', 'config', 'user.email', 'release-validation@example.com'],
        ['git', 'config', 'user.name', 'Release Validation'],
        ['git', 'add', '.'],
        ['git', 'commit', '-m', 'Initial release'],
        ['git', 'tag', '-a', 'v1.2.3', '-m', 'Release v1.2.3'],
        ['git', 'tag', 'v1.2.4'],
    ];

    foreach ($commands as $command) {
        $process = new Process($command, cwd: $repositoryPath);
        $process->mustRun();
    }

    return $repositoryPath;
}

function releaseValidationProcess(string $repositoryPath, string $releaseTag): Process
{
    $successMarker = $repositoryPath.'/release-validation-succeeded';
    $process = new Process(
        [releaseValidationScriptPath()],
        cwd: $repositoryPath,
        env: [
            'PATH' => $repositoryPath.'/bin:'.getenv('PATH'),
            'RELEASE_TAG' => $releaseTag,
            'RELEASE_VALIDATION_SUCCESS_MARKER' => $successMarker,
        ],
    );

    $process->run();

    return $process;
}

afterEach(function (): void {
    if (isset($this->releaseValidationRepositoryPath)) {
        (new Process(['rm', '-rf', $this->releaseValidationRepositoryPath]))->mustRun();
    }
});

it('checks out an annotated SemVer release tag before validation succeeds', function (): void {
    $this->releaseValidationRepositoryPath = temporaryReleaseRepository();
    $annotatedCommit = trim((new Process(
        ['git', 'rev-parse', 'v1.2.3^{commit}'],
        cwd: $this->releaseValidationRepositoryPath,
    ))->mustRun()->getOutput());

    $process = releaseValidationProcess($this->releaseValidationRepositoryPath, 'v1.2.3');

    expect($process->isSuccessful())->toBeTrue($process->getErrorOutput())
        ->and($this->releaseValidationRepositoryPath.'/release-validation-succeeded')->toBeFile()
        ->and(trim((new Process(
            ['git', 'rev-parse', 'HEAD'],
            cwd: $this->releaseValidationRepositoryPath,
        ))->mustRun()->getOutput()))->toBe($annotatedCommit);
});

it('rejects a lightweight release tag before validation succeeds', function (): void {
    $this->releaseValidationRepositoryPath = temporaryReleaseRepository();

    $process = releaseValidationProcess($this->releaseValidationRepositoryPath, 'v1.2.4');

    expect($process->isSuccessful())->toBeFalse()
        ->and($process->getErrorOutput())->toContain('RELEASE_TAG must be an annotated tag')
        ->and($this->releaseValidationRepositoryPath.'/release-validation-succeeded')->not->toBeFile();
});

it('requires the release script to execute the complete validation gate', function (): void {
    expect(file_exists(releaseValidationScriptPath()))->toBeTrue();

    $script = file_get_contents(releaseValidationScriptPath());

    expect($script)
        ->toContain('git cat-file -t "$RELEASE_TAG"')
        ->toContain('composer lint:check')
        ->toContain('php artisan test --compact')
        ->toContain('npm run build')
        ->toContain('docker compose --env-file .env.example config --quiet');
});
