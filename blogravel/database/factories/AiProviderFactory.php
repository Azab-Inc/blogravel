<?php

namespace Database\Factories;

use App\Models\AiProvider;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiProvider>
 */
class AiProviderFactory extends Factory
{
    protected $model = AiProvider::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->company(),
            'api_key' => fake()->password(32),
            'base_url' => fake()->url(),
            'enabled' => true,
        ];
    }
}
