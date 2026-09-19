<?php

use Symfony\Component\Process\Process;

function productionComposeConfiguration(): array
{
    $basePath = dirname(__DIR__, 3);
    $process = new Process([
        'docker',
        'compose',
        '--env-file',
        '.env.example',
        '--profile',
        'production',
        'config',
        '--format',
        'json',
    ], cwd: $basePath);

    $process->run();

    expect($process->isSuccessful())->toBeTrue($process->getErrorOutput());

    return json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
}

it('keeps development services and internal services off host ports in production', function (): void {
    $services = productionComposeConfiguration()['services'];

    expect($services)->toHaveKeys(['laravel.test', 'pgsql', 'redis', 'queue', 'scheduler'])
        ->not->toHaveKeys(['pgadmin', 'mailpit']);

    foreach (['pgsql', 'redis', 'queue', 'scheduler'] as $service) {
        expect($services[$service]['ports'] ?? [])->toBe([]);
    }

    expect($services['laravel.test']['ports'])->toHaveCount(1)
        ->and($services['laravel.test']['ports'][0]['target'])->toBe(80);
});

it('defines readiness and restart policies for every long-running production service', function (): void {
    $configuration = productionComposeConfiguration();
    $services = $configuration['services'];

    foreach (['laravel.test', 'pgsql', 'redis', 'queue', 'scheduler'] as $service) {
        expect($services[$service]['restart'])->toBe('unless-stopped')
            ->and($services[$service])->toHaveKey('healthcheck');
    }

    expect($services['laravel.test']['depends_on']['pgsql']['condition'])->toBe('service_healthy')
        ->and($services['laravel.test']['depends_on']['redis']['condition'])->toBe('service_healthy')
        ->and($services['queue']['depends_on']['laravel.test']['condition'])->toBe('service_healthy')
        ->and($services['scheduler']['depends_on']['laravel.test']['condition'])->toBe('service_healthy')
        ->and($services['scheduler']['command'])->toContain('schedule:work')
        ->and($services['scheduler']['healthcheck']['test'][1])->toContain("pgrep -f 'artisan schedule:work'");
});

it('persists database and redis data in named volumes', function (): void {
    $configuration = productionComposeConfiguration();

    expect($configuration['volumes'])->toHaveKeys(['sail-pgsql', 'sail-redis'])
        ->and($configuration['services']['pgsql']['volumes'])->toContainEqual([
            'type' => 'volume',
            'source' => 'sail-pgsql',
            'target' => '/var/lib/postgresql',
            'volume' => [],
        ])
        ->and($configuration['services']['redis']['volumes'])->toContainEqual([
            'type' => 'volume',
            'source' => 'sail-redis',
            'target' => '/data',
            'volume' => [],
        ]);
});
