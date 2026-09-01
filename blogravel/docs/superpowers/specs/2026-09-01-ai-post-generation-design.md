# AI Post Generation — Design Spec

## Overview

Add AI-powered content generation to Blogravel. Admins configure AI providers (OpenAI-compatible, Ollama, custom) with per-provider settings (model, temperature, max tokens). From the Post create/edit form, a "Generate with AI" modal lets admins pick a provider, enter a prompt, and select what to generate (title, content, excerpt, category/tag suggestions). Generated content fills the form fields.

## Goals

- Support multiple AI provider types without third-party dependencies
- Per-provider configuration (model, temperature, max tokens)
- Configurable output types (title, content, excerpt, categories, tags)
- Modal-based generation directly on the Post form
- Custom admin settings page for provider management

## Non-Goals

- Streaming generation (future enhancement)
- Image/media generation
- Multi-tenant AI quota limits (future enhancement)

---

## Database

### Migration: Add columns to `ai_providers`

Add to existing `ai_providers` table:

| Column | Type | Notes |
|--------|------|-------|
| `type` | string | `openai`, `ollama`, or `custom` |
| `model` | string, nullable | e.g. `gpt-4o`, `llama3`. Provider-appropriate default if null |
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
    public function defaultModel(): ?string { /* ... */ }
}
```

- `OpenAi` → default model: `gpt-4o`
- `Ollama` → default model: `llama3`
- `Custom` → no default model (required in form)

---

## Models

### `AiProvider` — updated `app/Models/AiProvider.php`

- Add `type`, `model`, `temperature`, `max_tokens`, `custom_template` to fillable
- Add casts: `type` → `AiProviderType::class`, `temperature` → `decimal:2`, `max_tokens` → `integer`
- Keep `api_key` → `encrypted`, `enabled` → `boolean`

---

## Services

### `AiService` — `app/Services/AiService.php`

Core service that handles generation across all provider types.

```php
class AiService
{
    public function generate(
        AiProvider $provider,
        string $prompt,
        array $outputTypes, // ['title', 'content', 'excerpt', 'categories', 'tags']
    ): array;
}
```

**Internal methods:**

- `callOpenAi(AiProvider $provider, string $prompt, array $outputTypes): array`
  - POST to `{base_url}/v1/chat/completions` (default: `https://api.openai.com/v1/chat/completions`)
  - Headers: `Authorization: Bearer {api_key}`
  - Builds system prompt instructing model to return JSON with requested output types
  - Parses JSON response

- `callOllama(AiProvider $provider, string $prompt, array $outputTypes): array`
  - POST to `{base_url}/api/generate` (default: `http://localhost:11434/api/generate`)
  - Body: `{ "model": "...", "prompt": "...", "stream": false }`
  - System prompt instructs model to return JSON
  - Parses JSON from response

- `callCustom(AiProvider $provider, string $prompt, array $outputTypes): array`
  - Uses `custom_template` JSON to build request
  - Template is a JSON object with placeholders: `{prompt}`, `{output_types}`, `{model}`
  - Sends HTTP request to `{base_url}` with configured method/headers/body
  - Parses response using configured JSON path

**System prompt pattern:**

```
You are a blog content generator. Return ONLY valid JSON with these fields based on what was requested:
{requested fields description}

Topic: {prompt}

Rules:
- Content should be well-structured with HTML formatting
- Excerpt should be 1-2 sentences
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

Two tabs:

**Tab 1: Providers**
- Table of existing providers (name, type, model, enabled status)
- "Add Provider" button → opens modal form:
  - TextInput: name (required)
  - Select: type (OpenAI, Ollama, Custom)
  - TextInput: base_url (nullable, with provider-appropriate placeholder)
  - TextInput: api_key (required, password-type)
  - TextInput: model (nullable, with default hint from `AiProviderType::defaultModel()`)
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

### `GenerateAiPostAction` — `app/Filament/Actions/GenerateAiPostAction.php`

Livewire action attached to PostResource form.

**Behavior:**
1. Button labeled "Generate with AI" with `heroicon-o-sparkles` icon
2. Click opens modal with:
   - Select: provider (from enabled providers)
   - Textarea: prompt (required, rows 4)
   - CheckboxList: output types (title, content, excerpt, categories, tags — all checked by default)
3. Submit → calls `AiService::generate()` with selected provider + prompt + output types
4. Returns generated data to fill form fields:
   - `title` → fills `title` field
   - `content` → fills `content` field
   - `excerpt` → fills `excerpt` field
   - `categories` → adds to `categories` relationship (if they exist)
   - `tags` → adds to `tags` relationship (if they exist)
5. Shows success notification with fields filled count
6. On error → shows error notification with message

**PostResource form change:**
- Add `GenerateAiPostAction::make()` to form components (after content textarea)

---

## Tests

### Unit Tests

**`tests/Unit/Services/AiServiceTest.php`**
- `test_openai_provider_returns_structured_json` — mock Http, verify request format, assert parsed response
- `test_ollama_provider_returns_structured_json` — mock Http, verify Ollama format
- `test_custom_provider_uses_template` — mock Http, verify template rendering
- `test_throws_exception_for_disabled_provider`
- `test_throws_exception_on_http_error`
- `test_throws_exception_on_invalid_json`

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
| `app/Services/AiService.php` | Create |
| `app/Exceptions/AiGenerationException.php` | Create |
| `app/Filament/Pages/AiSettings.php` | Create |
| `app/Filament/Actions/GenerateAiPostAction.php` | Create |
| `app/Filament/Resources/PostResource.php` | Modify (add action) |
| `tests/Unit/Services/AiServiceTest.php` | Create |
| `tests/Feature/Filament/Pages/AiSettingsTest.php` | Create |
| `tests/Feature/Filament/Actions/GenerateAiPostActionTest.php` | Create |
