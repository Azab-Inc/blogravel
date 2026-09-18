<?php

namespace App\Jobs;

use App\Models\Tenant;
use App\Models\User;
use App\Notifications\TenantExportReadyNotification;
use App\Services\DataExportService;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateTenantExportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 600;

    public function __construct(
        public string $tenantId,
        public string $requestedById,
        public string $format,
        public string $outputPath,
        public CarbonImmutable $expiresAt,
    ) {}

    public function handle(DataExportService $exports): void
    {
        if ($this->expiresAt->isPast()) {
            $exports->purgeExpired();

            return;
        }

        $tenant = Tenant::withoutGlobalScopes()
            ->withTrashed()
            ->findOrFail($this->tenantId);
        $exports->generate($tenant, $this->format, $this->outputPath);

        $requester = User::withoutGlobalScopes()
            ->withTrashed()
            ->findOrFail($this->requestedById);

        $requester->notify(new TenantExportReadyNotification(
            pathinfo($this->outputPath, PATHINFO_FILENAME),
            $this->format,
            $this->expiresAt,
        ));
    }
}
