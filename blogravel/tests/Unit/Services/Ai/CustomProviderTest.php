<?php

use App\Contracts\AiProviderInterface;
use App\Exceptions\AiGenerationException;
use App\Models\AiProvider;
use App\Services\Ai\CustomProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('implements AiProviderInterface', function () {
    $provider = new CustomProvider;
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

    $service = new CustomProvider;
    $result = $service->generate($aiProvider, 'Write about testing', ['title', 'content']);

    expect($result)->toBeArray()
        ->toHaveKey('title')
        ->toHaveKey('content')
        ->and($result['title'])->toBe('Custom Title');
});

it('handles api keys containing json breaking characters in placeholders', function () {
    Http::fake([
        'api.example.com/*' => Http::response([
            'result' => ['text' => json_encode([
                'title' => 'Safe Title',
                'content' => '<p>Safe content</p>',
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
        ],
        'response_path' => 'result.text',
    ]);

    $aiProvider = AiProvider::factory()->custom()->create([
        'base_url' => 'https://api.example.com',
        'custom_template' => $template,
        'api_key' => 'api"key\\with"quotes',
    ]);

    $service = new CustomProvider;
    $result = $service->generate($aiProvider, 'Write about escaping', ['title', 'content']);

    expect($result)->toBeArray()
        ->toHaveKey('title')
        ->toHaveKey('content')
        ->and($result['title'])->toBe('Safe Title');
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

    $service = new CustomProvider;
    $service->generate($aiProvider, 'test', ['title']);
})->throws(AiGenerationException::class, 'HTTP 403');
