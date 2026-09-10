<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyWebhookSignature
{
    public function handle(Request $request, Closure $next, string $secretConfig): Response
    {
        $signature = $request->header('X-Blogravel-Signature');

        if (! $signature) {
            abort(401, 'Missing webhook signature.');
        }

        $secret = config("webhooks.{$secretConfig}");

        if (! $secret) {
            abort(500, 'Webhook secret not configured.');
        }

        $payload = $request->getContent();
        $expected = hash_hmac('sha256', $payload, $secret);

        if (! hash_equals($expected, $signature)) {
            abort(401, 'Invalid webhook signature.');
        }

        return $next($request);
    }
}
