<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

class ProcessWordPressImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    /**
     * @param  array  $items  Parsed items from WxrParser
     */
    public function __construct(
        public array $items,
        public string $tenantId,
        public string $userId,
    ) {}

    public function handle(): void
    {
        $job = new WordPressImportJob($this->items, $this->tenantId, $this->userId);
        $job->handle();
    }
}
