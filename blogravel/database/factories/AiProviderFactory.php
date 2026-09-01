<?php

namespace Database\Factories;

use App\Enums\AiProviderType;
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
            'type' => AiProviderType::OpenAi,
            'name' => fake()->company(),
            'api_key' => fake()->password(32),
            'base_url' => 'https://api.openai.com/v1',
            'model' => 'gpt-4o',
            'temperature' => 0.70,
            'max_tokens' => 2048,
            'enabled' => true,
        ];
    }

    public function ollama(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => AiProviderType::Ollama,
            'base_url' => 'http://localhost:11434',
            'model' => 'llama3',
        ]);
    }

    public function custom(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => AiProviderType::Custom,
            'base_url' => fake()->url(),
            'model' => fake()->word(),
            'custom_template' => json_encode([
                'method' => 'POST',
                'url' => '{base_url}',
                'headers' => ['Content-Type' => 'application/json'],
                'body' => ['model' => '{model}', 'prompt' => '{prompt}'],
                'response_path' => 'result.text',
            ]),
        ]);
    }
}
