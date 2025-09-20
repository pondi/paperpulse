<?php

namespace App\Providers;

use App\Contracts\Services\FileMetadataContract;
// File Service Contracts
use App\Contracts\Services\FileStorageContract;
use App\Contracts\Services\FileValidationContract;
use App\Contracts\Services\PulseDavFileContract;
// File Service Implementations
use App\Contracts\Services\PulseDavFolderContract;
use App\Contracts\Services\PulseDavImportContract;
use App\Contracts\Services\PulseDavSyncContract;
// Receipt Service Contracts
use App\Contracts\Services\ReceiptEnricherContract;
use App\Contracts\Services\ReceiptParserContract;
use App\Contracts\Services\ReceiptValidatorContract;
// Receipt Service Implementations
use App\Services\File\FileMetadataService;
use App\Services\File\FileStorageService;
use App\Services\File\FileValidationService;
// PulseDav Service Contracts
use App\Services\PulseDav\PulseDavFileService;
use App\Services\PulseDav\PulseDavFolderService;
use App\Services\PulseDav\PulseDavImportService;
use App\Services\PulseDav\PulseDavSyncService;
// PulseDav Service Implementations
use App\Services\Receipt\ReceiptEnricherService;
use App\Services\Receipt\ReceiptParserService;
use App\Services\Receipt\ReceiptValidatorService;
use Illuminate\Support\ServiceProvider;

class ServiceLayerServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // File Processing Services
        $this->app->bind(FileValidationContract::class, FileValidationService::class);
        $this->app->bind(FileStorageContract::class, FileStorageService::class);
        $this->app->bind(FileMetadataContract::class, FileMetadataService::class);

        // Receipt Analysis Services
        $this->app->bind(ReceiptParserContract::class, ReceiptParserService::class);
        $this->app->bind(ReceiptValidatorContract::class, ReceiptValidatorService::class);
        $this->app->bind(ReceiptEnricherContract::class, ReceiptEnricherService::class);

        // PulseDav Services
        $this->app->bind(PulseDavSyncContract::class, PulseDavSyncService::class);
        $this->app->bind(PulseDavFileContract::class, PulseDavFileService::class);
        $this->app->bind(PulseDavFolderContract::class, PulseDavFolderService::class);
        $this->app->bind(PulseDavImportContract::class, PulseDavImportService::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
