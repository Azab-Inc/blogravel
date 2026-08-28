<?php

namespace Database\Factories;

use App\Models\Media;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Media>
 */
class MediaFactory extends Factory
{
    protected $model = Media::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->word(),
            'file_path' => 'uploads/'.fake()->uuid(),
            'url' => fake()->url(),
            'mime_type' => fake()->mimeType(),
            'size' => fake()->numberBetween(1024, 5242880),
        ];
    }
}
