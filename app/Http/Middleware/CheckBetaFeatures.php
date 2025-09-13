<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckBetaFeatures
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $feature = 'beta'): Response
    {
        // Check if the specific feature is enabled
        $configKey = $feature === 'beta' ? 'features.beta.enabled' : "features.beta.{$feature}";

        if (! config($configKey, false)) {
            // If it's an API request, return a JSON error
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'This feature is currently in beta and not available.',
                ], 403);
            }

            // Otherwise redirect to dashboard with error message
            return redirect()->route('dashboard')
                ->with('error', 'This feature is currently in beta and not available.');
        }

        return $next($request);
    }
}
