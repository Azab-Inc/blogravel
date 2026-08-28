<?php

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\Webhook;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Webhook>
 */
class WebhookFactory extends Factory
{
    protected $model = Webhook::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'url' => fake()->url(),
            'events' => ['post.published'],
            'secret' => fake()->uuid(),
            'active' => true,
        ];
    }
}
