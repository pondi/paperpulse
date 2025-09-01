<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
            \App\Http\Middleware\SetLocale::class,
            \App\Http\Middleware\SecurityHeaders::class,
        ]);

        $middleware->api(append: [
            \App\Http\Middleware\ApiSecurityHeaders::class,
        ]);

        // Register API middleware aliases
        $middleware->alias([
            'api.version' => \App\Http\Middleware\Api\ApiVersion::class,
            'api.rate_limit' => \App\Http\Middleware\Api\ApiRateLimit::class,
        ]);

        //
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (\Illuminate\Validation\ValidationException $e, $request) {
            if ($request->expectsJson()) {
                $response = [
                    'message' => $e->getMessage(),
                    'errors' => $e->errors(),
                ];

                if (config('app.debug')) {
                    $response['debug'] = [
                        'validator_data' => $e->validator->getData(),
                        'failed_rules' => $e->validator->failed(),
                    ];
                }

                return response()->json($response, 422);
            }
        });

        $exceptions->render(function (\Exception $e, $request) {
            if ($request->expectsJson() && config('app.debug')) {
                return response()->json([
                    'message' => $e->getMessage(),
                    'exception' => get_class($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => collect($e->getTrace())->take(5)->toArray(),
                ], 500);
            }
        });
    })->create();
