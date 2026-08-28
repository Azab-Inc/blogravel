<?php

namespace App\Enums;

enum ApiKeyAbility: string
{
    case Read = 'read';
    case Write = 'write';
    case DraftRead = 'draft_read';

    public function label(): string
    {
        return match ($this) {
            self::Read => 'Read',
            self::Write => 'Write',
            self::DraftRead => 'Draft Read',
        };
    }

    public static function values(): array
    {
        return array_map(static fn (self $ability): string => $ability->value, self::cases());
    }
}
