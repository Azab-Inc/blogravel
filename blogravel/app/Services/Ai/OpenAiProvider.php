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
