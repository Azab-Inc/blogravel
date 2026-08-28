<?php

namespace App\Enums;

enum Role: string
{
    case SuperAdmin = 'super_admin';
    case Editor = 'editor';
    case Author = 'author';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Admin',
            self::Editor => 'Editor',
            self::Author => 'Author',
        };
    }

    public static function values(): array
    {
        return array_map(static fn (self $role): string => $role->value, self::cases());
    }
}
