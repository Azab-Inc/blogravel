<?php

namespace App\Console\Commands;

use App\Services\DataExportService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('gdpr:purge-expired')]
#[Description('Purge expired GDPR export archives')]
class PurgeExpiredDeletedDataCommand extends Command
{
    public function handle(DataExportService $exports): int
    {
        $exports->purgeExpired();
        $this->info('Expired GDPR export archives purged.');

        return self::SUCCESS;
    }
}
