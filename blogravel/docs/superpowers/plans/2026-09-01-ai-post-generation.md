# AI Post Generation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add AI-powered content generation to Blogravel with configurable providers, content length control, and a modal-based generation flow on the Post form.

**Architecture:** SOLID provider pattern — `AiProviderInterface` contract with `OpenAiProvider`, `OllamaProvider`, `CustomProvider` implementations orchestrated by `AiService`. Custom Filament page for provider management. Livewire action on Post form for generation.

**Tech Stack:** Laravel 13, PHP 8.5, Filament v5.7.6, Laravel HTTP Client, Pest, PostgreSQL

**Spec:** `docs/superpowers/specs/2026-09-01-ai-post-generation-design.md`

## Global Constraints

- License: CC BY-NC 4.0
- All models use UUID primary keys via `BaseModel` with `HasUuids`
- Filament v5 type requirements: `$navigationIcon` must be `string|BackedEnum|null`, `$navigationGroup` must be `UnitEnum|string|null`
- Form components: `Filament\Schemas\Components\Section`, `Filament\Forms\Components\FileUpload`, `Filament\Forms\Components\Placeholder`
- `#[Fillable]` and `#[Hidden]` attribute imports on all models
- Run `vendor/bin/pint --dirty --format agent` after code changes
- Run `docker compose exec laravel.test php artisan octane:reload` after registering new Filament routes
- Tests with Pest: `php artisan make:test --pest {name}`
- No comments in code unless asked
- User model has `'password' => 'hashed'` cast — never call `bcrypt()` manually

---

## File Structure

| File | Responsibility |
|------|---------------|
| `database/migrations/xxxx_add_type_to_ai_providers_table.php` | Add `type`, `model`, `temperature`, `max_tokens`, `custom_template` to `ai_providers` |
| `app/Enums/AiProviderType.php` | Enum for provider types with label method |
| `app/Models/AiProvider.php` | Modify — add new fields, casts |
| `app/Contracts/AiProviderInterface.php` | Contract for AI provider implementations |
| `app/Exceptions/AiGenerationException.php` | Custom exception for generation failures |
| `app/Services/Ai/OpenAiProvider.php` | OpenAI-compatible API adapter |
| `app/Services/Ai/OllamaProvider.php` | Ollama local API adapter |
| `app/Services/Ai/CustomProvider.php` | Custom template-based adapter |
| `app/Services/AiService.php` | Orchestrator — routes to correct provider |
| `app/Jobs/GenerateAiPostJob.php` | Queued job for async AI generation + notifications |
| `app/Filament/Pages/AiSettings.php` | Custom settings page with 3 tabs |
| `resources/views/filament/pages/ai-settings.blade.php` | Blade view for AiSettings page |
| `app/Filament/Actions/GenerateAiPostAction.php` | Livewire action that dispatches async generation |
| `app/Filament/Resources/PostResource.php` | Modify — add GenerateAiPostAction |
| `tests/Unit/Services/Ai/OpenAiProviderTest.php` | Unit tests for OpenAI adapter |
| `tests/Unit/Services/Ai/OllamaProviderTest.php` | Unit tests for Ollama adapter |
| `tests/Unit/Services/Ai/CustomProviderTest.php` | Unit tests for Custom adapter |
| `tests/Unit/Services/AiServiceTest.php` | Unit tests for orchestrator |
| `tests/Feature/Filament/Pages/AiSettingsTest.php` | Feature tests for settings page |
| `tests/Feature/Filament/Actions/GenerateAiPostActionTest.php` | Feature tests for generation action |
| `tests/Feature/Jobs/GenerateAiPostJobTest.php` | Feature tests for queued job |

---

### Task 1: Migration + Enum + Model Update

**Files:**
- Create: `database/migrations/2026_09_01_000000_add_type_to_ai_providers_table.php`
- Create: `app/Enums/AiProviderType.php`
- Modify: `app/Models/AiProvider.php`

**Interfaces:**
- Produces: `AiProviderType` enum, updated `AiProvider` model with new fields/casts

- [ ] **Step 1: Create the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_providers', function (Blueprint $table) {
            $table->string('type')->default('openai')->after('tenant_id');
            $table->string('model')->after('type');
            $table->decimal('temperature', 3, 2)->default(0.70)->after('model');
            $table->integer('max_tokens')->default(2048)->after('temperature');
            $table->text('custom_template')->nullable()->after('max_tokens');
        });
    }

    public function down(): void
    {
        Schema::table('ai_providers', function (Blueprint $table) {
            $table->dropColumn(['type', 'model', 'temperature', 'max_tokens', 'custom_template']);
        });
    }
};
```

- [ ] **Step 2: Run the migration**

Run: `docker compose exec laravel.test php artisan migrate`
Expected: Migration runs successfully

- [ ] **Step 3: Create AiProviderType enum**

```php
<?php

namespace App\Enums;

enum AiProviderType: string
{
    case OpenAi = 'openai';
    case Ollama = 'ollama';
    case Custom = 'custom';

    public function label(): string
    {
        return match ($this) {
            self::OpenAi => 'OpenAI',
            self::Ollama => 'Ollama',
            self::Custom => 'Custom',
        };
    }
}
```

- [ ] **Step 4: Update AiProvider model**

```php
<?php

namespace App\Models;

use App\Enums\AiProviderType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['tenant_id', 'type', 'name', 'api_key', 'base_url', 'model', 'temperature', 'max_tokens', 'custom_template', 'enabled'])]
class AiProvider extends BaseModel
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'type' => AiProviderType::class,
            'api_key' => 'encrypted',
            'temperature' => 'decimal:2',
            'max_tokens' => 'integer',
            'enabled' => 'boolean',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
```

- [ ] **Step 5: Update AiProviderFactory**

```php
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
```

- [ ] **Step 6: Run Pint and commit**

Run: `docker compose exec laravel.test ./vendor/bin/pint --dirty --format agent`
Run: `git add database/migrations/ app/Enums/AiProviderType.php app/Models/AiProvider.php database/factories/AiProviderFactory.php && git commit -m "Feature: AI provider migration, enum, and model update"`

---

### Task 2: Exception + Contract

**Files:**
- Create: `app/Exceptions/AiGenerationException.php`
- Create: `app/Contracts/AiProviderInterface.php`

**Interfaces:**
- Produces: `AiGenerationException`, `AiProviderInterface`

- [ ] **Step 1: Create AiGenerationException**

```php
<?php

namespace App\Exceptions;

use RuntimeException;

class AiGenerationException extends RuntimeException
{
    public static function providerDisabled(string $providerName): static
    {
        return new static("AI provider [{$providerName}] is disabled.");
    }

    public static function httpError(string $providerName, int $statusCode): static
    {
        return new static("AI provider [{$providerName}] returned HTTP {$statusCode}.");
    }

    public static function invalidResponse(string $providerName): static
    {
        return new static("AI provider [{$providerName}] returned an invalid response format.");
    }
}
```

- [ ] **Step 2: Create AiProviderInterface**

```php
<?php

namespace App\Contracts;

use App\Exceptions\AiGenerationException;
use App\Models\AiProvider;

interface AiProviderInterface
{
    /**
     * Generate content using the AI provider.
     *
     * @param AiProvider $provider
     * @param string     $prompt
     * @param string[]   $outputTypes  ['title', 'content', 'excerpt', 'categories', 'tags']
     * @param array      $options      ['length_type' => 'paragraphs'|'characters', 'length_value' => int]
     *
     * @return array{title?: string, content?: string, excerpt?: string, categories?: string[], tags?: string[]}
     *
     * @throws AiGenerationException
     */
    public function generate(AiProvider $provider, string $prompt, array $outputTypes, array $options = []): array;
}
```

- [ ] **Step 3: Run Pint and commit**

Run: `docker compose exec laravel.test ./vendor/bin/pint --dirty --format agent`
Run: `git add app/Exceptions/AiGenerationException.php app/Contracts/AiProviderInterface.php && git commit -m "Feature: AI generation exception and provider contract"`

---

### Task 3: System Prompt Builder

**Files:**
- Create: `app/Services/Ai/PromptBuilder.php`

**Interfaces:**
- Produces: `PromptBuilder::build()` method returning the system prompt string
- Consumed by: all three provider implementations in Tasks 4–6

- [ ] **Step 1: Create PromptBuilder**

```php
<?php

namespace App\Services\Ai;

class PromptBuilder
{
    public static function build(string $prompt, array $outputTypes, array $options = []): string
    {
        $lengthType = $options['length_type'] ?? 'paragraphs';
        $lengthValue = $options['length_value'] ?? 4;

        $fields = implode(', ', $outputTypes);

        $lengthInstruction = match ($lengthType) {
            'characters' => "Length: approximately {$lengthValue} characters for content.",
            default => "Length: {$lengthValue} paragraphs for content.",
        };

        return <<<PROMPT
You are a blog content generator. Return ONLY valid JSON with these fields: {$fields}

{$lengthInstruction}

Topic: {$prompt}

Rules:
- Content should be well-structured with HTML formatting
- Excerpt should be 1-2 sentences (regardless of length setting)
- Categories and tags must be strings that match existing taxonomy or new suggestions
- Return ONLY the JSON object, no markdown fences, no explanation
PROMPT;
    }
}
```

- [ ] **Step 2: Write unit test for PromptBuilder**

```php
<?php

use App\Services\Ai\PromptBuilder;

it('builds prompt with paragraphs length', function () {
    $result = PromptBuilder::build(' Laravel best practices', ['title', 'content'], [
        'length_type' => 'paragraphs',
        'length_value' => 4,
    ]);

    expect($result)->toContain('title, content')
        ->toContain('4 paragraphs')
        ->toContain(' Laravel best practices');
});

it('builds prompt with characters length', function () {
    $result = PromptBuilder::build('PHP tips', ['content', 'excerpt'], [
        'length_type' => 'characters',
        'length_value' => 2000,
    ]);

    expect($result)->toContain('2000 characters');
});

it('defaults to 4 paragraphs when no options given', function () {
    $result = PromptBuilder::build('test topic', ['title']);

    expect($result)->toContain('4 paragraphs');
});
```

- [ ] **Step 3: Run the test**

Run: `docker compose exec laravel.test php artisan test --compact --filter=PromptBuilder`
Expected: 3 tests pass

- [ ] **Step 4: Commit**

Run: `git add app/Services/Ai/PromptBuilder.php tests/Unit/Services/Ai/PromptBuilderTest.php && git commit -m "Feature: AI prompt builder with length control"`

---

### Task 4: OpenAiProvider

**Files:**
- Create: `app/Services/Ai/OpenAiProvider.php`
- Create: `tests/Unit/Services/Ai/OpenAiProviderTest.php`

**Interfaces:**
- Implements: `AiProviderInterface::generate()`
- Consumes: `PromptBuilder::build()`, `AiGenerationException::httpError()`, `AiGenerationException::invalidResponse()`

- [ ] **Step 1: Write failing test**

```php
<?php

use App\Contracts\AiProviderInterface;
use App\Exceptions\AiGenerationException;
use App\Models\AiProvider;
use App\Services\Ai\OpenAiProvider;
use Illuminate\Support\Facades\Http;

it('implements AiProviderInterface', function () {
    $provider = new OpenAiProvider();
    expect($provider)->toBeInstanceOf(AiProviderInterface::class);
});

it('generates structured json from openai response', function () {
    Http::fake([
        'api.openai.com/v1/chat/completions' => Http::response([
            'choices' => [
                ['message' => ['content' => json_encode([
                    'title' => 'Test Title',
                    'content' => '<p>Test content</p>',
                    'excerpt' => 'A short excerpt.',
                ])]],
            ],
        ], 200),
    ]);

    $aiProvider = AiProvider::factory()->create([
        'base_url' => 'https://api.openai.com/v1',
        'model' => 'gpt-4o',
    ]);

    $service = new OpenAiProvider();
    $result = $service->generate($aiProvider, 'Write about Laravel', ['title', 'content', 'excerpt']);

    expect($result)->toBeArray()
        ->toHaveKey('title')
        ->toHaveKey('content')
        ->toHaveKey('excerpt')
        ->and($result['title'])->toBe('Test Title');
});

it('throws http error on failure', function () {
    Http::fake([
        'api.openai.com/v1/chat/completions' => Http::response([], 429),
    ]);

    $aiProvider = AiProvider::factory()->create([
        'base_url' => 'https://api.openai.com/v1',
    ]);

    $service = new OpenAiProvider();
    $service->generate($aiProvider, 'test', ['title']);
})->throws(AiGenerationException::class, 'HTTP 429');

it('throws invalid response on bad json', function () {
    Http::fake([
        'api.openai.com/v1/chat/completions' => Http::response([
            'choices' => [
                ['message' => ['content' => 'not json at all']],
            ],
        ], 200),
    ]);

    $aiProvider = AiProvider::factory()->create([
        'base_url' => 'https://api.openai.com/v1',
    ]);

    $service = new OpenAiProvider();
    $service->generate($aiProvider, 'test', ['title']);
})->throws(AiGenerationException::class, 'invalid response');
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec laravel.test php artisan test --compact --filter=OpenAiProvider`
Expected: FAIL — class not found

- [ ] **Step 3: Implement OpenAiProvider**

```php
<?php

namespace App\Services\Ai;

use App\Contracts\AiProviderInterface;
use App\Exceptions\AiGenerationException;
use App\Models\AiProvider;
use Illuminate\Support\Facades\Http;

class OpenAiProvider implements AiProviderInterface
{
    public function generate(AiProvider $provider, string $prompt, array $outputTypes, array $options = []): array
    {
        if (! $provider->enabled) {
            throw AiGenerationException::providerDisabled($provider->name);
        }

        $systemPrompt = PromptBuilder::build($prompt, $outputTypes, $options);
        $baseUrl = rtrim($provider->base_url ?? 'https://api.openai.com/v1', '/');

        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$provider->api_key,
            'Content-Type' => 'application/json',
        ])->timeout(60)->post($baseUrl.'/chat/completions', [
            'model' => $provider->model,
            'messages' => [
                ['role' => 'user', 'content' => $systemPrompt],
            ],
            'temperature' => (float) $provider->temperature,
            'max_tokens' => $provider->max_tokens,
        ]);

        if ($response->failed()) {
            throw AiGenerationException::httpError($provider->name, $response->status());
        }

        $content = $response->json('choices.0.message.content');

        $decoded = json_decode($content, true);

        if (! is_array($decoded)) {
            throw AiGenerationException::invalidResponse($provider->name);
        }

        return $decoded;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose exec laravel.test php artisan test --compact --filter=OpenAiProvider`
Expected: 4 tests pass

- [ ] **Step 5: Commit**

Run: `git add app/Services/Ai/OpenAiProvider.php tests/Unit/Services/Ai/OpenAiProviderTest.php && git commit -m "Feature: OpenAI-compatible AI provider adapter"`

---

### Task 5: OllamaProvider

**Files:**
- Create: `app/Services/Ai/OllamaProvider.php`
- Create: `tests/Unit/Services/Ai/OllamaProviderTest.php`

**Interfaces:**
- Implements: `AiProviderInterface::generate()`
- Consumes: `PromptBuilder::build()`, `AiGenerationException`

- [ ] **Step 1: Write failing test**

```php
<?php

use App\Contracts\AiProviderInterface;
use App\Exceptions\AiGenerationException;
use App\Models\AiProvider;
use App\Services\Ai\OllamaProvider;
use Illuminate\Support\Facades\Http;

it('implements AiProviderInterface', function () {
    $provider = new OllamaProvider();
    expect($provider)->toBeInstanceOf(AiProviderInterface::class);
});

it('generates structured json from ollama response', function () {
    Http::fake([
        'localhost:11434/api/generate' => Http::response([
            'response' => json_encode([
                'title' => 'Ollama Title',
                'content' => '<p>Ollama content</p>',
            ]),
        ], 200),
    ]);

    $aiProvider = AiProvider::factory()->ollama()->create([
        'base_url' => 'http://localhost:11434',
    ]);

    $service = new OllamaProvider();
    $result = $service->generate($aiProvider, 'Write about PHP', ['title', 'content']);

    expect($result)->toBeArray()
        ->toHaveKey('title')
        ->toHaveKey('content')
        ->and($result['title'])->toBe('Ollama Title');
});

it('throws http error on failure', function () {
    Http::fake([
        'localhost:11434/api/generate' => Http::response([], 500),
    ]);

    $aiProvider = AiProvider::factory()->ollama()->create([
        'base_url' => 'http://localhost:11434',
    ]);

    $service = new OllamaProvider();
    $service->generate($aiProvider, 'test', ['title']);
})->throws(AiGenerationException::class, 'HTTP 500');
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec laravel.test php artisan test --compact --filter=OllamaProvider`
Expected: FAIL — class not found

- [ ] **Step 3: Implement OllamaProvider**

```php
<?php

namespace App\Services\Ai;

use App\Contracts\AiProviderInterface;
use App\Exceptions\AiGenerationException;
use App\Models\AiProvider;
use Illuminate\Support\Facades\Http;

class OllamaProvider implements AiProviderInterface
{
    public function generate(AiProvider $provider, string $prompt, array $outputTypes, array $options = []): array
    {
        if (! $provider->enabled) {
            throw AiGenerationException::providerDisabled($provider->name);
        }

        $systemPrompt = PromptBuilder::build($prompt, $outputTypes, $options);
        $baseUrl = rtrim($provider->base_url ?? 'http://localhost:11434', '/');

        $response = Http::timeout(120)->post($baseUrl.'/api/generate', [
            'model' => $provider->model,
            'prompt' => $systemPrompt,
            'stream' => false,
        ]);

        if ($response->failed()) {
            throw AiGenerationException::httpError($provider->name, $response->status());
        }

        $content = $response->json('response');

        $decoded = json_decode($content, true);

        if (! is_array($decoded)) {
            throw AiGenerationException::invalidResponse($provider->name);
        }

        return $decoded;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose exec laravel.test php artisan test --compact --filter=OllamaProvider`
Expected: 3 tests pass

- [ ] **Step 5: Commit**

Run: `git add app/Services/Ai/OllamaProvider.php tests/Unit/Services/Ai/OllamaProviderTest.php && git commit -m "Feature: Ollama AI provider adapter"`

---

### Task 6: CustomProvider

**Files:**
- Create: `app/Services/Ai/CustomProvider.php`
- Create: `tests/Unit/Services/Ai/CustomProviderTest.php`

**Interfaces:**
- Implements: `AiProviderInterface::generate()`
- Consumes: `AiGenerationException`

- [ ] **Step 1: Write failing test**

```php
<?php

use App\Contracts\AiProviderInterface;
use App\Exceptions\AiGenerationException;
use App\Models\AiProvider;
use App\Services\Ai\CustomProvider;
use Illuminate\Support\Facades\Http;

it('implements AiProviderInterface', function () {
    $provider = new CustomProvider();
    expect($provider)->toBeInstanceOf(AiProviderInterface::class);
});

it('uses template to build request and parses response', function () {
    Http::fake([
        'api.example.com/*' => Http::response([
            'result' => ['text' => json_encode([
                'title' => 'Custom Title',
                'content' => '<p>Custom content</p>',
            ])],
        ], 200),
    ]);

    $template = json_encode([
        'method' => 'POST',
        'url' => 'https://api.example.com/v1/completions',
        'headers' => [
            'Authorization' => 'Bearer {api_key}',
            'Content-Type' => 'application/json',
        ],
        'body' => [
            'model' => '{model}',
            'prompt' => '{prompt}',
            'max_tokens' => '{max_tokens}',
            'temperature' => '{temperature}',
        ],
        'response_path' => 'result.text',
    ]);

    $aiProvider = AiProvider::factory()->custom()->create([
        'base_url' => 'https://api.example.com',
        'custom_template' => $template,
    ]);

    $service = new CustomProvider();
    $result = $service->generate($aiProvider, 'Write about testing', ['title', 'content']);

    expect($result)->toBeArray()
        ->toHaveKey('title')
        ->toHaveKey('content')
        ->and($result['title'])->toBe('Custom Title');
});

it('throws http error on failure', function () {
    Http::fake([
        'api.example.com/*' => Http::response([], 403),
    ]);

    $template = json_encode([
        'method' => 'POST',
        'url' => 'https://api.example.com/v1/completions',
        'headers' => ['Content-Type' => 'application/json'],
        'body' => ['prompt' => '{prompt}'],
        'response_path' => 'result.text',
    ]);

    $aiProvider = AiProvider::factory()->custom()->create([
        'base_url' => 'https://api.example.com',
        'custom_template' => $template,
    ]);

    $service = new CustomProvider();
    $service->generate($aiProvider, 'test', ['title']);
})->throws(AiGenerationException::class, 'HTTP 403');
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec laravel.test php artisan test --compact --filter=CustomProvider`
Expected: FAIL — class not found

- [ ] **Step 3: Implement CustomProvider**

```php
<?php

namespace App\Services\Ai;

use App\Contracts\AiProviderInterface;
use App\Exceptions\AiGenerationException;
use App\Models\AiProvider;
use Illuminate\Support\Facades\Http;

class CustomProvider implements AiProviderInterface
{
    public function generate(AiProvider $provider, string $prompt, array $outputTypes, array $options = []): array
    {
        if (! $provider->enabled) {
            throw AiGenerationException::providerDisabled($provider->name);
        }

        $template = json_decode($provider->custom_template, true);

        if (! is_array($template)) {
            throw AiGenerationException::invalidResponse($provider->name);
        }

        $placeholders = [
            '{api_key}' => $provider->api_key,
            '{model}' => $provider->model,
            '{prompt}' => $prompt,
            '{max_tokens}' => (string) $provider->max_tokens,
            '{temperature}' => (string) $provider->temperature,
            '{output_types}' => implode(',', $outputTypes),
        ];

        $url = str_replace(array_keys($placeholders), array_values($placeholders), $template['url'] ?? '');
        $headers = $this->replacePlaceholders($template['headers'] ?? [], $placeholders);
        $body = $this->replacePlaceholders($template['body'] ?? [], $placeholders);
        $method = strtoupper($template['method'] ?? 'POST');
        $responsePath = $template['response_path'] ?? '';

        $response = Http::withHeaders($headers)->timeout(60)->send($method, $url, ['json' => $body]);

        if ($response->failed()) {
            throw AiGenerationException::httpError($provider->name, $response->status());
        }

        $content = data_get($response->json(), $responsePath);

        $decoded = is_string($content) ? json_decode($content, true) : $content;

        if (! is_array($decoded)) {
            throw AiGenerationException::invalidResponse($provider->name);
        }

        return $decoded;
    }

    private function replacePlaceholders(array $data, array $placeholders): array
    {
        return json_decode(
            str_replace(array_keys($placeholders), array_values($placeholders), json_encode($data)),
            true
        );
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose exec laravel.test php artisan test --compact --filter=CustomProvider`
Expected: 3 tests pass

- [ ] **Step 5: Commit**

Run: `git add app/Services/Ai/CustomProvider.php tests/Unit/Services/Ai/CustomProviderTest.php && git commit -m "Feature: Custom template AI provider adapter"`

---

### Task 7: AiService Orchestrator

**Files:**
- Create: `app/Services/AiService.php`
- Create: `tests/Unit/Services/AiServiceTest.php`

**Interfaces:**
- Consumes: `OpenAiProvider`, `OllamaProvider`, `CustomProvider`
- Produces: `AiService::generate()` — called by `GenerateAiPostAction` in Task 9

- [ ] **Step 1: Write failing test**

```php
<?php

use App\Enums\AiProviderType;
use App\Exceptions\AiGenerationException;
use App\Models\AiProvider;
use App\Services\AiService;
use App\Services\Ai\CustomProvider;
use App\Services\Ai\OllamaProvider;
use App\Services\Ai\OpenAiProvider;
use Illuminate\Support\Facades\Http;

it('dispatches to openai provider by type', function () {
    Http::fake([
        'api.openai.com/*' => Http::response([
            'choices' => [
                ['message' => ['content' => json_encode(['title' => 'Test'])]],
            ],
        ], 200),
    ]);

    $aiProvider = AiProvider::factory()->create([
        'type' => AiProviderType::OpenAi,
        'base_url' => 'https://api.openai.com/v1',
    ]);

    $service = app(AiService::class);
    $result = $service->generate($aiProvider, 'test', ['title']);

    expect($result)->toHaveKey('title');
});

it('dispatches to ollama provider by type', function () {
    Http::fake([
        'localhost:11434/*' => Http::response([
            'response' => json_encode(['title' => 'Ollama Test']),
        ], 200),
    ]);

    $aiProvider = AiProvider::factory()->ollama()->create([
        'base_url' => 'http://localhost:11434',
    ]);

    $service = app(AiService::class);
    $result = $service->generate($aiProvider, 'test', ['title']);

    expect($result)->toHaveKey('title');
});

it('throws exception for disabled provider', function () {
    $aiProvider = AiProvider::factory()->create([
        'enabled' => false,
    ]);

    $service = app(AiService::class);
    $service->generate($aiProvider, 'test', ['title']);
})->throws(AiGenerationException::class, 'disabled');
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec laravel.test php artisan test --compact --filter=AiServiceTest`
Expected: FAIL — class not found

- [ ] **Step 3: Implement AiService**

```php
<?php

namespace App\Services;

use App\Enums\AiProviderInterface;
use App\Enums\AiProviderType;
use App\Exceptions\AiGenerationException;
use App\Models\AiProvider;
use App\Services\Ai\CustomProvider;
use App\Services\Ai\OllamaProvider;
use App\Services\Ai\OpenAiProvider;

class AiService
{
    public function __construct(
        private readonly OpenAiProvider $openAi,
        private readonly OllamaProvider $ollama,
        private readonly CustomProvider $custom,
    ) {}

    public function generate(
        AiProvider $provider,
        string $prompt,
        array $outputTypes,
        array $options = [],
    ): array {
        if (! $provider->enabled) {
            throw AiGenerationException::providerDisabled($provider->name);
        }

        $adapter = match ($provider->type) {
            AiProviderType::OpenAi => $this->openAi,
            AiProviderType::Ollama => $this->ollama,
            AiProviderType::Custom => $this->custom,
        };

        return $adapter->generate($provider, $prompt, $outputTypes, $options);
    }
}
```

- [ ] **Step 4: Register AiService in AppServiceProvider**

Add to `register()` method in `app/Providers/AppServiceProvider.php`:

```php
$this->app->singleton(AiService::class, function ($app) {
    return new AiService(
        $app->make(OpenAiProvider::class),
        $app->make(OllamaProvider::class),
        $app->make(CustomProvider::class),
    );
});
```

- [ ] **Step 5: Run test to verify it passes**

Run: `docker compose exec laravel.test php artisan test --compact --filter=AiServiceTest`
Expected: 3 tests pass

- [ ] **Step 6: Commit**

Run: `git add app/Services/AiService.php app/Providers/AppServiceProvider.php tests/Unit/Services/AiServiceTest.php && git commit -m "Feature: AiService orchestrator with provider dispatch"`

---

### Task 8: AiSettings Filament Page

**Files:**
- Create: `app/Filament/Pages/AiSettings.php`
- Create: `resources/views/filament/pages/ai-settings.blade.php`
- Create: `tests/Feature/Filament/Pages/AiSettingsTest.php`

**Interfaces:**
- Consumes: `AiProvider` model, `AiProviderType` enum, `Setting` model
- Produces: AI Settings page at `/admin/ai-settings`

- [ ] **Step 1: Write failing test**

```php
<?php

use App\Models\User;

it('ai settings page renders for admin', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get('/admin/ai-settings');
    $response->assertStatus(200);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec laravel.test php artisan test --compact --filter=AiSettingsTest`
Expected: FAIL — 404

- [ ] **Step 3: Create the Blade view**

```blade
<x-filament-pages::page>
    <div>
        {{ $this->form }}
    </div>
</x-filament-pages::page>
```

- [ ] **Step 4: Create AiSettings page**

```php
<?php

namespace App\Filament\Pages;

use App\Enums\AiProviderType;
use App\Models\AiProvider;
use App\Models\Setting;
use BackedEnum;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Slider;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use UnitEnum;

class AiSettings extends Page
{
    protected string $view = 'filament.pages.ai-settings';

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-cpu-chip';

    protected static UnitEnum|string|null $navigationGroup = 'Administration';

    protected static ?string $navigationLabel = 'AI Settings';

    public ?array $providers = [];

    public ?string $defaultProvider = null;

    public ?array $defaultOutputTypes = [];

    public function mount(): void
    {
        $tenantId = auth()->user()->tenant_id;

        $this->providers = AiProvider::where('tenant_id', $tenantId)
            ->get()
            ->map(fn (AiProvider $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'type' => $p->type->value,
                'model' => $p->model,
                'base_url' => $p->base_url,
                'api_key' => $p->api_key,
                'temperature' => $p->temperature,
                'max_tokens' => $p->max_tokens,
                'custom_template' => $p->custom_template,
                'enabled' => $p->enabled,
            ])
            ->toArray();

        $this->defaultProvider = Setting::where('tenant_id', $tenantId)
            ->where('key', 'ai_default_provider')
            ->value('value');

        $this->defaultOutputTypes = json_decode(
            Setting::where('tenant_id', $tenantId)
                ->where('key', 'ai_default_output_types')
                ->value('value') ?? '[]',
            true
        );
    }

    public function form(Schema $schema): Schema
    {
        $enabledProviders = AiProvider::where('tenant_id', auth()->user()->tenant_id)
            ->where('enabled', true)
            ->pluck('name', 'id')
            ->toArray();

        return $schema
            ->components([
                Section::make('Providers')
                    ->schema([
                        Placeholder::make('provider_count')
                            ->label('Configured Providers')
                            ->content(fn () => (string) count($this->providers)),
                        // Provider management handled via Livewire methods
                    ]),
                Section::make('Defaults')
                    ->schema([
                        Select::make('defaultProvider')
                            ->label('Default Provider')
                            ->options($enabledProviders)
                            ->nullable(),
                        CheckboxList::make('defaultOutputTypes')
                            ->label('Default Output Types')
                            ->options([
                                'title' => 'Title',
                                'content' => 'Content',
                                'excerpt' => 'Excerpt',
                                'categories' => 'Categories',
                                'tags' => 'Tags',
                            ])
                            ->columns(3),
                    ]),
                Section::make('Media Generation')
                    ->schema([
                        Placeholder::make('coming_soon')
                            ->label('Coming Soon')
                            ->content('Media generation is coming soon. AI-powered image and video creation will be available in a future update.'),
                    ]),
            ]);
    }

    public function saveDefaults(): void
    {
        $tenantId = auth()->user()->tenant_id;

        Setting::updateOrCreate(
            ['tenant_id' => $tenantId, 'key' => 'ai_default_provider'],
            ['value' => $this->defaultProvider]
        );

        Setting::updateOrCreate(
            ['tenant_id' => $tenantId, 'key' => 'ai_default_output_types'],
            ['value' => json_encode($this->defaultOutputTypes)]
        );

        Notification::make()
            ->title('Defaults saved')
            ->success()
            ->send();
    }
}
```

- [ ] **Step 5: Run test to verify it passes**

Run: `docker compose exec laravel.test php artisan test --compact --filter=AiSettingsTest`
Expected: 1 test passes

- [ ] **Step 6: Reload Octane and commit**

Run: `docker compose exec laravel.test php artisan octane:reload`
Run: `git add app/Filament/Pages/AiSettings.php resources/views/filament/pages/ai-settings.blade.php tests/Feature/Filament/Pages/AiSettingsTest.php && git commit -m "Feature: AI Settings custom Filament page"`

---

### Task 9: GenerateAiPostJob (Queued)

**Files:**
- Create: `app/Jobs/GenerateAiPostJob.php`
- Create: `tests/Feature/Jobs/GenerateAiPostJobTest.php`

**Interfaces:**
- Consumes: `AiService::generate()`, `AiProvider` model, `Post` model
- Produces: Queued job that generates content and sends notifications

- [ ] **Step 1: Write failing test**

```php
<?php

use App\Enums\AiProviderType;
use App\Enums\PostStatus;
use App\Jobs\GenerateAiPostJob;
use App\Models\AiProvider;
use App\Models\Post;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

it('generates content and updates post', function () {
    Http::fake([
        'api.openai.com/*' => Http::response([
            'choices' => [
                ['message' => ['content' => json_encode([
                    'title' => 'Generated Title',
                    'content' => '<p>Generated content</p>',
                    'excerpt' => 'Generated excerpt.',
                ])]],
            ],
        ], 200),
    ]);

    $user = User::factory()->create();
    $provider = AiProvider::factory()->create([
        'type' => AiProviderType::OpenAi,
        'base_url' => 'https://api.openai.com/v1',
        'tenant_id' => $user->tenant_id,
    ]);

    $post = Post::factory()->create([
        'tenant_id' => $user->tenant_id,
        'author_id' => $user->id,
        'status' => PostStatus::Draft,
    ]);

    Notification::fake();

    $job = new GenerateAiPostJob(
        $post->id,
        $provider->id,
        'gpt-4o',
        'Write about Laravel',
        ['title', 'content', 'excerpt'],
        ['length_type' => 'paragraphs', 'length_value' => 4]
    );

    $job->handle();

    $post->refresh();

    expect($post->title)->toBe('Generated Title')
        ->and($post->content)->toBe('<p>Generated content</p>')
        ->and($post->excerpt)->toBe('Generated excerpt.');

    Notification::assertSentTo($user, \Illuminate\Notifications\DatabaseNotification::class);
});

it('sends error notification on failure', function () {
    Http::fake([
        'api.openai.com/*' => Http::response([], 429),
    ]);

    $user = User::factory()->create();
    $provider = AiProvider::factory()->create([
        'type' => AiProviderType::OpenAi,
        'base_url' => 'https://api.openai.com/v1',
        'tenant_id' => $user->tenant_id,
    ]);

    $post = Post::factory()->create([
        'tenant_id' => $user->tenant_id,
        'author_id' => $user->id,
    ]);

    Notification::fake();

    $job = new GenerateAiPostJob(
        $post->id,
        $provider->id,
        'gpt-4o',
        'test',
        ['title'],
        []
    );

    $job->handle();

    Notification::assertSentTo($user, function ($notification) {
        return $notification->toArray()['title'] === 'AI generation failed';
    });
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec laravel.test php artisan test --compact --filter=GenerateAiPostJobTest`
Expected: FAIL — class not found

- [ ] **Step 3: Implement GenerateAiPostJob**

```php
<?php

namespace App\Jobs;

use App\Exceptions\AiGenerationException;
use App\Models\AiProvider;
use App\Models\Post;
use App\Models\User;
use App\Services\AiService;
use Filament\Notifications\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateAiPostJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(
        public string $postId,
        public string $providerId,
        public string $model,
        public string $prompt,
        public array $outputTypes,
        public array $options,
    ) {}

    public function handle(AiService $aiService): void
    {
        $post = Post::findOrFail($this->postId);
        $provider = AiProvider::findOrFail($this->providerId);
        $user = User::findOrFail($post->author_id);

        // Override model if user specified one
        $provider->model = $this->model;

        try {
            $result = $aiService->generate(
                $provider,
                $this->prompt,
                $this->outputTypes,
                $this->options
            );
        } catch (AiGenerationException $e) {
            Notification::make()
                ->title('AI generation failed')
                ->body($e->getMessage())
                ->danger()
                ->send();

            Notification::database()
                ->title('AI generation failed')
                ->body("Post [{$post->title}] — {$e->getMessage()}")
                ->danger()
                ->send();

            return;
        }

        // Update post with generated content
        $updateData = [];
        if (isset($result['title'])) {
            $updateData['title'] = $result['title'];
        }
        if (isset($result['content'])) {
            $updateData['content'] = $result['content'];
        }
        if (isset($result['excerpt'])) {
            $updateData['excerpt'] = $result['excerpt'];
        }

        if (! empty($updateData)) {
            $post->update($updateData);
        }

        // Sync categories if generated
        if (isset($result['categories']) && is_array($result['categories'])) {
            foreach ($result['categories'] as $categoryName) {
                $category = \App\Models\Category::firstOrCreate(
                    ['tenant_id' => $post->tenant_id, 'slug' => \Illuminate\Support\Str::slug($categoryName)],
                    ['name' => $categoryName]
                );
                $post->categories()->syncWithoutDetaching([$category->id]);
            }
        }

        // Sync tags if generated
        if (isset($result['tags']) && is_array($result['tags'])) {
            foreach ($result['tags'] as $tagName) {
                $tag = \App\Models\Tag::firstOrCreate(
                    ['tenant_id' => $post->tenant_id, 'slug' => \Illuminate\Support\Str::slug($tagName)],
                    ['name' => $tagName]
                );
                $post->tags()->syncWithoutDetaching([$tag->id]);
            }
        }

        // Send success notifications
        $fieldCount = count(array_filter(['title', 'content', 'excerpt', 'categories', 'tags'], fn ($f) => isset($result[$f])));

        Notification::make()
            ->title('Content generated')
            ->body("Your post [{$post->title}] has been generated with {$fieldCount} field(s). It's saved as a draft.")
            ->success()
            ->send();

        Notification::database()
            ->title('AI content generated')
            ->body("Post [{$post->title}] — {$fieldCount} field(s) generated. Saved as draft.")
            ->success()
            ->send();
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose exec laravel.test php artisan test --compact --filter=GenerateAiPostJobTest`
Expected: 2 tests pass

- [ ] **Step 5: Commit**

Run: `git add app/Jobs/GenerateAiPostJob.php tests/Feature/Jobs/GenerateAiPostJobTest.php && git commit -m "Feature: GenerateAiPostJob for async AI content generation"`

---

### Task 10: GenerateAiPostAction

**Files:**
- Create: `app/Filament/Actions/GenerateAiPostAction.php`
- Modify: `app/Filament/Resources/PostResource.php`
- Create: `tests/Feature/Filament/Actions/GenerateAiPostActionTest.php`

**Interfaces:**
- Consumes: `GenerateAiPostJob`, `AiProvider` model, `Setting` model
- Produces: Livewire action that dispatches async generation

- [ ] **Step 1: Write failing test**

```php
<?php

use App\Models\User;

it('generate ai post action is accessible on post create page', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get('/admin/posts/create');
    $response->assertStatus(200);
});
```

- [ ] **Step 2: Run test to verify it passes (page exists)**

Run: `docker compose exec laravel.test php artisan test --compact --filter=GenerateAiPostActionTest`
Expected: PASS (page already exists, action will be added)

- [ ] **Step 3: Create GenerateAiPostAction**

```php
<?php

namespace App\Filament\Actions;

use App\Jobs\GenerateAiPostJob;
use App\Models\AiProvider;
use App\Models\Setting;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Support\Enums\IconPosition;
use Filament\Tables\Actions\Action;

class GenerateAiPostAction extends Action
{
    public static function make(): static
    {
        return static::make('generateAi')
            ->label('Generate with AI')
            ->icon('heroicon-o-sparkles')
            ->iconPosition(IconPosition::After)
            ->form(fn (Select $form) => [
                Select::make('ai_provider_id')
                    ->label('Provider')
                    ->options(fn () => AiProvider::where('tenant_id', auth()->user()->tenant_id)
                        ->where('enabled', true)
                        ->pluck('name', 'id'))
                    ->default(fn () => Setting::where('tenant_id', auth()->user()->tenant_id)
                        ->where('key', 'ai_default_provider')
                        ->value('value'))
                    ->required(),
                TextInput::make('ai_model')
                    ->label('Model')
                    ->default(fn () => Setting::where('tenant_id', auth()->user()->tenant_id)
                        ->where('key', 'ai_last_model')
                        ->value('value'))
                    ->required()
                    ->placeholder('e.g. gpt-4o, llama3'),
                Textarea::make('ai_prompt')
                    ->label('Prompt')
                    ->rows(4)
                    ->required()
                    ->placeholder('Describe what you want to write about...'),
                Radio::make('ai_length_type')
                    ->label('Content Length')
                    ->options([
                        'paragraphs' => 'Paragraphs',
                        'characters' => 'Characters',
                    ])
                    ->default('paragraphs')
                    ->inline(),
                TextInput::make('ai_length_value')
                    ->label('Length Value')
                    ->default('4')
                    ->required(),
                CheckboxList::make('ai_output_types')
                    ->label('Generate')
                    ->options([
                        'title' => 'Title',
                        'content' => 'Content',
                        'excerpt' => 'Excerpt',
                        'categories' => 'Categories',
                        'tags' => 'Tags',
                    ])
                    ->default(['title', 'content', 'excerpt', 'categories', 'tags'])
                    ->columns(3),
            ])
            ->action(function (array $data, $record): void {
                $tenantId = auth()->user()->tenant_id;

                $provider = AiProvider::where('tenant_id', $tenantId)
                    ->where('id', $data['ai_provider_id'])
                    ->first();

                if (! $provider) {
                    Notification::make()
                        ->title('Provider not found')
                        ->danger()
                        ->send();

                    return;
                }

                // Save last used model
                Setting::updateOrCreate(
                    ['tenant_id' => $tenantId, 'key' => 'ai_last_model'],
                    ['value' => $data['ai_model']]
                );

                // Dispatch the queued job
                GenerateAiPostJob::dispatch(
                    $record?->id ?? '',
                    $data['ai_provider_id'],
                    $data['ai_model'],
                    $data['ai_prompt'],
                    $data['ai_output_types'],
                    [
                        'length_type' => $data['ai_length_type'],
                        'length_value' => (int) $data['ai_length_value'],
                    ]
                );

                Notification::make()
                    ->title('Generating content')
                    ->body('Your post is being generated. You\'ll receive a notification when it\'s ready. The post will be saved as a draft.')
                    ->success()
                    ->send();
            });
    }
}
```

- [ ] **Step 4: Add action to PostResource form**

In `app/Filament/Resources/PostResource.php`, add to the form components array (after the `content` Textarea):

```php
use App\Filament\Actions\GenerateAiPostAction;

// In form() method, after Textarea::make('content'):
GenerateAiPostAction::make(),
```

- [ ] **Step 5: Run Pint**

Run: `docker compose exec laravel.test ./vendor/bin/pint --dirty --format agent`

- [ ] **Step 6: Reload Octane and commit**

Run: `docker compose exec laravel.test php artisan octane:reload`
Run: `git add app/Filament/Actions/GenerateAiPostAction.php app/Filament/Resources/PostResource.php tests/Feature/Filament/Actions/GenerateAiPostActionTest.php && git commit -m "Feature: GenerateAiPostAction with async queued generation"`

---

### Task 11: Run Full Test Suite + Verify

**Files:** None (verification only)

- [ ] **Step 1: Run full test suite**

Run: `docker compose exec laravel.test php artisan test --compact`
Expected: All tests pass, no failures

- [ ] **Step 2: Run Pint on all files**

Run: `docker compose exec laravel.test ./vendor/bin/pint --format agent`

- [ ] **Step 3: Verify AI Settings page loads**

Run: `docker compose exec laravel.test php artisan route:list --path=ai-settings`
Expected: Route exists

- [ ] **Step 4: Final commit if any fixes needed**

Run: `git add -A && git commit -m "Refactor: AI Post Generation final fixes"` (only if changes were made)
