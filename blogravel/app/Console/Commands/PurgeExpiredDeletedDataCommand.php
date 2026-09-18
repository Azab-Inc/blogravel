<?php

namespace App\Console\Commands;

use App\Services\DataExportService;
use App\Services\RetentionPurgeService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('gdpr:purge-expired')]
#[Description('Purge expired GDPR data and export archives')]
class PurgeExpiredDeletedDataCommand extends Command
{
    public function handle(DataExportService $exports, RetentionPurgeService $retention): int
    {
        $failures = [];
        $tenantCount = 0;
        $userCount = 0;

        try {
            $exports->purgeExpired();
            $this->info('Expired GDPR export archives purged.');
        } catch (Throwable $exception) {
            $failures[] = $exception;
            $this->error('Export cleanup requires retry: '.$exception->getMessage());
        }

        try {
            $tenantCount = $retention->purgeDueTenants(CarbonImmutable::now());
        } catch (Throwable $exception) {
            $failures[] = $exception;
            $this->error('Tenant purge requires retry: '.$exception->getMessage());
        }

        try {
            $userCount = $retention->purgeDueUsers(CarbonImmutable::now());
        } catch (Throwable $exception) {
            $failures[] = $exception;
            $this->error('User purge requires retry: '.$exception->getMessage());
        }

        $this->info("Tenants purged: {$tenantCount}");
        $this->info("Users purged: {$userCount}");

        return $failures === [] ? self::SUCCESS : self::FAILURE;
    }
}
