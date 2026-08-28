<?php

namespace Database\Factories;

use App\Enums\SubscriberStatus;
use App\Models\Subscriber;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subscriber>
 */
class SubscriberFactory extends Factory
{
    protected $model = Subscriber::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'email' => fake()->unique()->safeEmail(),
            'name' => fake()->name(),
            'status' => fake()->randomElement(SubscriberStatus::cases()),
        ];
    }
}
