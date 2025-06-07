<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check session first (for immediate updates)
        if (session()->has('locale')) {
            App::setLocale(session('locale'));
        }
        // Then check user preferences
        elseif (auth()->check() && auth()->user()->preferences) {
            $locale = auth()->user()->preferences->language;
            App::setLocale($locale);
            session(['locale' => $locale]);
        }
        // Finally, check browser preference
        elseif ($request->hasHeader('Accept-Language')) {
            $locale = substr($request->header('Accept-Language'), 0, 2);
            if (in_array($locale, ['en', 'nb'])) {
                App::setLocale($locale);
            }
        }

        return $next($request);
    }
}
