<?php

use App\Http\Middleware\EnsureApiKeyHasAbility;
use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\EnsureWithinPlanLimits;
use App\Http\Middleware\VerifyWebhookSignature;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => EnsureUserHasRole::class,
            'api.key.ability' => EnsureApiKeyHasAbility::class,
            'webhook.signature' => VerifyWebhookSignature::class,
            'plan.limit' => EnsureWithinPlanLimits::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $rfc9457Types = [
            400 => 'https://tools.ietf.org/html/rfc9110#section-15.5.1',
            403 => 'https://tools.ietf.org/html/rfc9110#section-15.5.4',
            404 => 'https://tools.ietf.org/html/rfc9110#section-15.5.5',
            405 => 'https://tools.ietf.org/html/rfc9110#section-15.5.15',
            409 => 'https://tools.ietf.org/html/rfc9110#section-15.5.10',
            429 => 'https://tools.ietf.org/html/rfc6585#section-4',
            500 => 'https://tools.ietf.org/html/rfc9110#section-15.6.1',
        ];

        $rfc9457Titles = [
            400 => 'Bad Request',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            409 => 'Conflict',
            429 => 'Too Many Requests',
            500 => 'Internal Server Error',
        ];

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        $exceptions->renderable(function (Throwable $e, Request $request) {
            // Validation and Authentication exceptions: let Laravel's default
            // handler produce the correct status codes and response shapes.
            if ($e instanceof ValidationException || $e instanceof AuthenticationException) {
                return null;
            }

            if (! $request->is('api/*')) {
                return null;
            }

            $status = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 500;

            return new JsonResponse([
                'type' => $rfc9457Types[$status] ?? 'https://tools.ietf.org/html/rfc9110#section-15.6.1',
                'title' => $rfc9457Titles[$status] ?? 'Error',
                'status' => $status,
                'detail' => $e->getMessage(),
            ], $status, ['Content-Type' => 'application/problem+json']);
        });

        $exceptions->renderable(function (Throwable $e, Request $request) {
            if (app()->environment('local') && ! $request->is('api/*')) {
                file_put_contents(storage_path('logs/exception-capture.log'),
                    date('Y-m-d H:i:s')." {$request->method()} {$request->path()}\n".
                    $e->getMessage()."\n".
                    $e->getTraceAsString()."\n\n",
                    FILE_APPEND
                );
            }

            return null;
        });
    })->create();
