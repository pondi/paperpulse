<?php

namespace App\Providers;

use App\Listeners\CreateUserPreferences;
use App\Models\Category;
use App\Models\Document;
use App\Models\File;
use App\Models\Receipt;
use App\Models\Tag;
use App\Policies\CategoryPolicy;
use App\Policies\DocumentPolicy;
use App\Policies\FilePolicy;
use App\Policies\ReceiptPolicy;
use App\Policies\TagPolicy;
use App\Services\AI\AIService;
use App\Services\AI\AIServiceFactory;
use Illuminate\Auth\Events\Registered;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register services as singletons for better performance
        $this->app->singleton(\App\Services\StorageService::class, function ($app) {
            return new \App\Services\StorageService();
        });
        
        $this->app->singleton(\App\Services\TextExtractionService::class, function ($app) {
            return new \App\Services\TextExtractionService(
                $app->make(\App\Services\StorageService::class)
            );
        });
        
        $this->app->singleton(\App\Services\FileProcessingService::class, function ($app) {
            return new \App\Services\FileProcessingService(
                $app->make(\App\Services\StorageService::class),
                $app->make(\App\Services\TextExtractionService::class)
            );
        });
        
        // Register AI services
        $this->app->bind(AIService::class, function ($app) {
            return AIServiceFactory::create();
        });
        
        $this->app->singleton(\App\Services\ReceiptAnalysisService::class, function ($app) {
            return new \App\Services\ReceiptAnalysisService(
                $app->make(AIService::class)
            );
        });
        
        $this->app->singleton(\App\Services\DocumentAnalysisService::class, function ($app) {
            return new \App\Services\DocumentAnalysisService(
                $app->make(AIService::class)
            );
        });
        
        // Register SharingService
        $this->app->singleton(\App\Services\SharingService::class, function ($app) {
            return new \App\Services\SharingService();
        });
        
        // Register SearchService
        $this->app->singleton(\App\Services\SearchService::class, function ($app) {
            return new \App\Services\SearchService();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureRateLimiting();
        $this->registerPolicies();

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

    /**
     * Register the application's policies.
     */
    protected function registerPolicies(): void
    {
        Gate::policy(Receipt::class, ReceiptPolicy::class);
        Gate::policy(File::class, FilePolicy::class);
        Gate::policy(Category::class, CategoryPolicy::class);
        Gate::policy(Document::class, DocumentPolicy::class);
        Gate::policy(Tag::class, TagPolicy::class);
    }
}
