<?php

namespace App\Support;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Cron\CronExpression;
use InvalidArgumentException;

final class BackupSchedule
{
    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromFormData(array $data): string
    {
        if (($data['schedule_mode'] ?? 'cron') === 'simple') {
            return self::simple(
                interval: (int) ($data['schedule_interval'] ?? 1),
                unit: (string) ($data['schedule_unit'] ?? 'day'),
                time: (string) ($data['schedule_time'] ?? '02:00'),
                weekday: (int) ($data['schedule_weekday'] ?? 1),
                monthDay: (int) ($data['schedule_month_day'] ?? 1),
            );
        }

        return trim((string) ($data['schedule'] ?? ''));
    }

    /**
     * @return array<string, mixed>
     */
    public static function formDataFor(string $schedule): array
    {
        $settings = json_decode($schedule, true);

        if (is_array($settings) && ($settings['mode'] ?? null) === 'simple') {
            return [
                'schedule_mode' => 'simple',
                'schedule_interval' => $settings['interval'] ?? 1,
                'schedule_unit' => $settings['unit'] ?? 'day',
                'schedule_time' => $settings['time'] ?? '02:00',
                'schedule_weekday' => $settings['weekday'] ?? 1,
                'schedule_month_day' => $settings['month_day'] ?? 1,
            ];
        }

        return [
            'schedule_mode' => 'cron',
            'schedule' => $schedule,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function prepareFormData(array $data): array
    {
        $data['schedule'] = self::fromFormData($data);
        $data['next_run_at'] = self::nextRunAt($data['schedule'], now());

        foreach ([
            'schedule_mode',
            'schedule_interval',
            'schedule_unit',
            'schedule_time',
            'schedule_weekday',
            'schedule_month_day',
        ] as $field) {
            unset($data[$field]);
        }

        return $data;
    }

    public static function simple(
        int $interval,
        string $unit,
        string $time,
        int $weekday = 1,
        int $monthDay = 1,
    ): string {
        if ($interval < 1) {
            throw new InvalidArgumentException('Backup schedule interval must be at least 1.');
        }

        if (! in_array($unit, ['minute', 'hour', 'day', 'week', 'month'], true)) {
            throw new InvalidArgumentException('Backup schedule unit is invalid.');
        }

        if (! preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $time)) {
            throw new InvalidArgumentException('Backup schedule time must use HH:MM format.');
        }

        if ($weekday < 1 || $weekday > 7) {
            throw new InvalidArgumentException('Backup schedule weekday is invalid.');
        }

        if ($monthDay < 1 || $monthDay > 28) {
            throw new InvalidArgumentException('Backup schedule month day must be between 1 and 28.');
        }

        return json_encode([
            'mode' => 'simple',
            'interval' => $interval,
            'unit' => $unit,
            'time' => $time,
            'weekday' => $weekday,
            'month_day' => $monthDay,
        ], JSON_THROW_ON_ERROR);
    }

    public static function nextRunAt(string $schedule, CarbonInterface $from): Carbon
    {
        $settings = json_decode($schedule, true);

        if (is_array($settings) && ($settings['mode'] ?? null) === 'simple') {
            return self::nextSimpleRunAt($settings, $from);
        }

        return Carbon::instance(CronExpression::factory($schedule)->getNextRunDate($from->toDateTimeImmutable()));
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private static function nextSimpleRunAt(array $settings, CarbonInterface $from): Carbon
    {
        $interval = (int) ($settings['interval'] ?? 0);
        $unit = (string) ($settings['unit'] ?? '');
        $time = (string) ($settings['time'] ?? '');
        $weekday = (int) ($settings['weekday'] ?? 1);
        $monthDay = (int) ($settings['month_day'] ?? 1);

        self::simple($interval, $unit, $time, $weekday, $monthDay);
        $nextRun = Carbon::instance($from->toDateTimeImmutable());

        if ($unit === 'minute') {
            return $nextRun->startOfMinute()->addMinutes($interval);
        }

        if ($unit === 'hour') {
            return $nextRun->startOfHour()->addHours($interval);
        }

        [$hour, $minute] = array_map('intval', explode(':', $time));

        if ($unit === 'day') {
            $nextRun->setTime($hour, $minute);

            return $nextRun->lessThanOrEqualTo($from)
                ? $nextRun->addDays($interval)
                : $nextRun;
        }

        if ($unit === 'week') {
            $daysUntilWeekday = ($weekday - (int) $nextRun->isoWeekday() + 7) % 7;
            $nextRun->startOfDay()->addDays($daysUntilWeekday)->setTime($hour, $minute);

            return $nextRun->lessThanOrEqualTo($from)
                ? $nextRun->addWeeks($interval)
                : $nextRun;
        }

        $nextRun->day($monthDay)->setTime($hour, $minute);

        return $nextRun->lessThanOrEqualTo($from)
            ? $nextRun->addMonths($interval)
            : $nextRun;
    }
}
