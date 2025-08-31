<?php

namespace App\Console\Commands;

use App\Services\FileProcessingService;
use App\Services\StorageService;
use App\Services\TextExtractionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class TestInfrastructure extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:infrastructure';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test the infrastructure services for document management';

    protected StorageService $storageService;

    protected TextExtractionService $textExtractionService;

    protected FileProcessingService $fileProcessingService;

    /**
     * Create a new command instance.
     */
    public function __construct(
        StorageService $storageService,
        TextExtractionService $textExtractionService,
        FileProcessingService $fileProcessingService
    ) {
        parent::__construct();
        $this->storageService = $storageService;
        $this->textExtractionService = $textExtractionService;
        $this->fileProcessingService = $fileProcessingService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Testing Infrastructure Services...');

        // Test StorageService
        $this->testStorageService();

        // Test TextExtractionService
        $this->testTextExtractionService();

        // Test FileProcessingService
        $this->testFileProcessingService();

        $this->info('Infrastructure test completed!');

        return Command::SUCCESS;
    }

    protected function testStorageService()
    {
        $this->info("\n=== Testing StorageService ===");

        try {
            // Test if S3 is configured
            $isS3 = $this->storageService->isS3Storage();
            $this->info('Storage type: '.($isS3 ? 'S3' : 'Local'));

            if ($isS3) {
                // Test file operations
                $testContent = 'Test content for infrastructure verification';
                $userId = 1;
                $fileId = 'test-'.uniqid();

                // Test storing a file
                $path = $this->storageService->storeFile(
                    $testContent,
                    $userId,
                    $fileId,
                    'document',
                    'test',
                    'txt'
                );

                $this->info("✓ File stored successfully at: {$path}");

                // Test retrieving the file
                $retrieved = $this->storageService->getFile($path);
                if ($retrieved === $testContent) {
                    $this->info('✓ File retrieved successfully');
                } else {
                    $this->error('✗ File retrieval failed - content mismatch');
                }

                // Test generating temporary URL
                $url = $this->storageService->getTemporaryUrl($path, 5);
                if ($url) {
                    $this->info('✓ Temporary URL generated: '.substr($url, 0, 50).'...');
                } else {
                    $this->error('✗ Temporary URL generation failed');
                }

                // Clean up
                $deleted = $this->storageService->deleteFile($path);
                if ($deleted) {
                    $this->info('✓ Test file cleaned up successfully');
                }

            } else {
                $this->warn('S3 not configured - using local storage');
            }

        } catch (\Exception $e) {
            $this->error('✗ StorageService test failed: '.$e->getMessage());
            Log::error('[TestInfrastructure] StorageService test failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    protected function testTextExtractionService()
    {
        $this->info("\n=== Testing TextExtractionService ===");

        try {
            // Create a test text file
            $testText = "This is a test document for text extraction.\nIt contains multiple lines.\nAnd should be extracted properly.";
            $testPath = storage_path('app/test-extraction.txt');
            file_put_contents($testPath, $testText);

            // Test extraction
            $extracted = $this->textExtractionService->extract($testPath, 'document', 'test-guid');

            if (str_contains($extracted, 'test document')) {
                $this->info('✓ Text extraction successful');
                $this->info('  Extracted '.strlen($extracted).' characters');
            } else {
                $this->warn('⚠ Text extraction returned unexpected content');
            }

            // Clean up
            unlink($testPath);
            $this->textExtractionService->clearCache('test-guid');

        } catch (\Exception $e) {
            $this->error('✗ TextExtractionService test failed: '.$e->getMessage());
            $this->warn('  Make sure AWS Textract is configured properly');
            Log::error('[TestInfrastructure] TextExtractionService test failed', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function testFileProcessingService()
    {
        $this->info("\n=== Testing FileProcessingService ===");

        try {
            // Test file type validation
            $receiptSupported = $this->fileProcessingService->isSupported('jpg', 'receipt');
            $documentSupported = $this->fileProcessingService->isSupported('pdf', 'document');

            $this->info('✓ Receipt format validation: '.($receiptSupported ? 'jpg supported' : 'jpg not supported'));
            $this->info('✓ Document format validation: '.($documentSupported ? 'pdf supported' : 'pdf not supported'));

            // Test max file sizes
            $receiptMaxSize = $this->fileProcessingService->getMaxFileSize('receipt');
            $documentMaxSize = $this->fileProcessingService->getMaxFileSize('document');

            $this->info('✓ Max receipt size: '.($receiptMaxSize / 1024 / 1024).' MB');
            $this->info('✓ Max document size: '.($documentMaxSize / 1024 / 1024).' MB');

        } catch (\Exception $e) {
            $this->error('✗ FileProcessingService test failed: '.$e->getMessage());
            Log::error('[TestInfrastructure] FileProcessingService test failed', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
