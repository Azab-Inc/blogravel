<?php

namespace App\Services;

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
