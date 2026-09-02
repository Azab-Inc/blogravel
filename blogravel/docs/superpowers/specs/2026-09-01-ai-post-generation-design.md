# AI Post Generation — Design Spec

## Overview

Add AI-powered content generation to Blogravel. Admins configure AI providers (OpenAI-compatible, Ollama, custom) with per-provider settings (model, temperature, max tokens). From the Post create/edit form, a "Generate with AI" modal lets admins pick a provider, enter a prompt, set content length, and select what to generate (title, content, excerpt, category/tag suggestions). Generated content fills the form fields.

## Goals

- Support multiple AI provider types without third-party dependencies
- Per-provider configuration (model, temperature, max tokens)
- Configurable output types (title, content, excerpt, categories, tags)
- Content length control (paragraphs/sentences or character count)
- Modal-based generation directly on the Post form
- Custom admin settings page for provider management
- Remember last used provider and model across sessions

## Non-Goals

- Streaming generation (future enhancement)
- Image/media generation — "Coming soon" label in UI
- Multi-tenant AI quota limits (future enhancement)

---

## Database

### Migration: Add columns to `ai_providers`

Add to existing `ai_providers` table:

| Column | Type | Notes |
|--------|------|-------|
| `type` | string | `openai`, `ollama`, or `custom` |
| `model` | string | Required — no default, user must pick |
| `temperature` | decimal(3,2) | 0.00–2.00, default `0.70` |
| `max_tokens` | integer | default `2048` |
| `custom_template` | text, nullable | JSON template for custom provider requests |

**Custom template example:**

```json
{
  "method": "POST",
  "url": "https://api.example.com/v1/completions",
  "headers": {
    "Authorization": "Bearer {api_key}",
    "Content-Type": "application/json"
  },
  "body": {
    "model": "{model}",
    "prompt": "{prompt}",
    "max_tokens": "{max_tokens}",
    "temperature": "{temperature}"
  },
  "response_path": "choices.0.text"
}
```

Placeholders: `{api_key}`, `{model}`, `{prompt}`, `{max_tokens}`, `{temperature}`, `{output_types}`

Existing columns (`name`, `api_key`, `base_url`, `enabled`) remain unchanged.

---

## Enums

### `AiProviderType` — `app/Enums/AiProviderType.php`

```php
enum AiProviderType: string
{
    case OpenAi = 'openai';
    case Ollama = 'ollama';
    case Custom = 'custom';

    public function label(): string { /* ... */ }
}
```

No default models — user must provide model name when creating a provider.

---

## Models

### `AiProvider` — updated `app/Models/AiProvider.php`

- Add `type`, `model`, `temperature`, `max_tokens`, `custom_template` to fillable
- Add casts: `type` → `AiProviderType::class`, `temperature` → `decimal:2`, `max_tokens` → `integer`
- Keep `api_key` → `encrypted`, `enabled` → `boolean`
- `model` is required (validation in form, not nullable in migration)

---

## Services

### SOLID Architecture

AiService follows the Dependency Inversion Principle. A contract defines what generation looks like; each provider type has its own implementation.

**Contract:**

```php
// app/Contracts/AiProviderInterface.php
interface AiProviderInterface
{
    public function generate(AiProvider $provider, string $prompt, array $outputTypes, array $options = []): array;
}
```

**Implementations:**

```php
// app/Services/Ai/OpenAiProvider.php
class OpenAiProvider implements AiProviderInterface { /* ... */ }

// app/Services/Ai/OllamaProvider.php
class OllamaProvider implements AiProviderInterface { /* ... */ }

// app/Services/Ai/CustomProvider.php
class CustomProvider implements AiProviderInterface { /* ... */ }
```

**AiService orchestrator:**

```php
// app/Services/AiService.php
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
        return match ($provider->type) {
            AiProviderType::OpenAi => $this->openAi->generate($provider, $prompt, $outputTypes, $options),
            AiProviderType::Ollama => $this->ollama->generate($provider, $prompt, $outputTypes, $options),
            AiProviderType::Custom => $this->custom->generate($provider, $prompt, $outputTypes, $options),
        };
    }
}
```

**`$options` array:**

- `length_type` → `'paragraphs'` or `'characters'`
- `length_value` → integer (e.g. `4` for 4 paragraphs, or `2000` for 2000 chars)

### Provider implementations

**`OpenAiProvider`**
- POST to `{base_url}/v1/chat/completions` (default: `https://api.openai.com/v1/chat/completions`)
- Headers: `Authorization: Bearer {api_key}`
- Body: `{ "model": "...", "messages": [...], "temperature": ..., "max_tokens": ... }`
- Parses `choices[0].message.content` JSON

**`OllamaProvider`**
- POST to `{base_url}/api/generate` (default: `http://localhost:11434/api/generate`)
- Body: `{ "model": "...", "prompt": "...", "stream": false }`
- System prompt instructs model to return JSON
- Parses response JSON

**`CustomProvider`**
- Uses `custom_template` JSON to build request
- Template placeholders: `{api_key}`, `{model}`, `{prompt}`, `{max_tokens}`, `{temperature}`
- Sends HTTP request to `{base_url}` with configured method/headers/body
- Parses response using configured `response_path` (dot notation)

### System prompt pattern

```
You are a blog content generator. Return ONLY valid JSON with these fields:
{requested fields}

Length requirement:
- {length_type}: {length_value}

Topic: {prompt}

Rules:
- Content should be well-structured with HTML formatting
- Excerpt should be 1-2 sentences (regardless of length setting)
- Categories and tags must be strings that match existing taxonomy or new suggestions
- Return ONLY the JSON object, no markdown fences, no explanation
```

**Error handling:**
- HTTP errors → throw `AiGenerationException` with provider name + status code
- Invalid JSON response → throw `AiGenerationException` with "Invalid response format"
- Provider disabled → throw `AiGenerationException` with "Provider is disabled"

---

## Filament

### `AiSettings` page — `app/Filament/Pages/AiSettings.php`

Custom Filament page under Administration group.

**Navigation:**
- `navigationIcon`: `heroicon-o-cpu-chip`
- `navigationGroup`: `'Administration'`
- `navigationLabel`: `'AI Settings'`
- `slug`: `'ai-settings'`

**Layout:**

Three tabs:

**Tab 1: Providers**
- Table of existing providers (name, type, model, enabled status)
- "Add Provider" button → opens modal form:
  - TextInput: name (required)
  - Select: type (OpenAI, Ollama, Custom)
  - TextInput: base_url (nullable, with provider-appropriate placeholder)
  - TextInput: api_key (required, password-type)
  - TextInput: model (required — no default, user must enter, e.g. `gpt-4o`, `llama3`)
  - Slider: temperature (0–2, step 0.1, default 0.7)
  - TextInput: max_tokens (default 2048)
  - Textarea: custom_template (visible only when type = Custom)
  - Toggle: enabled
- Edit button on each row → opens same modal pre-filled
- Delete button with confirmation

**Tab 2: Defaults**
- Select: default provider (from enabled providers)
- CheckboxList: default output types (`title`, `content`, `excerpt`, `categories`, `tags`)
- Save button → stores in `settings` table using existing Setting model (key-value pairs):
  - `ai_default_provider` → provider UUID
  - `ai_default_output_types` → JSON array of selected output types
  - `ai_last_model` → last selected model name (for form pre-selection)

**Tab 3: Media Generation**
- Placeholder card with text: "Media generation is coming soon. AI-powered image and video creation will be available in a future update."

### `GenerateAiPostAction` — `app/Filament/Actions/GenerateAiPostAction.php`

Livewire action attached to PostResource form.

**Behavior:**
1. Button labeled "Generate with AI" with `heroicon-o-sparkles` icon
2. Click opens modal with:
   - Select: provider (from enabled providers, pre-selected from last used)
   - TextInput: model (pre-filled from last used, editable)
   - Textarea: prompt (required, rows 4)
   - Radio: length_type (default `paragraphs`) — options: "Paragraphs" / "Characters"
   - TextInput: length_value (default `4` — when paragraphs selected; or `2000` when characters)
   - CheckboxList: output types (title, content, excerpt, categories, tags — all checked by default)
3. Submit → creates a draft Post immediately, dispatches `GenerateAiPostJob` to queue
4. Shows loading modal: "Generating content... A notification will be sent when your post is ready. The post will be saved as a draft."
5. Job completes → sends in-app notification (top-right bell) + email notification to user
6. On error → sends error notification with message

**PostResource form change:**
- Add `GenerateAiPostAction::make()` to form components (after content textarea)

### `GenerateAiPostJob` — `app/Jobs/GenerateAiPostJob.php`

Queued job implementing `ShouldQueue`.

**Tenant Queue Isolation (bounded pool):**
- Job dispatches to queue `ai-generation-{pool}` where pool = `crc32(tenant_id) % config('queue.ai_generation.pools', 4)`
- Tenants distribute across a bounded pool of queues, so one tenant's backlog only blocks tenants sharing its pool queue (crc32 collision), never the fleet
- Queue workers subscribe to the literal list: `php artisan queue:work --queue=ai-generation-0,ai-generation-1,ai-generation-2,ai-generation-3` (Laravel has no wildcard queue subscription)
- `config/queue.php` gains `ai_generation.pools` (int, default 4) so pool count scales with deployment

**Constructor:**
- `string $postId` — the draft post to update
- `string $providerId` — AI provider to use
- `string $model` — model name override
- `string $prompt` — user's prompt
- `array $outputTypes` — what to generate
- `array $options` — length_type, length_value
- Sets queue dynamically: `$this->onQueue("ai-generation-{pool}")` where pool = crc32 of the post's tenant_id modulo the configured pool count

**`handle()` method:**
1. Load Post and AiProvider from DB
2. Call `AiService::generate()`
3. Update Post with generated fields (title, content, excerpt)
4. Sync categories/tags if generated
5. Send in-app notification via `Notification::make()->database()`
6. Send email notification via `Mail::to($user->email)`

**Error handling:**
- Catches `AiGenerationException`
- Sends error notification to user with failure reason
- Post remains as draft with original state

**Queue worker command:**
```
php artisan queue:work --queue=ai-generation-0,ai-generation-1,ai-generation-2,ai-generation-3
```

---

## Tests

### Unit Tests

**`tests/Unit/Services/Ai/OpenAiProviderTest.php`**
- `test_generates_structured_json` — mock Http, verify request format, assert parsed response
- `test_throws_exception_on_http_error`
- `test_throws_exception_on_invalid_json`

**`tests/Unit/Services/Ai/OllamaProviderTest.php`**
- `test_generates_structured_json` — mock Http, verify Ollama format
- `test_throws_exception_on_http_error`

**`tests/Unit/Services/Ai/CustomProviderTest.php`**
- `test_uses_template_to_build_request` — mock Http, verify template rendering
- `test_throws_exception_on_http_error`

**`tests/Unit/Services/AiServiceTest.php`**
- `test_dispatches_to_correct_provider_by_type`
- `test_throws_exception_for_disabled_provider`

### Feature Tests

**`tests/Feature/Filament/Pages/AiSettingsTest.php`**
- `test_ai_settings_page_renders` — GET /admin/ai-settings returns 200
- `test_can_create_provider` — create via page action
- `test_can_edit_provider` — update via page action
- `test_can_delete_provider` — delete via page action

**`tests/Feature/Filament/Actions/GenerateAiPostActionTest.php`**
- `test_generate_action_returns_generated_fields` — mock AiService, assert form fields filled
- `test_generate_action_shows_error_on_failure`

---

## Files to Create/Modify

| File | Action |
|------|--------|
| `database/migrations/xxxx_add_type_to_ai_providers_table.php` | Create |
| `app/Enums/AiProviderType.php` | Create |
| `app/Models/AiProvider.php` | Modify (add fields, casts) |
| `app/Contracts/AiProviderInterface.php` | Create |
| `app/Services/AiService.php` | Create |
| `app/Services/Ai/OpenAiProvider.php` | Create |
| `app/Services/Ai/OllamaProvider.php` | Create |
| `app/Services/Ai/CustomProvider.php` | Create |
| `app/Exceptions/AiGenerationException.php` | Create |
| `app/Filament/Pages/AiSettings.php` | Create |
| `app/Filament/Actions/GenerateAiPostAction.php` | Create |
| `app/Jobs/GenerateAiPostJob.php` | Create |
| `app/Filament/Resources/PostResource.php` | Modify (add action) |
| `tests/Unit/Services/AiServiceTest.php` | Create |
| `tests/Unit/Services/Ai/OpenAiProviderTest.php` | Create |
| `tests/Unit/Services/Ai/OllamaProviderTest.php` | Create |
| `tests/Unit/Services/Ai/CustomProviderTest.php` | Create |
| `tests/Feature/Filament/Pages/AiSettingsTest.php` | Create |
| `tests/Feature/Filament/Actions/GenerateAiPostActionTest.php` | Create |
| `tests/Feature/Jobs/GenerateAiPostJobTest.php` | Create |
