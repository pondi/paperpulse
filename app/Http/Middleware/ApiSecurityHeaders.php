<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiSecurityHeaders
{
    /**
     * Handle an incoming request.
     *
     * @param Closure(Request): (Response) $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Add API-specific security headers
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');

        // Add API versioning header
        $response->headers->set('X-API-Version', config('app.api_version', '1.0'));

        // Remove server header
        $response->headers->remove('Server');
        $response->headers->remove('X-Powered-By');

        // Add request ID for tracking
        if ($request->hasHeader('X-Request-ID')) {
            $response->headers->set('X-Request-ID', $request->header('X-Request-ID'));
        }

        return $response;
    }
}
