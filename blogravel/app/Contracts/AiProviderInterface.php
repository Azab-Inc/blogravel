<?php

namespace App\Contracts;

use App\Exceptions\AiGenerationException;
use App\Models\AiProvider;

interface AiProviderInterface
{
    /**
     * Generate content using the AI provider.
     *
     * @param  string[]  $outputTypes  ['title', 'content', 'excerpt', 'categories', 'tags']
     * @param  array  $options  ['length_type' => 'paragraphs'|'characters', 'length_value' => int]
     * @return array{title?: string, content?: string, excerpt?: string, categories?: string[], tags?: string[]}
     *
     * @throws AiGenerationException
     */
    public function generate(AiProvider $provider, string $prompt, array $outputTypes, array $options = []): array;
}
