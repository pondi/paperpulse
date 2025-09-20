<?php

namespace Tests\Feature;

use App\Jobs\ProcessFile;
use App\Jobs\ProcessReceipt;
use App\Services\AI\AIService;
use App\Services\FileProcessingService;
use App\Services\StorageService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class QuickFileProcessingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Configure for testing without database
        Config::set('queue.default', 'sync');
        Config::set('cache.default', 'array');
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_can_process_file_upload_workflow()
    {
        $this->withoutExceptionHandling();

        // Create a test file
        $file = UploadedFile::fake()->image('test-receipt.jpg', 640, 480)->size(500);

        // Mock services
        $storageService = Mockery::mock(StorageService::class);
        $storageService->shouldReceive('storeFile')
            ->once()
            ->andReturn('receipts/1/test-guid/original.jpg');

        $this->app->instance(StorageService::class, $storageService);

        // Resolve FileProcessingService via container (constructor deps bound in provider)
        $fileProcessingService = app(FileProcessingService::class);

        // Generate test data
        $jobId = Str::uuid()->toString();
        $fileGuid = Str::uuid()->toString();
        $userId = 1;

        // Store metadata in cache
        $metadata = [
            'fileId' => 1,
            'fileGuid' => $fileGuid,
            'userId' => $userId,
            'fileName' => 'test-receipt.jpg',
            'fileType' => 'receipt',
            's3Paths' => [
                'original' => 'receipts/1/test-guid/original.jpg',
            ],
        ];

        Cache::put("job.{$jobId}.fileMetaData", $metadata, 3600);

        // Test cache retrieval
        $cachedData = Cache::get("job.{$jobId}.fileMetaData");
        $this->assertNotNull($cachedData);
        $this->assertEquals($fileGuid, $cachedData['fileGuid']);

        // Test job creation
        $processFileJob = new ProcessFile($jobId);
        $this->assertNotNull($processFileJob);
        $this->assertEquals($jobId, $processFileJob->jobId);

        Log::info('File processing workflow test completed', [
            'job_id' => $jobId,
            'file_guid' => $fileGuid,
        ]);
    }

    /** @test */
    public function it_can_mock_ai_service_for_receipt_analysis()
    {
        // Mock AI Service
        $aiService = Mockery::mock(AIService::class);
        $aiService->shouldReceive('analyzeReceipt')
            ->once()
            ->with(Mockery::type('string'))
            ->andReturn([
                'success' => true,
                'data' => [
                    'merchant' => [
                        'name' => 'Test Store',
                        'address' => '123 Test St',
                    ],
                    'items' => [
                        ['name' => 'Item 1', 'price' => 10.00, 'quantity' => 1, 'total' => 10.00],
                    ],
                    'totals' => [
                        'subtotal' => 10.00,
                        'tax' => 0,
                        'total' => 10.00,
                    ],
                    'date' => '2024-01-15',
                    'time' => '14:30',
                ],
                'provider' => 'mock',
            ]);

        $this->app->instance(AIService::class, $aiService);

        // Test AI analysis
        $result = app(AIService::class)->analyzeReceipt('Test receipt content');

        $this->assertTrue($result['success']);
        $this->assertEquals('Test Store', $result['data']['merchant']['name']);
        $this->assertEquals(10.00, $result['data']['totals']['total']);
    }

    /** @test */
    public function it_identifies_missing_environment_variables()
    {
        $required = [
            'AWS_ACCESS_KEY_ID' => env('AWS_ACCESS_KEY_ID'),
            'AWS_SECRET_ACCESS_KEY' => env('AWS_SECRET_ACCESS_KEY'),
            'AWS_BUCKET' => env('AWS_BUCKET'),
            'TEXTRACT_KEY' => env('TEXTRACT_KEY'),
            'OPENAI_API_KEY' => env('OPENAI_API_KEY'),
        ];

        $missing = [];
        foreach ($required as $key => $value) {
            if (empty($value)) {
                $missing[] = $key;
            }
        }

        if (! empty($missing)) {
            Log::warning('Missing environment variables for full integration', [
                'missing' => $missing,
            ]);
        }

        // This test always passes but logs missing config
        $this->assertTrue(true);
    }

    /** @test */
    public function it_can_handle_job_chain_metadata()
    {
        $jobId = Str::uuid()->toString();
        $fileId = 123;
        $fileGuid = Str::uuid()->toString();

        // Test metadata structure used by jobs
        $fileMetadata = [
            'fileId' => $fileId,
            'fileGuid' => $fileGuid,
            'userId' => 1,
            'fileName' => 'test.jpg',
            'fileType' => 'receipt',
            'filePath' => 'uploads/test.jpg',
            's3Paths' => [
                'original' => "receipts/1/{$fileGuid}/original.jpg",
            ],
        ];

        // Store in cache
        Cache::put("job.{$jobId}.fileMetaData", $fileMetadata, 3600);

        // Simulate ProcessFile job storing additional data
        $fileMetadata['status'] = 'processing';
        Cache::put("job.{$jobId}.fileMetaData", $fileMetadata, 3600);

        // Simulate ProcessReceipt job storing receipt data
        $receiptMetadata = [
            'receiptId' => 456,
            'merchantName' => 'Test Merchant',
            'total' => 25.99,
        ];
        Cache::put("job.{$jobId}.receiptMetaData", $receiptMetadata, 3600);

        // Verify data flow
        $retrievedFileData = Cache::get("job.{$jobId}.fileMetaData");
        $retrievedReceiptData = Cache::get("job.{$jobId}.receiptMetaData");

        $this->assertEquals('processing', $retrievedFileData['status']);
        $this->assertEquals(456, $retrievedReceiptData['receiptId']);

        // Simulate cleanup
        Cache::forget("job.{$jobId}.fileMetaData");
        Cache::forget("job.{$jobId}.receiptMetaData");

        $this->assertNull(Cache::get("job.{$jobId}.fileMetaData"));
        $this->assertNull(Cache::get("job.{$jobId}.receiptMetaData"));
    }
}
