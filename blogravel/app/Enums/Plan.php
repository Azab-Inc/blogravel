<?php

namespace App\Enums;

enum Plan: string
{
    case Free = 'free';
    case Pro = 'pro';
    case Business = 'business';

    public function label(): string
    {
        return match ($this) {
            self::Free => 'Free',
            self::Pro => 'Pro',
            self::Business => 'Business',
        };
    }

    public static function values(): array
    {
        return array_map(static fn (self $plan): string => $plan->value, self::cases());
    }
}
