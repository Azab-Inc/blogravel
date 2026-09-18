<?php

namespace App\Services;

use App\Enums\Role;
use App\Jobs\GenerateTenantExportJob;
use App\Models\AiProvider;
use App\Models\ApiKey;
use App\Models\Backup;
use App\Models\BackupRule;
use App\Models\Category;
use App\Models\Comment;
use App\Models\Invitation;
use App\Models\Media;
use App\Models\OutboundWebhook;
use App\Models\Page;
use App\Models\Post;
use App\Models\Setting;
use App\Models\Subscriber;
use App\Models\Subscription;
use App\Models\Tag;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Webhook;
use Carbon\CarbonImmutable;
use Closure;
use DateTimeInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use RuntimeException;
use ZipArchive;

class DataExportService
{
    private const EXPIRY_HOURS = 24;

    private const RECOVERY_DAYS = 30;

    /** @var list<string> */
    private const SAFE_SETTING_KEYS = [
        'authors_can_view_others_posts',
        'theme_enabled',
        'active_theme',
    ];

    public function queue(Tenant $tenant, User $requestedBy, string $format): string
    {
        $this->validateFormat($format);
        $authorizedRequester = User::query()->find($requestedBy->getKey());
        $tenant = $authorizedRequester === null
            ? null
            : $this->authorizedTenant((string) $tenant->getKey(), $authorizedRequester);

        if ($tenant === null) {
            throw new AuthorizationException;
        }

        $identifier = (string) Str::uuid();
        $outputPath = 'exports/'.$identifier.'.zip';
        $expiresAt = now()->addHours(self::EXPIRY_HOURS);

        $this->writeManifest($identifier, [
            'tenant_id' => $tenant->getKey(),
            'requested_by' => $authorizedRequester->getKey(),
            'format' => $format,
            'output_path' => $outputPath,
            'expires_at' => $expiresAt->toIso8601String(),
        ]);

        Bus::dispatch(new GenerateTenantExportJob(
            (string) $tenant->getKey(),
            (string) $authorizedRequester->getKey(),
            $format,
            $outputPath,
            $expiresAt,
        ));

        return $identifier;
    }

    /**
     * @return array{tenant: Tenant, requester: User}|null
     */
    public function authorizeQueuedExport(string $tenantId, string $requestedById): ?array
    {
        $requester = User::query()->find($requestedById);
        if ($requester === null) {
            return null;
        }

        $tenant = $this->authorizedTenant($tenantId, $requester);
        if ($tenant === null) {
            return null;
        }

        return compact('tenant', 'requester');
    }

    public function generate(Tenant $tenant, string $format, string $outputPath): void
    {
        $this->validateFormat($format);
        $tenant = $this->verifiedTenant($tenant);
        $outputPath = $this->safePath($outputPath);
        $disk = Storage::disk('local');
        $temporaryDirectory = 'tmp/tenant-export-'.Str::uuid();
        $temporaryFiles = [];

        $disk->makeDirectory($temporaryDirectory);

        try {
            $datasets = $this->datasetDefinitions($tenant);

            if ($format === 'csv') {
                foreach ($datasets as $dataset) {
                    $archiveName = $dataset['name'].'.csv';
                    $temporaryPath = $temporaryDirectory.'/'.$archiveName;
                    $this->writeCsv($disk->path($temporaryPath), $dataset['headers'], $dataset['rows']());
                    $temporaryFiles[$archiveName] = $disk->path($temporaryPath);
                }
            } else {
                $archiveName = 'tenant-export.xlsx';
                $temporaryPath = $temporaryDirectory.'/'.$archiveName;
                $this->writeXlsx($disk->path($temporaryPath), $datasets);
                $temporaryFiles[$archiveName] = $disk->path($temporaryPath);
            }

            $this->copyMediaFiles($tenant, $temporaryDirectory, $temporaryFiles);
            $outputFullPath = $disk->path($outputPath);
            app('files')->makeDirectory(dirname($outputFullPath), 0755, true, true);

            $archive = new ZipArchive;
            if ($archive->open($outputFullPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('Unable to create tenant export archive.');
            }

            try {
                foreach ($temporaryFiles as $archiveName => $temporaryFile) {
                    if (! $archive->addFile($temporaryFile, $archiveName)) {
                        throw new RuntimeException("Unable to add {$archiveName} to tenant export archive.");
                    }
                }
            } finally {
                $archive->close();
            }
        } finally {
            $disk->deleteDirectory($temporaryDirectory);
        }
    }

    public function authorizeDownload(User $user, string $identifier): string
    {
        if (! preg_match('/\A[a-f0-9-]{36}\z/i', $identifier)) {
            abort(404);
        }

        $manifestPath = $this->manifestPath($identifier);
        $disk = Storage::disk('local');
        if (! $disk->exists($manifestPath)) {
            abort(404);
        }

        $manifest = json_decode($disk->get($manifestPath), true);
        if (! is_array($manifest)) {
            abort(404);
        }

        $expiresAt = CarbonImmutable::parse($manifest['expires_at'] ?? 'now');
        if ($expiresAt->isPast()) {
            $this->deleteManifestAndArchive($manifestPath, $manifest);
            abort(410);
        }

        if (! in_array($user->role, [Role::Admin, Role::SuperAdmin], true)) {
            throw new AuthorizationException;
        }

        $isRequester = (string) $user->getKey() === (string) ($manifest['requested_by'] ?? '');
        if (! $isRequester && ! $user->isSuperAdmin()) {
            throw new AuthorizationException;
        }

        if ($user->isSuperAdmin()) {
            $tenant = Tenant::withTrashed()->find($manifest['tenant_id'] ?? null);
            if ($tenant === null || ! $this->isWithinRecoveryWindow($tenant)) {
                throw new AuthorizationException;
            }
        }

        $outputPath = $manifest['output_path'] ?? null;
        if (! is_string($outputPath) || $this->safePath($outputPath) !== $outputPath || ! $disk->exists($outputPath)) {
            abort(404);
        }

        return $outputPath;
    }

    public function purgeExpired(): void
    {
        $disk = Storage::disk('local');

        foreach ($disk->files('exports') as $manifestPath) {
            if (! str_ends_with($manifestPath, '.json')) {
                continue;
            }

            $manifest = json_decode($disk->get($manifestPath), true);
            if (! is_array($manifest) || ! isset($manifest['expires_at'])) {
                continue;
            }

            if (CarbonImmutable::parse($manifest['expires_at'])->isPast()) {
                $this->deleteManifestAndArchive($manifestPath, $manifest);
            }
        }
    }

    /**
     * @return list<array{name: string, headers: list<string>, rows: iterable<array<int, mixed>>}>
     */
    private function datasetDefinitions(Tenant $tenant): array
    {
        return [
            $this->dataset('tenant', [
                'id', 'domain', 'slug', 'custom_domain', 'name', 'plan', 'created_at', 'updated_at', 'deleted_at',
            ], fn (): iterable => [
                $this->row($tenant, ['id', 'domain', 'slug', 'custom_domain', 'name', 'plan', 'created_at', 'updated_at', 'deleted_at']),
            ]),
            $this->modelDataset('users', User::class, [
                'id', 'tenant_id', 'name', 'first_name', 'last_name', 'email', 'email_verified_at', 'role',
                'can_invite', 'created_at', 'updated_at', 'deleted_at', 'deleted_by', 'deletion_reason',
            ], $tenant, true),
            $this->modelDataset('posts', Post::class, [
                'id', 'tenant_id', 'author_id', 'title', 'slug', 'content', 'excerpt', 'status', 'published_at',
                'created_at', 'updated_at',
            ], $tenant),
            $this->modelDataset('pages', Page::class, [
                'id', 'tenant_id', 'title', 'slug', 'content', 'status', 'created_at', 'updated_at',
            ], $tenant),
            $this->modelDataset('categories', Category::class, [
                'id', 'tenant_id', 'name', 'slug', 'created_at', 'updated_at',
            ], $tenant),
            $this->modelDataset('tags', Tag::class, [
                'id', 'tenant_id', 'name', 'slug', 'created_at', 'updated_at',
            ], $tenant),
            $this->modelDataset('comments', Comment::class, [
                'id', 'tenant_id', 'post_id', 'author_name', 'author_email', 'content', 'status', 'created_at', 'updated_at',
            ], $tenant),
            $this->modelDataset('subscribers', Subscriber::class, [
                'id', 'tenant_id', 'email', 'name', 'status', 'created_at', 'updated_at',
            ], $tenant),
            $this->modelDataset('invitations', Invitation::class, [
                'id', 'tenant_id', 'email', 'role', 'accepted_at', 'expires_at', 'invited_by', 'type', 'created_at', 'updated_at',
            ], $tenant),
            $this->modelDataset('subscriptions', Subscription::class, [
                'id', 'tenant_id', 'stripe_id', 'stripe_status', 'stripe_plan', 'trial_ends_at', 'ends_at', 'created_at', 'updated_at',
            ], $tenant),
            $this->settingDataset($tenant),
            $this->modelDataset('media', Media::class, [
                'id', 'tenant_id', 'name', 'file_path', 'url', 'mime_type', 'size', 'created_at', 'updated_at',
            ], $tenant),
            $this->modelDataset('api-keys', ApiKey::class, [
                'id', 'tenant_id', 'name', 'abilities', 'rate_limit_per_minute', 'last_used_at', 'expires_at', 'created_at', 'updated_at',
            ], $tenant),
            $this->modelDataset('webhooks', Webhook::class, [
                'id', 'tenant_id', 'url', 'events', 'active', 'created_at', 'updated_at',
            ], $tenant),
            $this->modelDataset('outbound-webhooks', OutboundWebhook::class, [
                'id', 'tenant_id', 'url', 'events', 'is_active', 'created_at', 'updated_at',
            ], $tenant),
            $this->modelDataset('ai-providers', AiProvider::class, [
                'id', 'tenant_id', 'type', 'name', 'base_url', 'model', 'temperature', 'max_tokens', 'custom_template', 'enabled', 'created_at', 'updated_at',
            ], $tenant),
            $this->modelDataset('backup-rules', BackupRule::class, [
                'id', 'tenant_id', 'name', 'backup_content', 'schedule', 'destination', 'ftp_host', 'ftp_port', 'ftp_user', 'ftp_path', 'enabled', 'last_run_at', 'next_run_at', 'created_at', 'updated_at',
            ], $tenant),
            $this->modelDataset('backups', Backup::class, [
                'id', 'tenant_id', 'backup_rule_id', 'filename', 'path', 'size_bytes', 'disk', 'status', 'encrypted', 'delivered_via', 'completed_at', 'created_at', 'updated_at',
            ], $tenant),
        ];
    }

    /**
     * @param  list<string>  $headers
     * @param  Closure(): iterable<array<int, mixed>>  $rows
     * @return array{name: string, headers: list<string>, rows: iterable<array<int, mixed>>}
     */
    private function dataset(string $name, array $headers, Closure $rows): array
    {
        return compact('name', 'headers', 'rows');
    }

    /**
     * @param  class-string<Model>  $modelClass
     * @param  list<string>  $fields
     * @return array{name: string, headers: list<string>, rows: iterable<array<int, mixed>>}
     */
    private function modelDataset(string $name, string $modelClass, array $fields, Tenant $tenant, bool $withTrashed = false): array
    {
        return $this->dataset($name, $fields, function () use ($modelClass, $fields, $tenant, $withTrashed): iterable {
            $query = $modelClass::withoutGlobalScopes()
                ->select($fields)
                ->where('tenant_id', $tenant->getKey())
                ->orderBy('id');

            if ($withTrashed) {
                $query->withTrashed();
            }

            foreach ($query->cursor() as $record) {
                yield $this->row($record, $fields);
            }
        });
    }

    /**
     * @return array{name: string, headers: list<string>, rows: iterable<array<int, mixed>>}
     */
    private function settingDataset(Tenant $tenant): array
    {
        $fields = ['id', 'tenant_id', 'key', 'value', 'created_at', 'updated_at'];

        return $this->dataset('settings', $fields, function () use ($fields, $tenant): iterable {
            $query = Setting::withoutGlobalScopes()
                ->select($fields)
                ->where('tenant_id', $tenant->getKey())
                ->whereIn('key', self::SAFE_SETTING_KEYS)
                ->orderBy('key');

            foreach ($query->cursor() as $record) {
                yield $this->row($record, $fields);
            }
        });
    }

    /**
     * @param  list<string>  $fields
     * @return list<mixed>
     */
    private function row(Model $record, array $fields): array
    {
        return array_map(fn (string $field): mixed => $this->normalizeValue($record->getAttribute($field)), $fields);
    }

    private function normalizeValue(mixed $value): mixed
    {
        if ($value instanceof \BackedEnum) {
            return $value->value;
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format(DateTimeInterface::ATOM);
        }

        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        }

        return $value;
    }

    /** @param list<string> $headers */
    private function writeCsv(string $path, array $headers, iterable $rows): void
    {
        $handle = fopen($path, 'wb');
        if ($handle === false) {
            throw new RuntimeException("Unable to open {$path} for writing.");
        }

        try {
            fputcsv($handle, $headers);
            foreach ($rows as $row) {
                fputcsv($handle, array_map(fn (mixed $value): mixed => $this->normalizeValue($value), $row));
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param  list<array{name: string, headers: list<string>, rows: iterable<array<int, mixed>>}>  $datasets
     */
    private function writeXlsx(string $path, array $datasets): void
    {
        $writer = new Writer;
        $writer->openToFile($path);

        try {
            foreach ($datasets as $index => $dataset) {
                $sheet = $index === 0
                    ? $writer->getCurrentSheet()
                    : $writer->addNewSheetAndMakeItCurrent();
                $sheet->setName($dataset['name']);
                $writer->addRow(Row::fromValues($dataset['headers']));

                foreach (($dataset['rows'])() as $row) {
                    $writer->addRow(Row::fromValues(array_map(fn (mixed $value): mixed => $this->normalizeValue($value), $row)));
                }
            }
        } finally {
            $writer->close();
        }
    }

    /** @param array<string, string> $temporaryFiles */
    private function copyMediaFiles(Tenant $tenant, string $temporaryDirectory, array &$temporaryFiles): void
    {
        $publicDisk = Storage::disk('public');
        $localDisk = Storage::disk('local');

        $media = Media::withoutGlobalScopes()
            ->select(['file_path'])
            ->where('tenant_id', $tenant->getKey())
            ->orderBy('id')
            ->cursor();

        foreach ($media as $record) {
            $relativePath = $this->safeMediaPath((string) $record->file_path);
            if ($relativePath === null || ! $publicDisk->exists($relativePath)) {
                continue;
            }

            $archiveName = 'media-files/'.$relativePath;
            $temporaryPath = $temporaryDirectory.'/'.$archiveName;
            $localDisk->put($temporaryPath, $publicDisk->get($relativePath));
            $temporaryFiles[$archiveName] = $localDisk->path($temporaryPath);
        }
    }

    private function verifiedTenant(Tenant $tenant): Tenant
    {
        return Tenant::withoutGlobalScopes()->withTrashed()->findOrFail($tenant->getKey());
    }

    private function authorizedTenant(string $tenantId, User $requestedBy): ?Tenant
    {
        if ($requestedBy->isSuperAdmin()) {
            $tenant = Tenant::withTrashed()->find($tenantId);

            return $tenant !== null && $this->isWithinRecoveryWindow($tenant) ? $tenant : null;
        }

        if (! $requestedBy->isAdmin()
            || (string) $requestedBy->tenant_id !== $tenantId) {
            return null;
        }

        $tenant = Tenant::withTrashed()->find($tenantId);

        return $tenant !== null && $this->isWithinRecoveryWindow($tenant) ? $tenant : null;
    }

    private function isWithinRecoveryWindow(Tenant $tenant): bool
    {
        return $tenant->deleted_at === null
            || ! $tenant->deleted_at->isBefore(now()->subDays(self::RECOVERY_DAYS));
    }

    private function validateFormat(string $format): void
    {
        if (! in_array($format, ['csv', 'xlsx'], true)) {
            throw new InvalidArgumentException('Export format must be csv or xlsx.');
        }
    }

    private function safePath(string $path): string
    {
        $path = ltrim(str_replace('\\', '/', $path), '/');
        $segments = explode('/', $path);
        if ($path === '' || in_array('..', $segments, true) || ! str_starts_with($path, 'exports/')) {
            throw new InvalidArgumentException('Export paths must remain in the private exports directory.');
        }

        return $path;
    }

    private function safeMediaPath(string $path): ?string
    {
        $path = ltrim(str_replace('\\', '/', $path), '/');
        $segments = explode('/', $path);
        if ($path === '' || in_array('..', $segments, true)) {
            return null;
        }

        return $path;
    }

    /** @param array<string, mixed> $manifest */
    private function writeManifest(string $identifier, array $manifest): void
    {
        Storage::disk('local')->put(
            $this->manifestPath($identifier),
            json_encode($manifest, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
        );
    }

    private function manifestPath(string $identifier): string
    {
        return 'exports/'.$identifier.'.json';
    }

    /** @param array<string, mixed> $manifest */
    private function deleteManifestAndArchive(string $manifestPath, array $manifest): void
    {
        $disk = Storage::disk('local');
        $disk->delete($manifestPath);

        $outputPath = $manifest['output_path'] ?? null;
        if (is_string($outputPath) && $this->safePath($outputPath) === $outputPath) {
            $disk->delete($outputPath);
        }
    }
}
