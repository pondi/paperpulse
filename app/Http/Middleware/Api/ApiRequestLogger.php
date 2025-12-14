<?php

namespace App\Http\Middleware\Api;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;

class ApiRequestLogger
{
    /**
     * Log sanitized API request/response metadata.
     */
    public function handle(Request $request, Closure $next)
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
            'user_agent' => $request->userAgent(),
            'payload' => $this->sanitizePayload($request),
        ]);

        return $response;
    }

    protected function sanitizePayload(Request $request): array
    {
        $payload = [];

        // Include query params for GET/HEAD
        if (in_array($request->getMethod(), ['GET', 'HEAD'])) {
            $payload['query'] = $request->query();
        } else {
            $body = $request->all();
            $payload['body'] = Arr::except($body, [
                'password',
                'password_confirmation',
                'token',
                'access_token',
                'refresh_token',
            ]);
        }

        // Include file metadata only (not contents)
        if ($request->files->count() > 0) {
            $files = [];
            foreach ($request->files as $key => $file) {
                if (is_array($file)) {
                    $files[$key] = array_map(function ($f) {
                        return $this->fileInfo($f);
                    }, $file);
                } else {
                    $files[$key] = $this->fileInfo($file);
                }
            }
            $payload['files'] = $files;
        }

        return $payload;
    }

    protected function fileInfo($file): array
    {
        if (! $file) {
            return [];
        }

        return [
            'original_name' => $file->getClientOriginalName(),
            'mime' => $file->getClientMimeType(),
            'size' => $file->getSize(),
        ];
    }
}
