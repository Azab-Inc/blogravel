<?php

namespace Database\Factories;

use App\Enums\PostStatus;
use App\Models\Page;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Page>
 */
class PageFactory extends Factory
{
    protected $model = Page::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'title' => fake()->sentence(),
            'slug' => fake()->slug(),
            'content' => fake()->paragraphs(2, true),
            'status' => fake()->randomElement(PostStatus::cases()),
        ];
    }
}
