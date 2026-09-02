<?php

use App\Contracts\AiProviderInterface;
use App\Exceptions\AiGenerationException;

test('AiGenerationException extends RuntimeException', function () {
    $exception = new AiGenerationException('test');
    expect($exception)->toBeInstanceOf(RuntimeException::class);
});

test('providerDisabled returns correct exception', function () {
    $exception = AiGenerationException::providerDisabled('openai');

    expect($exception->getMessage())->toBe('AI provider [openai] is disabled.');
});

test('httpError returns correct exception with status code', function () {
    $exception = AiGenerationException::httpError('anthropic', 429);

    expect($exception->getMessage())->toBe('AI provider [anthropic] returned HTTP 429.');
});

test('invalidResponse returns correct exception', function () {
    $exception = AiGenerationException::invalidResponse('gemini');

    expect($exception->getMessage())->toBe('AI provider [gemini] returned an invalid response format.');
});

test('invalidTemplate returns correct exception', function () {
    $exception = AiGenerationException::invalidTemplate('custom');

    expect($exception->getMessage())->toBe('AI provider [custom] has an invalid custom template.');
});

test('AiProviderInterface is an interface', function () {
    expect(interface_exists(AiProviderInterface::class))->toBeTrue();
});
