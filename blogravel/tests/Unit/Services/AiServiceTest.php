<?php

use App\Enums\AiProviderType;
use App\Exceptions\AiGenerationException;
use App\Models\AiProvider;
use App\Services\AiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

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
