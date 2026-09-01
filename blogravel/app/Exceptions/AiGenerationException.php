<?php

namespace App\Exceptions;

use RuntimeException;

class AiGenerationException extends RuntimeException
{
    public static function providerDisabled(string $providerName): static
    {
        return new static("AI provider [{$providerName}] is disabled.");
    }

    public static function httpError(string $providerName, int $statusCode): static
    {
        return new static("AI provider [{$providerName}] returned HTTP {$statusCode}.");
    }

    public static function invalidResponse(string $providerName): static
    {
        return new static("AI provider [{$providerName}] returned an invalid response format.");
    }
}
