<?php

namespace App\Enums;

enum BackupContent: string
{
    case Database = 'database';
    case Files = 'files';
    case Both = 'both';

    public function label(): string
    {
        return match ($this) {
            self::Database => 'Database Only',
            self::Files => 'Files Only',
            self::Both => 'Database & Files',
        };
    }

    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
