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
