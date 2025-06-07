<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Auth\Events\Registered;
use App\Listeners\CreateUserPreferences;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureRateLimiting();
        
        // Register event listeners
        Event::listen(Registered::class, CreateUserPreferences::class);
    }

    /**
     * Configure the rate limiters for the application.
     */
    protected function configureRateLimiting(): void
    {
        // API rate limiting
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(config('paperpulse.rate_limits.api_requests', 60))
                ->by($request->user()?->id ?: $request->ip());
        });

        // File upload rate limiting
        RateLimiter::for('file-uploads', function (Request $request) {
            return Limit::perMinute(config('paperpulse.rate_limits.file_uploads', 10))
                ->by($request->user()->id)
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'message' => 'Too many file uploads. Please try again later.',
                        'retry_after' => $headers['Retry-After'],
                    ], 429);
                });
        });

        // PulseDav auth rate limiting
        RateLimiter::for('pulsedav-auth', function (Request $request) {
            return Limit::perMinute(config('paperpulse.rate_limits.pulsedav_auth', 10))
                ->by($request->ip())
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'error' => 'Too many authentication attempts.',
                        'retry_after' => $headers['Retry-After'],
                    ], 429);
                });
        });

        // Export rate limiting
        RateLimiter::for('exports', function (Request $request) {
            return Limit::perHour(config('paperpulse.rate_limits.export_requests', 10))
                ->by($request->user()->id)
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'message' => 'Too many export requests. Please try again later.',
                        'retry_after' => $headers['Retry-After'],
                    ], 429);
                });
        });
    }
}
