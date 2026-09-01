<?php

namespace App\Enums;

enum AiProviderType: string
{
    case OpenAi = 'openai';
    case Ollama = 'ollama';
    case Custom = 'custom';

    public function label(): string
    {
        return match ($this) {
            self::OpenAi => 'OpenAI',
            self::Ollama => 'Ollama',
            self::Custom => 'Custom',
        };
    }
}
