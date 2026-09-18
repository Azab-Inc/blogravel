<?php

namespace App\Services;

use App\Enums\DeletionReason;
use App\Models\Backup;
use App\Models\Media;
use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class RetentionPurgeService
{
    private const RETENTION_DAYS = 30;

    public function purgeDueTenants(CarbonImmutable $now): int
    {
        $purged = 0;

        Tenant::withTrashed()
            ->whereNotNull('deleted_at')
            ->where('deleted_at', '<=', $now->subDays(self::RETENTION_DAYS))
            ->orderBy('id')
            ->get()
            ->each(function (Tenant $tenant) use (&$purged): void {
                $this->purgeTenant($tenant);
                $purged++;
            });

        return $purged;
    }

    public function purgeDueUsers(CarbonImmutable $now): int
    {
        $purged = 0;

        User::withoutGlobalScopes()
            ->withTrashed()
            ->whereNotNull('deleted_at')
            ->where('deleted_at', '<=', $now->subDays(self::RETENTION_DAYS))
            ->orderBy('id')
            ->get()
            ->each(function (User $user) use (&$purged): void {
                $this->purgeUser($user);
                $purged++;
            });

        return $purged;
    }

    public function purgeTenant(Tenant $tenant): void
    {
        $tenant = Tenant::withTrashed()->find($tenant->getKey());
        if ($tenant === null || $tenant->deleted_at === null || $tenant->deleted_at->isAfter(now()->subDays(self::RETENTION_DAYS))) {
            return;
        }

        $users = User::withoutGlobalScopes()
            ->withTrashed()
            ->where('tenant_id', $tenant->getKey())
            ->get();
        $media = Media::withoutGlobalScopes()
            ->where('tenant_id', $tenant->getKey())
            ->get(['file_path']);
        $backups = Backup::withoutGlobalScopes()
            ->where('tenant_id', $tenant->getKey())
            ->get(['path', 'disk']);

        $userIds = $users->modelKeys();
        $this->deleteMediaFiles($media);
        $this->deleteBackupFiles($backups);
        $this->deleteTenantExports((string) $tenant->getKey());
        $this->deleteTenantReferences((string) $tenant->getKey(), $userIds);

        DB::transaction(function () use ($tenant, $users): void {
            foreach ($users as $user) {
                $attributes = ['tenant_id' => null];

                if (! $user->trashed()) {
                    $attributes['deleted_at'] = now();
                    $attributes['deleted_by'] = null;
                    $attributes['deletion_reason'] = DeletionReason::TenantClosed->value;
                }

                User::withoutGlobalScopes()
                    ->withTrashed()
                    ->whereKey($user->getKey())
                    ->update($attributes);
            }

            $tenant->forceDelete();
        });
    }

    public function purgeUser(User $user): void
    {
        $user = User::withoutGlobalScopes()->withTrashed()->find($user->getKey());
        if ($user === null
            || $user->deleted_at === null
            || $user->deleted_at->isAfter(now()->subDays(self::RETENTION_DAYS))) {
            return;
        }

        $this->deleteUserReferences($user);

        DB::transaction(function () use ($user): void {
            $user->forceDelete();
        });
    }

    /**
     * @param  Collection<int, Media>  $media
     */
    private function deleteMediaFiles(Collection $media): void
    {
        $disk = Storage::disk('public');

        foreach ($media as $record) {
            $this->deleteFile($disk, (string) $record->file_path);
        }
    }

    /**
     * @param  Collection<int, Backup>  $backups
     */
    private function deleteBackupFiles(Collection $backups): void
    {
        foreach ($backups as $backup) {
            $this->deleteFile(Storage::disk((string) $backup->disk), (string) $backup->path);
        }
    }

    private function deleteFile(mixed $disk, string $path): void
    {
        if ($path === '' || ! $disk->exists($path)) {
            return;
        }

        if (! $disk->delete($path)) {
            throw new RuntimeException("Unable to delete retained file [{$path}].");
        }
    }

    private function deleteTenantExports(string $tenantId): void
    {
        $disk = Storage::disk('local');

        foreach ($disk->files('exports') as $manifestPath) {
            if (! str_ends_with($manifestPath, '.json')) {
                continue;
            }

            $manifest = json_decode($disk->get($manifestPath), true);
            if (! is_array($manifest) || (string) ($manifest['tenant_id'] ?? '') !== $tenantId) {
                continue;
            }

            $outputPath = $manifest['output_path'] ?? null;
            if (is_string($outputPath)) {
                $this->deleteFile($disk, $outputPath);
            }

            $this->deleteFile($disk, $manifestPath);
        }
    }

    /**
     * @param  list<string>  $userIds
     */
    private function deleteTenantReferences(string $tenantId, array $userIds): void
    {
        $this->deleteQueuedReferences([$tenantId, ...$userIds]);

        if (Schema::hasTable('personal_access_tokens') && $userIds !== []) {
            DB::table('personal_access_tokens')
                ->where('tokenable_type', User::class)
                ->whereIn('tokenable_id', $userIds)
                ->delete();
        }

        if (Schema::hasTable('sessions') && $userIds !== []) {
            DB::table('sessions')->whereIn('user_id', $userIds)->delete();
        }

        if (Schema::hasTable('notifications')) {
            DB::table('notifications')
                ->where(function ($query) use ($tenantId, $userIds): void {
                    $query->where(function ($query) use ($userIds): void {
                        $query->where('notifiable_type', User::class)
                            ->whereIn('notifiable_id', $userIds);
                    })->orWhere(function ($query) use ($tenantId): void {
                        $query->where('notifiable_type', Tenant::class)
                            ->where('notifiable_id', $tenantId);
                    });
                })
                ->delete();
        }
    }

    private function deleteUserReferences(User $user): void
    {
        $userId = (string) $user->getKey();
        $this->deleteQueuedReferences([$userId]);

        if (Schema::hasTable('personal_access_tokens')) {
            DB::table('personal_access_tokens')
                ->where('tokenable_type', User::class)
                ->where('tokenable_id', $userId)
                ->delete();
        }

        if (Schema::hasTable('sessions')) {
            DB::table('sessions')->where('user_id', $userId)->delete();
        }

        if (Schema::hasTable('notifications')) {
            DB::table('notifications')
                ->where('notifiable_type', User::class)
                ->where('notifiable_id', $userId)
                ->delete();
        }

        if (Schema::hasTable('password_reset_tokens')) {
            DB::table('password_reset_tokens')->where('email', $user->email)->delete();
        }

        if (Schema::hasTable('passkeys')) {
            DB::table('passkeys')->where('user_id', $userId)->delete();
        }
    }

    /**
     * @param  list<string>  $identifiers
     */
    private function deleteQueuedReferences(array $identifiers): void
    {
        $identifiers = array_values(array_unique(array_filter($identifiers)));
        if ($identifiers === []) {
            return;
        }

        foreach (['jobs' => 'payload', 'failed_jobs' => 'payload', 'job_batches' => 'options'] as $table => $column) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            DB::table($table)
                ->where(function ($query) use ($column, $identifiers): void {
                    foreach ($identifiers as $identifier) {
                        $query->orWhere($column, 'like', '%'.$identifier.'%');
                    }
                })
                ->delete();
        }
    }
}
