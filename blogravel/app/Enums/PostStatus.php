<?php

namespace App\Enums;

enum PostStatus: string
{
    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case Published = 'published';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Scheduled => 'Scheduled',
            self::Published => 'Published',
        };
    }

    public static function values(): array
    {
        return array_map(static fn (self $status): string => $status->value, self::cases());
    }
}
