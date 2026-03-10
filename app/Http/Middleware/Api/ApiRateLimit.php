<?php

namespace App\Http\Middleware\Api;

use Closure;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Request;

class ApiRateLimit
{
    public function __construct(
        protected RateLimiter $limiter
    ) {}

    public function handle(Request $request, Closure $next, int $maxAttempts = 100, int $decayMinutes = 1)
    {
        $key = $this->resolveRequestSignature($request);

        if ($this->limiter->tooManyAttempts($key, $maxAttempts)) {
            $retryAfter = $this->limiter->availableIn($key);

            return response()->json([
                'status' => 'error',
                'code' => 'RATE_LIMITED',
                'message' => 'Too many requests',
                'errors' => null,
                'retry_after' => $retryAfter,
                'timestamp' => now()->toISOString(),
            ], 429)->withHeaders([
                'Retry-After' => $retryAfter,
                'X-RateLimit-Reset' => now()->addSeconds($retryAfter)->getTimestamp(),
            ]);
        }

        $this->limiter->hit($key, $decayMinutes * 60);

        $response = $next($request);

        $response->headers->set('X-RateLimit-Limit', (string) $maxAttempts);
        $response->headers->set('X-RateLimit-Remaining', (string) ($maxAttempts - $this->limiter->attempts($key)));

        return $response;
    }

    protected function resolveRequestSignature(Request $request): string
    {
        $user = $request->user();

        return sha1(
            ($user ? $user->id : $request->ip()).'|'.$request->route()->getName()
        );
    }
}
