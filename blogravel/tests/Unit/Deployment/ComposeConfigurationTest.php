<?php

use Symfony\Component\Process\Process;

function composeConfiguration(string $composeFile = 'compose.yaml', ?string $profile = null, array $environment = []): array
{
    $basePath = dirname(__DIR__, 3);
    $arguments = [
        'docker',
        'compose',
        '--env-file',
        '.env.example',
        '--file',
        $composeFile,
    ];

    if ($profile !== null) {
        $arguments[] = '--profile';
        $arguments[] = $profile;
    }

    $arguments[] = 'config';
    $arguments[] = '--format';
    $arguments[] = 'json';

    $process = new Process($arguments, cwd: $basePath, env: $environment);

    $process->run();

    expect($process->isSuccessful())->toBeTrue($process->getErrorOutput());

    return json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
}

function composeConfigurationProcess(string $composeFile = 'compose.yaml', array $environment = []): Process
{
    $basePath = dirname(__DIR__, 3);
    $process = new Process([
        'docker',
        'compose',
        '--env-file',
        '.env.example',
        '--file',
        $composeFile,
        'config',
        '--format',
        'json',
    ], cwd: $basePath, env: $environment);

    $process->run();

    return $process;
}

function exampleEnvironmentKeys(): array
{
    $lines = file(dirname(__DIR__, 3).'/.env.example', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    return array_values(array_filter(array_map(
        static fn (string $line): ?string => preg_match('/^([A-Z][A-Z0-9_]*)=/', $line, $matches) === 1
            ? $matches[1]
            : null,
        $lines,
    )));
}

function exampleEnvironment(): string
{
    return file_get_contents(dirname(__DIR__, 3).'/.env.example');
}

function composeFile(string $composeFile = 'compose.yaml'): string
{
    return file_get_contents(dirname(__DIR__, 3).'/'.$composeFile);
}

it('documents the required self-hosted environment keys', function (): void {
    expect(exampleEnvironmentKeys())->toContain(...[
        'APP_KEY',
        'APP_ENV',
        'APP_DEBUG',
        'APP_URL',
        'APP_PORT',
        'DB_CONNECTION',
        'DB_HOST',
        'DB_PORT',
        'DB_DATABASE',
        'DB_USERNAME',
        'DB_PASSWORD',
        'REDIS_CLIENT',
        'REDIS_HOST',
        'REDIS_PASSWORD',
        'REDIS_PORT',
        'CACHE_STORE',
        'QUEUE_CONNECTION',
        'REDIS_QUEUE_CONNECTION',
        'REDIS_QUEUE',
        'MAIL_MAILER',
        'MAIL_HOST',
        'MAIL_PORT',
        'MAIL_FROM_ADDRESS',
        'TENANCY_PLATFORM_DOMAIN',
        'TENANCY_RESERVED_LABELS',
        'SESSION_DRIVER',
        'SESSION_DOMAIN',
        'FILESYSTEM_DISK',
        'BACKUP_DISK',
        'BACKUP_PATH',
        'BILLING_ENABLED',
        'STRIPE_SECRET',
        'STRIPE_WEBHOOK_SECRET',
        'WWWUSER',
        'WWWGROUP',
        'OCTANE_SERVER',
    ]);
});

it('documents local defaults and production overrides without requiring production secrets', function (): void {
    $environment = exampleEnvironment();

    expect($environment)
        ->toContain("APP_ENV=local\n")
        ->toContain("APP_KEY=\n")
        ->toContain("APP_DEBUG=true\n")
        ->toContain("DB_PASSWORD=password\n")
        ->toContain("MAIL_HOST=mailpit\nMAIL_PORT=1025\n")
        ->toContain("SESSION_DOMAIN=.blogravel.com\n")
        ->toContain("BACKUP_DISK=local\n")
        ->toContain("OCTANE_SERVER=frankenphp\n");

    expect($environment)
        ->toContain('provision APP_KEY through your secret manager (never commit it)')
        ->toContain('Production: use APP_ENV=production, APP_DEBUG=false')
        ->toContain('Production: use a strong, unique database password')
        ->toContain('Mailpit is development-only. Production: use a real SMTP/provider host')
        ->toContain('Production: set this to the parent')
        ->toContain('production: use durable S3-compatible storage for backups/media');
});

it('requires explicit database passwords in Compose while keeping local examples usable', function (): void {
    expect(composeFile())
        ->toContain('${DB_PASSWORD:?DB_PASSWORD must be set}')
        ->not->toContain('PGADMIN_DEFAULT_PASSWORD');

    expect(composeFile('compose.dev.yaml'))
        ->toContain('${DB_PASSWORD:?DB_PASSWORD must be set}')
        ->toContain('${PGADMIN_DEFAULT_PASSWORD:-local-development-only-password}')
        ->not->toContain('${PGADMIN_DEFAULT_PASSWORD:?PGADMIN_DEFAULT_PASSWORD must be set}');

    expect(exampleEnvironment())
        ->toContain("DB_PASSWORD=password\n")
        ->toContain("PGADMIN_DEFAULT_PASSWORD=secret\n");
});

it('documents pgAdmin as development-only with a local fallback password', function (): void {
    expect(composeFile('compose.dev.yaml'))
        ->toContain('# Development-only database inspector.')
        ->toContain('PGADMIN_DEFAULT_PASSWORD:')
        ->toContain('local-development-only-password');
});

it('validates both Compose stacks without unresolved variables', function (): void {
    $configuration = json_encode(composeConfiguration(), JSON_THROW_ON_ERROR);
    $developmentConfiguration = json_encode(composeConfiguration('compose.dev.yaml'), JSON_THROW_ON_ERROR);

    expect($configuration)->not->toContain('${');
    expect($developmentConfiguration)->not->toContain('${');
});

it('uses the shell environment override for the Octane server', function (): void {
    $configuration = composeConfiguration('compose.dev.yaml', environment: ['OCTANE_SERVER' => 'roadrunner']);

    expect($configuration['services']['laravel.test']['environment']['SUPERVISOR_PHP_COMMAND'])
        ->toContain('--server=roadrunner');
});

it('fails Compose config when the database password is missing', function (): void {
    $process = composeConfigurationProcess(environment: ['DB_PASSWORD' => null]);

    expect($process->isSuccessful())->toBeFalse()
        ->and($process->getErrorOutput())->toContain('DB_PASSWORD must be set');
});

it('keeps production services and internal services off host ports', function (): void {
    $services = composeConfiguration()['services'];

    expect($services)->toHaveKeys(['laravel.test', 'pgsql', 'redis', 'queue', 'scheduler', 'prepare-composer', 'prepare-frontend'])
        ->not->toHaveKeys(['pgadmin', 'mailpit']);

    foreach (['pgsql', 'redis', 'queue', 'scheduler', 'prepare-composer', 'prepare-frontend'] as $service) {
        if (! isset($services[$service])) {
            continue;
        }

        expect($services[$service]['ports'] ?? [])->toBe([]);
    }

    expect($services['laravel.test']['ports'])->toHaveCount(1)
        ->and($services['laravel.test']['ports'][0]['target'])->toBe(8080);
});

it('includes development services in the local Compose stack', function (): void {
    $services = composeConfiguration('compose.dev.yaml')['services'];

    expect($services)->toHaveKeys(['prepare-composer', 'laravel.test', 'pgsql', 'redis', 'queue', 'scheduler', 'pgadmin', 'mailpit']);
});

it('defines readiness and restart policies for every long-running production service', function (): void {
    $configuration = composeConfiguration();
    $services = $configuration['services'];

    foreach (['laravel.test', 'pgsql', 'redis', 'queue', 'scheduler'] as $service) {
        expect($services[$service]['restart'])->toBe('unless-stopped')
            ->and($services[$service])->toBeArray();
    }

    foreach (['laravel.test', 'pgsql', 'redis'] as $service) {
        expect($services[$service])->toHaveKey('healthcheck');
    }

    expect($services['laravel.test']['depends_on']['pgsql']['condition'])->toBe('service_healthy')
        ->and($services['laravel.test']['depends_on']['redis']['condition'])->toBe('service_healthy')
        ->and($services['queue']['depends_on']['prepare-composer']['condition'])->toBe('service_completed_successfully')
        ->and($services['scheduler']['depends_on']['prepare-frontend']['condition'])->toBe('service_completed_successfully')
        ->and($services['scheduler']['command'])->toContain('schedule:work');
});

it('persists database and redis data in bind-mounted directories', function (): void {
    $configuration = composeConfiguration();

    expect($configuration)->not->toHaveKey('volumes');
    expect(collect($configuration['services']['pgsql']['volumes'])->contains(
        fn (array $mount): bool => $mount['source'] === dirname(__DIR__, 3).'/docker/volumes/pgsql'
            && $mount['target'] === '/var/lib/postgresql',
    ))->toBeTrue();
    expect(collect($configuration['services']['redis']['volumes'])->contains(
        fn (array $mount): bool => $mount['source'] === dirname(__DIR__, 3).'/docker/volumes/redis'
            && $mount['target'] === '/data',
    ))->toBeTrue();
});

it('uses only bind mounts in both Compose stacks', function (): void {
    foreach ([composeConfiguration(), composeConfiguration('compose.dev.yaml')] as $configuration) {
        expect($configuration)->not->toHaveKey('volumes');

        foreach ($configuration['services'] as $service) {
            foreach ($service['volumes'] ?? [] as $mount) {
                expect($mount['type'])->toBe('bind');
            }
        }
    }

    expect(composeConfiguration()['services']['laravel.test']['volumes'][0]['source'])
        ->toBe(dirname(__DIR__, 3));
    expect(composeConfiguration('compose.dev.yaml')['services']['laravel.test']['volumes'][0]['source'])
        ->toBe(dirname(__DIR__, 3));
});

it('prepares ignored production dependencies through bind-mounted services', function (): void {
    $services = composeConfiguration()['services'];

    expect($services)->toHaveKeys(['prepare-composer', 'prepare-frontend'])
        ->and($services['laravel.test']['build']['dockerfile'])->toBe('docker/production/Dockerfile')
        ->and($services['laravel.test']['volumes'][0]['target'])->toBe('/app')
        ->and($services['laravel.test']['environment']['CONTAINER_ROLE'])->toBe('web')
        ->and($services['queue']['environment']['CONTAINER_ROLE'])->toBe('worker')
        ->and($services['scheduler']['environment']['CONTAINER_ROLE'])->toBe('scheduler')
        ->and($services['laravel.test']['depends_on']['prepare-composer']['condition'])->toBe('service_completed_successfully')
        ->and($services['laravel.test']['depends_on']['prepare-frontend']['condition'])->toBe('service_completed_successfully');
});

it('keeps the production image free of Docker-managed volumes', function (): void {
    expect(file_get_contents(dirname(__DIR__, 3).'/docker/production/Dockerfile'))
        ->not->toContain("\nVOLUME ");
});

it('keeps the Compose and Octane runtimes on FrankenPHP', function (): void {
    $compose = composeConfiguration('compose.dev.yaml');
    $octane = file_get_contents(dirname(__DIR__, 3).'/config/octane.php');

    expect($compose['services']['laravel.test']['environment']['SUPERVISOR_PHP_COMMAND'])
        ->toContain('--server=frankenphp')
        ->and($octane)->toContain("'server' => env('OCTANE_SERVER', 'frankenphp')");
});
