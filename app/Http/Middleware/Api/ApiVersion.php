<?php

namespace App\Http\Middleware\Api;

use Closure;
use Illuminate\Http\Request;

class ApiVersion
{
    public function handle(Request $request, Closure $next, string $version = 'v1')
    {
        $request->attributes->set('api_version', $version);

        // Set response headers
        return $next($request)->header('X-API-Version', $version);
    }
}
