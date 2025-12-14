<?php

namespace Tests\Feature;

use App\Jobs\DeleteWorkingFiles;
use App\Jobs\MatchMerchant;
use App\Jobs\ProcessFile;
use App\Jobs\ProcessReceipt;
use App\Models\File;
use App\Models\Merchant;
use App\Models\Receipt;
use App\Models\User;
use App\Services\AIService;
use App\Services\StorageService;
use App\Services\TextExtractionService;
use Aws\MockHandler;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class FileProcessingPipelineTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected MockHandler $s3MockHandler;

    protected MockHandler $textractMockHandler;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test user
        $this->user = User::factory()->create();

        // Configure test storage
        Storage::fake('local');
        Storage::fake('s3');

        // Setup AWS mock handlers
        $this->s3MockHandler = new MockHandler;
        $this->textractMockHandler = new MockHandler;

        // Configure queue for testing
        Queue::fake();

        // Configure test environment
        Config::set('filesystems.disks.s3.bucket', 'test-storage-bucket');
        Config::set('paperpulse.incoming_bucket', 'test-incoming-bucket');
        Config::set('paperpulse.storage_bucket', 'test-storage-bucket');
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_uploads_receipt_and_dispatches_job_chain()
    {
        // Arrange
        $file = UploadedFile::fake()->image('receipt.jpg', 100, 200)->size(500);

        // Mock S3 upload
        $this->mockS3Upload();

        // Act
        $response = $this->actingAs($this->user)
            ->postJson('/documents', [
                'files' => [$file],
                'file_type' => 'receipt',
            ]);

        // Assert
        $response->assertOk();
        $response->assertJsonStructure([
            'success',
            'job_id',
            'files' => [
                '*' => [
                    'id',
                    'name',
                    'status',
                ],
            ],
        ]);

        // Verify file record created
        $this->assertDatabaseHas('files', [
            'user_id' => $this->user->id,
            'original_name' => 'receipt.jpg',
            'status' => 'pending',
        ]);

        // Verify job chain dispatched
        Queue::assertPushed(ProcessFile::class, function ($job) {
            return $job->jobId !== null;
        });
    }

    /** @test */
    public function it_processes_receipt_through_entire_pipeline()
    {
        // This test will run the actual job chain
        Queue::fake()->except([
            ProcessFile::class,
            ProcessReceipt::class,
            MatchMerchant::class,
            DeleteWorkingFiles::class,
        ]);

        // Arrange
        $file = UploadedFile::fake()->image('receipt.jpg')->size(500);

        // Mock external services
        $this->mockS3Upload();
        $this->mockS3Download();
        $this->mockTextractOCR();
        $this->mockOpenAIAnalysis();
        $this->mockOpenAIMerchantMatch();

        // Act
        $response = $this->actingAs($this->user)
            ->postJson('/documents', [
                'files' => [$file],
                'file_type' => 'receipt',
            ]);

        $jobId = $response->json('job_id');

        // Process the job chain
        $this->artisan('queue:work', [
            '--stop-when-empty' => true,
            '--max-jobs' => 4,
        ]);

        // Assert
        // Check receipt was created
        $receipt = Receipt::where('user_id', $this->user->id)->first();
        $this->assertNotNull($receipt);
        $this->assertEquals('Test Store', $receipt->merchant_name);
        $this->assertEquals(25.99, $receipt->total);
        $this->assertEquals('2024-01-15', $receipt->date?->format('Y-m-d'));

        // Check line items were created
        $this->assertCount(2, $receipt->lineItems);
        $lineItem = $receipt->lineItems->first();
        $this->assertEquals('Product 1', $lineItem->name);
        $this->assertEquals(12.99, $lineItem->total);

        // Check merchant was matched/created
        $this->assertNotNull($receipt->merchant_id);
        $merchant = Merchant::find($receipt->merchant_id);
        $this->assertEquals('Test Store', $merchant->name);

        // Check file status updated
        $file = File::where('user_id', $this->user->id)->first();
        $this->assertEquals('completed', $file->status);

        // Check job history
        $this->assertDatabaseHas('job_histories', [
            'job_id' => $jobId,
            'status' => 'completed',
        ]);
    }

    /** @test */
    public function it_handles_pdf_receipt_conversion()
    {
        Queue::fake()->except([ProcessFile::class, ProcessReceipt::class]);

        // Arrange
        $file = UploadedFile::fake()->create('receipt.pdf', 1000, 'application/pdf');

        $this->mockS3Upload();
        $this->mockS3Download();
        $this->mockPdfConversion();
        $this->mockTextractOCR();
        $this->mockOpenAIAnalysis();

        // Act
        $response = $this->actingAs($this->user)
            ->postJson('/documents', [
                'files' => [$file],
                'file_type' => 'receipt',
            ]);

        $this->artisan('queue:work', [
            '--stop-when-empty' => true,
            '--max-jobs' => 2,
        ]);

        // Assert
        $receipt = Receipt::where('user_id', $this->user->id)->first();
        $this->assertNotNull($receipt);

        // Verify converted image was uploaded to S3
        $file = File::where('user_id', $this->user->id)->first();
        $this->assertStringContainsString('converted.jpg', $file->s3_paths['converted'] ?? '');
    }

    /** @test */
    public function it_handles_textract_failure_gracefully()
    {
        Queue::fake()->except([ProcessFile::class, ProcessReceipt::class]);

        // Arrange
        $file = UploadedFile::fake()->image('receipt.jpg')->size(500);

        $this->mockS3Upload();
        $this->mockS3Download();
        $this->mockTextractFailure();
        $this->mockOpenAIAnalysisWithFallback();

        // Act
        $response = $this->actingAs($this->user)
            ->postJson('/documents', [
                'files' => [$file],
                'file_type' => 'receipt',
            ]);

        $this->artisan('queue:work', [
            '--stop-when-empty' => true,
            '--max-jobs' => 2,
        ]);

        // Assert - should still create receipt with fallback
        $receipt = Receipt::where('user_id', $this->user->id)->first();
        $this->assertNotNull($receipt);
        $this->assertStringContainsString('fallback', $receipt->notes ?? '');
    }

    /** @test */
    public function it_handles_openai_failure_and_retries()
    {
        Queue::fake()->except([ProcessFile::class, ProcessReceipt::class]);

        // Arrange
        $file = UploadedFile::fake()->image('receipt.jpg')->size(500);

        $this->mockS3Upload();
        $this->mockS3Download();
        $this->mockTextractOCR();
        $this->mockOpenAIFailure();

        // Act
        $response = $this->actingAs($this->user)
            ->postJson('/documents', [
                'files' => [$file],
                'file_type' => 'receipt',
            ]);

        $jobId = $response->json('job_id');

        // Process jobs - ProcessReceipt should fail
        $this->artisan('queue:work', [
            '--stop-when-empty' => true,
            '--max-jobs' => 2,
        ]);

        // Assert
        $this->assertDatabaseHas('job_histories', [
            'job_id' => $jobId,
            'job_name' => 'ProcessReceipt',
            'status' => 'failed',
        ]);

        // File should still be in processing state
        $file = File::where('user_id', $this->user->id)->first();
        $this->assertEquals('processing', $file->status);
    }

    /** @test */
    public function it_validates_file_type_and_size_restrictions()
    {
        // Test invalid file type
        $file = UploadedFile::fake()->create('document.exe', 100);

        $response = $this->actingAs($this->user)
            ->postJson('/documents', [
                'files' => [$file],
                'file_type' => 'receipt',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['files.0']);

        // Test oversized file
        $file = UploadedFile::fake()->image('receipt.jpg')->size(11000); // 11MB

        $response = $this->actingAs($this->user)
            ->postJson('/documents', [
                'files' => [$file],
                'file_type' => 'receipt',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['files.0']);
    }

    /** @test */
    public function it_handles_concurrent_file_uploads()
    {
        // Arrange
        $files = [
            UploadedFile::fake()->image('receipt1.jpg')->size(500),
            UploadedFile::fake()->image('receipt2.jpg')->size(500),
            UploadedFile::fake()->image('receipt3.jpg')->size(500),
        ];

        $this->mockS3Upload(3);

        // Act
        $response = $this->actingAs($this->user)
            ->postJson('/documents', [
                'files' => $files,
                'file_type' => 'receipt',
            ]);

        // Assert
        $response->assertOk();
        $this->assertCount(3, $response->json('files'));

        // Verify 3 files created
        $this->assertEquals(3, File::where('user_id', $this->user->id)->count());

        // Verify 3 jobs dispatched
        Queue::assertPushed(ProcessFile::class, 3);
    }

    /** @test */
    public function it_cleans_up_working_files_after_processing()
    {
        Queue::fake()->except([
            ProcessFile::class,
            ProcessReceipt::class,
            MatchMerchant::class,
            DeleteWorkingFiles::class,
        ]);

        // Arrange
        $file = UploadedFile::fake()->image('receipt.jpg')->size(500);

        $this->mockS3Upload();
        $this->mockS3Download();
        $this->mockTextractOCR();
        $this->mockOpenAIAnalysis();
        $this->mockOpenAIMerchantMatch();

        // Act
        $response = $this->actingAs($this->user)
            ->postJson('/documents', [
                'files' => [$file],
                'file_type' => 'receipt',
            ]);

        $jobId = $response->json('job_id');

        // Process entire job chain
        $this->artisan('queue:work', [
            '--stop-when-empty' => true,
            '--max-jobs' => 4,
        ]);

        // Assert
        // Cache should be cleared
        $this->assertNull(Cache::get("job.{$jobId}.fileMetaData"));
        $this->assertNull(Cache::get("job.{$jobId}.receiptMetaData"));

        // Local files should be deleted
        $fileRecord = File::where('user_id', $this->user->id)->first();
        Storage::disk('local')->assertMissing("uploads/{$fileRecord->file_guid}");
    }

    /** @test */
    public function it_ensures_user_data_isolation()
    {
        // Arrange
        $otherUser = User::factory()->create();

        // Create receipt for first user
        $file = UploadedFile::fake()->image('receipt.jpg')->size(500);
        $this->mockS3Upload();

        $this->actingAs($this->user)
            ->postJson('/documents', [
                'files' => [$file],
                'file_type' => 'receipt',
            ]);

        // Act - try to access as different user
        $fileRecord = File::where('user_id', $this->user->id)->first();

        $response = $this->actingAs($otherUser)
            ->get("/receipts/{$fileRecord->id}");

        // Assert
        $response->assertStatus(404);
    }

    // Helper methods for mocking

    protected function mockS3Upload($times = 1)
    {
        $storageService = Mockery::mock(StorageService::class);
        $storageService->shouldReceive('upload')
            ->times($times)
            ->andReturn('receipts/1/test-guid/original.jpg');

        $this->app->instance(StorageService::class, $storageService);
    }

    protected function mockS3Download()
    {
        $storageService = Mockery::mock(StorageService::class);
        $storageService->shouldReceive('download')
            ->andReturn(UploadedFile::fake()->image('receipt.jpg')->size(500));
        $storageService->shouldReceive('upload')
            ->andReturn('receipts/1/test-guid/original.jpg');

        $this->app->instance(StorageService::class, $storageService);
    }

    protected function mockTextractOCR()
    {
        $textExtractionService = Mockery::mock(TextExtractionService::class);
        $textExtractionService->shouldReceive('extractTextFromImage')
            ->andReturn([
                'text' => "Test Store\n123 Main St\nReceipt #12345\n\nProduct 1 $12.99\nProduct 2 $13.00\n\nTotal: $25.99\nDate: 01/15/2024",
                'raw_response' => ['Blocks' => []],
            ]);

        $this->app->instance(TextExtractionService::class, $textExtractionService);
    }

    protected function mockTextractFailure()
    {
        $textExtractionService = Mockery::mock(TextExtractionService::class);
        $textExtractionService->shouldReceive('extractTextFromImage')
            ->andThrow(new Exception('Textract service unavailable'));

        $this->app->instance(TextExtractionService::class, $textExtractionService);
    }

    protected function mockOpenAIAnalysis()
    {
        $aiService = Mockery::mock(AIService::class);
        $aiService->shouldReceive('analyzeReceipt')
            ->andReturn([
                'merchant' => [
                    'name' => 'Test Store',
                    'address' => '123 Main St',
                    'vat_number' => null,
                ],
                'line_items' => [
                    [
                        'name' => 'Product 1',
                        'sku' => null,
                        'quantity' => 1,
                        'unit_price' => 12.99,
                        'total' => 12.99,
                    ],
                    [
                        'name' => 'Product 2',
                        'sku' => null,
                        'quantity' => 1,
                        'unit_price' => 13.00,
                        'total' => 13.00,
                    ],
                ],
                'subtotal' => 25.99,
                'tax' => 0,
                'total' => 25.99,
                'date' => '2024-01-15',
                'time' => null,
                'receipt_number' => '12345',
            ]);

        $this->app->instance(AIService::class, $aiService);
    }

    protected function mockOpenAIAnalysisWithFallback()
    {
        $aiService = Mockery::mock(AIService::class);
        $aiService->shouldReceive('analyzeReceipt')
            ->andReturn([
                'merchant' => [
                    'name' => 'Unknown Store',
                    'address' => null,
                    'vat_number' => null,
                ],
                'line_items' => [],
                'subtotal' => 0,
                'tax' => 0,
                'total' => 0,
                'date' => null,
                'time' => null,
                'receipt_number' => null,
                'notes' => 'Processed with fallback due to OCR failure',
            ]);

        $this->app->instance(AIService::class, $aiService);
    }

    protected function mockOpenAIFailure()
    {
        $aiService = Mockery::mock(AIService::class);
        $aiService->shouldReceive('analyzeReceipt')
            ->andThrow(new Exception('OpenAI API error'));

        $this->app->instance(AIService::class, $aiService);
    }

    protected function mockOpenAIMerchantMatch()
    {
        $aiService = Mockery::mock(AIService::class);
        $aiService->shouldReceive('analyzeReceipt')->andReturnUsing(function () {
            return [
                'merchant' => [
                    'name' => 'Test Store',
                    'address' => '123 Main St',
                    'vat_number' => null,
                ],
                'line_items' => [
                    [
                        'name' => 'Product 1',
                        'sku' => null,
                        'quantity' => 1,
                        'unit_price' => 12.99,
                        'total' => 12.99,
                    ],
                    [
                        'name' => 'Product 2',
                        'sku' => null,
                        'quantity' => 1,
                        'unit_price' => 13.00,
                        'total' => 13.00,
                    ],
                ],
                'subtotal' => 25.99,
                'tax' => 0,
                'total' => 25.99,
                'date' => '2024-01-15',
                'time' => null,
                'receipt_number' => '12345',
            ];
        });

        $aiService->shouldReceive('matchMerchant')
            ->andReturn([
                'merchant_name' => 'Test Store',
                'confidence' => 0.95,
                'is_new' => true,
            ]);

        $this->app->instance(AIService::class, $aiService);
    }

    protected function mockPdfConversion()
    {
        // Mock PDF to image conversion
        // This would typically be handled by Spatie\PdfToImage
        // For testing, we'll mock at the service level
        Storage::disk('local')->put(
            'uploads/test-guid-converted.jpg',
            UploadedFile::fake()->image('converted.jpg')->size(400)->get()
        );
    }
}
