<?php

namespace App\Jobs;

use App\Models\Backup;
use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Storage;

class PruneBackupsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public function __construct(
        public string $tenantId,
    ) {}

    public function handle(): void
    {
        // Self-hosted (billing disabled) = unlimited retention
        if (! config('billing.enabled')) {
            return;
        }

        $tenant = Tenant::findOrFail($this->tenantId);
        $retentionDays = $tenant->plan->limit('backup_retention_days');

        if (! $retentionDays) {
            return;
        }

        $cutoff = now()->subDays($retentionDays);

        $expiredBackups = Backup::where('tenant_id', $this->tenantId)
            ->where('created_at', '<', $cutoff)
            ->get();

        foreach ($expiredBackups as $backup) {
            if ($backup->path && Storage::disk($backup->disk)->exists($backup->path)) {
                Storage::disk($backup->disk)->delete($backup->path);
            }
            $backup->delete();
        }
    }
}
