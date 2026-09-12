<?php

use App\Jobs\CreateBackupJob;
use App\Models\BackupRule;
use App\Support\BackupSchedule;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(function () {
    $now = now();
    $rules = BackupRule::where('enabled', true)
        ->where(function ($query) use ($now) {
            $query->whereNull('next_run_at')
                ->orWhere('next_run_at', '<=', $now);
        })
        ->get();

    foreach ($rules as $rule) {
        CreateBackupJob::dispatch($rule->id);
        $rule->update(['next_run_at' => BackupSchedule::nextRunAt($rule->schedule, $now)]);
    }
})->name('run-scheduled-backups')->everyFifteenMinutes()->withoutOverlapping();
