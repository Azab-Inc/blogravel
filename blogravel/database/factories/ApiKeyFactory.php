<?php

namespace Database\Factories;

use App\Enums\ApiKeyAbility;
use App\Models\ApiKey;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ApiKey>
 */
class ApiKeyFactory extends Factory
{
    protected $model = ApiKey::class;

    public function definition(): array
    {
        $plaintext = Str::random(60);

        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->word(),
            'token' => $plaintext,
            'key_hash' => hash('sha256', $plaintext),
            'abilities' => [fake()->randomElement(ApiKeyAbility::cases())],
            'last_used_at' => null,
            'expires_at' => null,
        ];
    }

    public function withTenantUser(): static
    {
        return $this->afterCreating(function (ApiKey $key) {
            User::factory()->create(['tenant_id' => $key->tenant_id]);
        });
    }
}
