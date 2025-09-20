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
            return response()->json([
                'status' => 'error',
                'message' => 'Too many requests',
                'retry_after' => $this->limiter->availableIn($key),
            ], 429);
        }

        $this->limiter->hit($key, $decayMinutes * 60);

        $response = $next($request);

        return $response->header('X-RateLimit-Limit', $maxAttempts)
            ->header('X-RateLimit-Remaining',
                $maxAttempts - $this->limiter->attempts($key));
    }

    protected function resolveRequestSignature(Request $request): string
    {
        $user = $request->user();

        return sha1(
            ($user ? $user->id : $request->ip()).'|'.$request->route()->getName()
        );
    }
}
