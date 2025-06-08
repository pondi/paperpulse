<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Prevent clickjacking attacks
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');

        // Prevent MIME type sniffing
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // Enable XSS protection in older browsers
        $response->headers->set('X-XSS-Protection', '1; mode=block');

        // Control which referrer information is sent
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Permissions Policy (formerly Feature Policy)
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        // Content Security Policy
        $viteServer = '';
        if (app()->environment('local') && file_exists(public_path('hot'))) {
            // In development with Vite running, allow the dev server
            $viteUrl = trim(file_get_contents(public_path('hot')));
            $viteServer = ' ' . $viteUrl;
        }

        $csp = "default-src 'self'{$viteServer}; ".
               "script-src 'self' 'unsafe-inline' 'unsafe-eval'{$viteServer}; ".
               "style-src 'self' 'unsafe-inline' https://fonts.bunny.net; ".
               "img-src 'self' data: blob:; ".
               "font-src 'self' data: https://fonts.bunny.net; ".
               "connect-src 'self'{$viteServer} ws://localhost:* wss://localhost:* ws://paperpulse.test:* wss://paperpulse.test:*; ".
               "media-src 'self'; ".
               "object-src 'none'; ".
               "base-uri 'self'; ".
               "form-action 'self'; ".
               "frame-ancestors 'self';";

        $response->headers->set('Content-Security-Policy', $csp);

        // Strict Transport Security (only for HTTPS)
        if ($request->secure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
