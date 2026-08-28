<?php

namespace Database\Factories;

use App\Enums\Plan;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tenant>
 */
class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    public function definition(): array
    {
        return [
            'domain' => fake()->unique()->domainName(),
            'name' => fake()->company(),
            'plan' => fake()->randomElement(Plan::cases()),
        ];
    }
}
