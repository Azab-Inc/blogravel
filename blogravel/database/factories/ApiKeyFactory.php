<?php

namespace Database\Factories;

use App\Enums\ApiKeyAbility;
use App\Models\ApiKey;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ApiKey>
 */
class ApiKeyFactory extends Factory
{
    protected $model = ApiKey::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->word(),
            'token' => fake()->unique()->uuid(),
            'abilities' => [fake()->randomElement(ApiKeyAbility::cases())],
            'last_used_at' => null,
            'expires_at' => null,
        ];
    }
}
