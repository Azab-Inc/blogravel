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

    /**
     * Get a specific limit for this plan.
     * Returns null if the limit is unlimited.
     */
    public function limit(string $key): ?int
    {
        $plans = config('billing.plans');

        return $plans[$this->value][$key] ?? null;
    }

    /**
     * Check if the plan has a specific limit.
     */
    public function hasLimit(string $key): bool
    {
        return $this->limit($key) !== null;
    }
}
