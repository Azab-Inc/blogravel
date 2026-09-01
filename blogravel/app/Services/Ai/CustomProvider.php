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
