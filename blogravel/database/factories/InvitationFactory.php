<?php

namespace Database\Factories;

use App\Enums\Role;
use App\Models\Invitation;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

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
            'role' => fake()->randomElement([Role::Admin, Role::Editor, Role::Author]),
            'token' => Str::random(64),
            'type' => 'email',
            'accepted_at' => null,
            'expires_at' => fake()->dateTimeBetween('+1 week', '+1 month'),
            'invited_by' => null,
        ];
    }

    public function accepted(): static
    {
        return $this->state(fn () => ['accepted_at' => now()]);
    }

    public function expired(): static
    {
        return $this->state(fn () => ['expires_at' => now()->subDay()]);
    }

    public function shareable(): static
    {
        return $this->state(fn () => ['type' => 'shareable']);
    }
}
