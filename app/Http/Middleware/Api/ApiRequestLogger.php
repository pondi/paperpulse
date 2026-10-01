<?php

namespace App\Http\Middleware\Api;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class ApiRequestLogger
{
    /**
     * Log sanitized API request/response metadata.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $start = microtime(true);
        $response = $next($request);

        $durationMs = round((microtime(true) - $start) * 1000, 1);
        $user = $request->user();

        Log::info('API request', [
            'method' => $request->getMethod(),
            'path' => $request->path(),
            'status' => $response->getStatusCode(),
            'duration_ms' => $durationMs,
            'user_id' => $user?->id,
            'ip' => $request->ip(),
            'user_agent_length' => strlen($request->userAgent() ?? ''),
            'payload' => $this->sanitizePayload($request),
        ]);

        return $response;
    }

    protected function sanitizePayload(Request $request): array
    {
        return ['field_count' => count($request->all()), 'file_count' => $request->files->count()];
    }
}
