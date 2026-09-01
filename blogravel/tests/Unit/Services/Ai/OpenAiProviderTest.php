<?php

use App\Contracts\AiProviderInterface;
use App\Exceptions\AiGenerationException;
use App\Models\AiProvider;
use App\Services\Ai\OpenAiProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('implements AiProviderInterface', function () {
    $provider = new OpenAiProvider;
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

    $service = new OpenAiProvider;
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

    $service = new OpenAiProvider;
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

    $service = new OpenAiProvider;
    $service->generate($aiProvider, 'test', ['title']);
})->throws(AiGenerationException::class, 'invalid response');
