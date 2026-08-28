<?php

namespace Database\Factories;

use App\Models\Setting;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Setting>
 */
class SettingFactory extends Factory
{
    protected $model = Setting::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'key' => fake()->word(),
            'value' => fake()->sentence(),
        ];
    }
}
