<?php

use App\Contracts\AiProviderInterface;
use App\Exceptions\AiGenerationException;
use App\Models\AiProvider;
use App\Services\Ai\OllamaProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('implements AiProviderInterface', function () {
    $provider = new OllamaProvider;
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

    $service = new OllamaProvider;
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

    $service = new OllamaProvider;
    $service->generate($aiProvider, 'test', ['title']);
})->throws(AiGenerationException::class, 'HTTP 500');
