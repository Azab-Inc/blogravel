<?php

namespace App\Services\Ai;

class PromptBuilder
{
    public static function build(string $prompt, array $outputTypes, array $options = []): string
    {
        $lengthType = $options['length_type'] ?? 'paragraphs';
        $lengthValue = $options['length_value'] ?? 4;

        $fields = implode(', ', $outputTypes);

        $lengthInstruction = match ($lengthType) {
            'characters' => "Length: approximately {$lengthValue} characters for content.",
            default => "Length: {$lengthValue} paragraphs for content.",
        };

        return <<<PROMPT
You are a blog content generator. Return ONLY valid JSON with these fields: {$fields}

{$lengthInstruction}

Topic: {$prompt}

Rules:
- Content should be well-structured with HTML formatting
- Excerpt should be 1-2 sentences (regardless of length setting)
- Categories and tags must be strings that match existing taxonomy or new suggestions
- Return ONLY the JSON object, no markdown fences, no explanation
PROMPT;
    }
}
