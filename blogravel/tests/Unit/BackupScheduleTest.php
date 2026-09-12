<?php

use App\Support\BackupSchedule;
use Carbon\Carbon;

it('encodes a simple schedule without losing its interval settings', function () {
    $schedule = BackupSchedule::simple(
        interval: 2,
        unit: 'week',
        time: '02:30',
        weekday: 1,
    );

    expect(json_decode($schedule, true))->toBe([
        'mode' => 'simple',
        'interval' => 2,
        'unit' => 'week',
        'time' => '02:30',
        'weekday' => 1,
        'month_day' => 1,
    ]);
});

it('calculates the next simple daily backup from the configured time', function () {
    $from = Carbon::create(2026, 9, 12, 10, 0, 0);

    $nextRun = BackupSchedule::nextRunAt(
        BackupSchedule::simple(interval: 2, unit: 'day', time: '09:00'),
        $from,
    );

    expect($nextRun->toDateTimeString())->toBe('2026-09-14 09:00:00');
});

it('calculates the next cron backup using the stored cron expression', function () {
    $from = Carbon::create(2026, 9, 12, 10, 0, 0);

    $nextRun = BackupSchedule::nextRunAt('0 2 * * *', $from);

    expect($nextRun->toDateTimeString())->toBe('2026-09-13 02:00:00');
});

it('converts simple form data into a stored schedule', function () {
    $schedule = BackupSchedule::fromFormData([
        'schedule_mode' => 'simple',
        'schedule_interval' => 3,
        'schedule_unit' => 'month',
        'schedule_time' => '04:15',
        'schedule_month_day' => 15,
    ]);

    expect(json_decode($schedule, true))->toMatchArray([
        'mode' => 'simple',
        'interval' => 3,
        'unit' => 'month',
        'time' => '04:15',
        'month_day' => 15,
    ]);
});

it('loads existing cron schedules in cron mode', function () {
    expect(BackupSchedule::formDataFor('0 2 * * *'))->toMatchArray([
        'schedule_mode' => 'cron',
        'schedule' => '0 2 * * *',
    ]);
});
