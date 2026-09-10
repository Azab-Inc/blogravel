<?php

namespace App\Http\Middleware;

use App\Enums\ApiKeyAbility;
use App\Models\ApiKey;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class EnsureApiKeyHasAbility
{
    public function handle(Request $request, Closure $next, string ...$abilities): Response
    {
        $token = $request->header('X-Api-Key');

        if (! $token) {
            abort(401, 'API key required.');
        }

        $keyHash = hash('sha256', $token);
        $apiKey = ApiKey::where('key_hash', $keyHash)->first();

        if (! $apiKey) {
            abort(401, 'Invalid API key.');
        }

        if ($apiKey->expires_at && $apiKey->expires_at->isPast()) {
            abort(401, 'API key has expired.');
        }

        $rateLimit = $apiKey->resolveRateLimit();
        $limiter = 'api-key-'.$apiKey->id;

        if (RateLimiter::tooManyAttempts($limiter, $rateLimit)) {
            $retryAfter = RateLimiter::availableIn($limiter);

            return response()->json([
                'message' => 'Rate limit exceeded.',
                'retry_after' => $retryAfter,
            ], 429)->withHeaders([
                'Retry-After' => $retryAfter,
                'X-RateLimit-Limit' => $rateLimit,
                'X-RateLimit-Remaining' => 0,
            ]);
        }

        RateLimiter::hit($limiter, 60);

        $apiKey->touch('last_used_at');

        $request->attributes->set('apiKey', $apiKey);

        foreach ($abilities as $ability) {
            $enumValue = ApiKeyAbility::tryFrom($ability);

            if (! $enumValue || ! $apiKey->abilities->contains($enumValue)) {
                abort(403, 'API key lacks required ability: '.$ability);
            }
        }

        return $next($request);
    }
}
