<?php

namespace Database\Factories;

use App\Enums\Role;
use App\Models\Invitation;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invitation>
 */
class InvitationFactory extends Factory
{
    protected $model = Invitation::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'email' => fake()->unique()->safeEmail(),
            'role' => fake()->randomElement(Role::cases()),
            'token' => fake()->unique()->uuid(),
            'accepted_at' => null,
            'expires_at' => null,
        ];
    }
}
