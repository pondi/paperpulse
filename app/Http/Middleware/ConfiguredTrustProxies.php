<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;

class ConfiguredTrustProxies extends TrustProxies
{
    public function handle(Request $request, Closure $next): mixed
    {
        Request::setTrustedProxies(
            config('network.trusted_proxies', []),
            config('network.forwarded_headers'),
        );

        return $next($request);
    }
}
