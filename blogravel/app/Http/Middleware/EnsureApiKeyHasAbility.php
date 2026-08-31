<?php

namespace App\Http\Middleware;

use App\Enums\ApiKeyAbility;
use App\Models\ApiKey;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureApiKeyHasAbility
{
    public function handle(Request $request, Closure $next, string ...$abilities): Response
    {
        $token = $request->header('X-Api-Key');

        if (! $token) {
            abort(401, 'API key required.');
        }

        $apiKey = ApiKey::where('token', $token)->first();

        if (! $apiKey) {
            abort(401, 'Invalid API key.');
        }

        if ($apiKey->expires_at && $apiKey->expires_at->isPast()) {
            abort(401, 'API key has expired.');
        }

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
