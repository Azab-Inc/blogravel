<?php

namespace Database\Factories;

use App\Models\Subscription;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subscription>
 */
class SubscriptionFactory extends Factory
{
    protected $model = Subscription::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'stripe_id' => fake()->unique()->uuid(),
            'stripe_status' => 'active',
            'stripe_plan' => null,
            'trial_ends_at' => null,
            'ends_at' => null,
        ];
    }
}
