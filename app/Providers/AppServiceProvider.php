<?php

namespace App\Providers;

use App\Contracts\Services\FileDuplicationContract;
use App\Contracts\Services\ReceiptEnricherContract;
use App\Contracts\Services\ReceiptParserContract;
use App\Contracts\Services\ReceiptValidatorContract;
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
use App\Services\AI\PromptTemplateService;
use App\Services\DocumentAnalysisService;
use App\Services\DocumentService;
use App\Services\File\FileMetadataService;
use App\Services\File\FileStorageService;
use App\Services\File\FileValidationService;
use App\Services\FileProcessingService;
use App\Services\Files\FileJobChainDispatcher;
use App\Services\Files\FilePreviewManager;
use App\Services\Files\ImagePreviewStorage;
use App\Services\OCR\TextractStorageBridge;
use App\Services\Receipt\ReceiptEnricherService;
use App\Services\Receipt\ReceiptParserService;
use App\Services\Receipt\ReceiptValidatorService;
use App\Services\ReceiptAnalysisService;
use App\Services\SearchService;
use App\Services\SharingService;
use App\Services\StorageService;
use App\Services\TextExtractionService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
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
        $this->app->singleton(StorageService::class, function ($app) {
            return new StorageService;
        });

        $this->app->singleton(TextExtractionService::class, function ($app) {
            return new TextExtractionService(
                $app->make(StorageService::class)
            );
        });

        $this->app->singleton(FileStorageService::class, function ($app) {
            return new FileStorageService(
                $app->make(StorageService::class)
            );
        });

        $this->app->singleton(FileMetadataService::class, function ($app) {
            return new FileMetadataService(
                $app->make(FileValidationService::class)
            );
        });

        $this->app->singleton(FileValidationService::class, function ($app) {
            return new FileValidationService;
        });

        $this->app->singleton(FileJobChainDispatcher::class, function ($app) {
            return new FileJobChainDispatcher;
        });

        $this->app->singleton(FileProcessingService::class, function ($app) {
            return new FileProcessingService(
                $app->make(FileStorageService::class),
                $app->make(FileMetadataService::class),
                $app->make(FileValidationService::class),
                $app->make(FileDuplicationContract::class),
                $app->make(TextExtractionService::class),
                $app->make(FileJobChainDispatcher::class)
            );
        });

        // Register AI services
        $this->app->bind(AIService::class, function ($app) {
            return AIServiceFactory::create();
        });

        // Register Receipt service contracts
        $this->app->bind(ReceiptParserContract::class, ReceiptParserService::class);
        $this->app->bind(ReceiptValidatorContract::class, ReceiptValidatorService::class);
        $this->app->bind(ReceiptEnricherContract::class, ReceiptEnricherService::class);

        $this->app->singleton(ReceiptAnalysisService::class, function ($app) {
            return new ReceiptAnalysisService(
                $app->make(ReceiptParserContract::class),
                $app->make(ReceiptValidatorContract::class),
                $app->make(ReceiptEnricherContract::class)
            );
        });

        $this->app->singleton(DocumentAnalysisService::class, function ($app) {
            return new DocumentAnalysisService(
                $app->make(AIService::class)
            );
        });

        // Register SharingService
        $this->app->singleton(SharingService::class, function ($app) {
            return new SharingService;
        });

        // Register SearchService
        $this->app->singleton(SearchService::class, function ($app) {
            return new SearchService;
        });

        // Register DocumentService
        $this->app->singleton(DocumentService::class, function ($app) {
            return new DocumentService(
                $app->make(FileProcessingService::class),
                $app->make(StorageService::class)
            );
        });

        // Register AI template service
        $this->app->singleton(PromptTemplateService::class);

        // Register Image Preview services
        $this->app->singleton(ImagePreviewStorage::class, function ($app) {
            return new ImagePreviewStorage(
                $app->make(StorageService::class)
            );
        });

        $this->app->singleton(FilePreviewManager::class, function ($app) {
            return new FilePreviewManager(
                $app->make(ImagePreviewStorage::class)
            );
        });

        // Register TextractStorageBridge
        $this->app->singleton(TextractStorageBridge::class, function ($app) {
            return new TextractStorageBridge(
                $app->make(StorageService::class),
                $app->make(FileStorageService::class)
            );
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

        // Admin gate for route/middleware checks
        Gate::define('admin', function ($user) {
            return $user && method_exists($user, 'isAdmin') ? $user->isAdmin() : false;
        });

        // Register polymorphic morph map for extractable entities
        Relation::morphMap([
            'receipt' => \App\Models\Receipt::class,
            'document' => \App\Models\Document::class,
            'voucher' => \App\Models\Voucher::class,
            'warranty' => \App\Models\Warranty::class,
            'return_policy' => \App\Models\ReturnPolicy::class,
            'invoice' => \App\Models\Invoice::class,
            'contract' => \App\Models\Contract::class,
            'bank_statement' => \App\Models\BankStatement::class,
        ]);
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
