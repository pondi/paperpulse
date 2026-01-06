<?php

use App\Http\Middleware\Api\ApiRateLimit;
use App\Http\Middleware\Api\ApiRequestLogger;
use App\Http\Middleware\Api\ApiVersion;
use App\Http\Middleware\ApiSecurityHeaders;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetLocale;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Trust all proxies for Kubernetes environments
        $middleware->trustProxies(
            at: '*',
            headers: Illuminate\Http\Request::HEADER_X_FORWARDED_FOR |
                     Illuminate\Http\Request::HEADER_X_FORWARDED_HOST |
                     Illuminate\Http\Request::HEADER_X_FORWARDED_PORT |
                     Illuminate\Http\Request::HEADER_X_FORWARDED_PROTO |
                     Illuminate\Http\Request::HEADER_X_FORWARDED_AWS_ELB
        );

        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            SetLocale::class,
            SecurityHeaders::class,
        ]);

        $middleware->api(append: [
            ApiSecurityHeaders::class,
            ApiRequestLogger::class,
        ]);

        // Register middleware aliases
        $middleware->alias([
            'api.version' => ApiVersion::class,
            'api.rate_limit' => ApiRateLimit::class,
            'admin' => EnsureUserIsAdmin::class,
        ]);

        //
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (ValidationException $e, $request) {
            if ($request->expectsJson()) {
                $response = [
                    'message' => $e->getMessage(),
                    'errors' => $e->errors(),
                ];

                if (config('app.debug')) {
                    $validatorData = $e->validator->getData();

                    $sensitiveKeys = [
                        'password',
                        'password_confirmation',
                        'current_password',
                        'token',
                        'access_token',
                        'refresh_token',
                    ];

                    foreach ($sensitiveKeys as $key) {
                        if (array_key_exists($key, $validatorData)) {
                            $validatorData[$key] = '*** redacted ***';
                        }
                    }

                    $response['debug'] = [
                        'validator_data' => $validatorData,
                        'failed_rules' => $e->validator->failed(),
                    ];
                }

                return response()->json($response, 422);
            }
        });

        $exceptions->render(function (Throwable $e, $request) {
            if (! $request->expectsJson() || ! config('app.debug')) {
                return null;
            }

            // Preserve default handling for framework exceptions that already have correct HTTP status codes.
            if (
                $e instanceof ValidationException ||
                $e instanceof AuthenticationException ||
                $e instanceof AuthorizationException ||
                $e instanceof ModelNotFoundException ||
                $e instanceof HttpExceptionInterface
            ) {
                return null;
            }

            return response()->json([
                'message' => $e->getMessage(),
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => collect($e->getTrace())->take(5)->toArray(),
            ], 500);
        });

        $exceptions->render(function (PostTooLargeException $e, $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'The uploaded payload exceeds the allowed size.',
                    'timestamp' => now()->toISOString(),
                ], 413);
            }
        });
    })->create();
