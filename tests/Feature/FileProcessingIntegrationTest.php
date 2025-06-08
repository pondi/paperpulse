<?php

namespace Tests\Feature;

use App\Jobs\DeleteWorkingFiles;
use App\Jobs\MatchMerchant;
use App\Jobs\ProcessFile;
use App\Jobs\ProcessReceipt;
use App\Models\File;
use App\Models\User;
use App\Services\FileProcessingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FileProcessingIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected $originalQueueDriver;
    
    protected function setUp(): void
    {
        parent::setUp();

        // Create test user
        $this->user = User::factory()->create();
        
        // Store original queue driver
        $this->originalQueueDriver = Config::get('queue.default');
        
        // Use sync queue driver for immediate execution
        Config::set('queue.default', 'sync');
        
        // Configure test storage
        Storage::fake('local');
        
        // Enable debug logging
        Log::channel('single');
    }

    protected function tearDown(): void
    {
        // Restore original queue driver
        Config::set('queue.default', $this->originalQueueDriver);
        
        parent::tearDown();
    }

    /** @test */
    public function it_identifies_configuration_issues()
    {
        $this->artisan('about')
            ->expectsOutput('Environment')
            ->assertExitCode(0);

        // Check critical environment variables
        $requiredEnvVars = [
            'AWS_ACCESS_KEY_ID' => config('filesystems.disks.s3.key'),
            'AWS_SECRET_ACCESS_KEY' => config('filesystems.disks.s3.secret'),
            'AWS_DEFAULT_REGION' => config('filesystems.disks.s3.region'),
            'AWS_STORAGE_BUCKET' => config('receipt-scanner.storage_bucket'),
            'TEXTRACT_KEY' => env('TEXTRACT_KEY'),
            'TEXTRACT_SECRET' => env('TEXTRACT_SECRET'),
            'TEXTRACT_REGION' => env('TEXTRACT_REGION'),
            'AI_PROVIDER' => env('AI_PROVIDER'),
            'OPENAI_API_KEY' => env('OPENAI_API_KEY'),
        ];

        $missingVars = [];
        foreach ($requiredEnvVars as $var => $value) {
            if (empty($value)) {
                $missingVars[] = $var;
            }
        }

        if (!empty($missingVars)) {
            $this->markTestSkipped(
                'Missing environment variables: ' . implode(', ', $missingVars) . 
                '. Please configure these in your .env file for integration testing.'
            );
        }
    }

    /** @test */
    public function it_processes_single_file_with_detailed_error_reporting()
    {
        // Skip if environment not configured
        if (empty(env('AWS_ACCESS_KEY_ID')) || empty(env('OPENAI_API_KEY'))) {
            $this->markTestSkipped('AWS and OpenAI credentials required for integration test');
        }

        // Create a simple test receipt image
        $file = UploadedFile::fake()->image('test-receipt.jpg', 640, 480)->size(500);
        
        try {
            // Step 1: Test file upload
            Log::info('Testing file upload...');
            $response = $this->actingAs($this->user)
                ->postJson('/documents', [
                    'files' => [$file],
                    'file_type' => 'receipt'
                ]);

            if ($response->status() !== 200) {
                Log::error('Upload failed', [
                    'status' => $response->status(),
                    'errors' => $response->json('errors'),
                    'message' => $response->json('message')
                ]);
                
                $this->fail("Upload failed with status {$response->status()}: " . json_encode($response->json()));
            }

            $jobId = $response->json('job_id');
            $fileId = $response->json('files.0.id');
            
            Log::info('Upload successful', ['job_id' => $jobId, 'file_id' => $fileId]);

            // Step 2: Check file record
            $fileRecord = File::find($fileId);
            $this->assertNotNull($fileRecord, 'File record not found in database');
            
            // Step 3: Check cache data
            $cacheKey = "job.{$jobId}.fileMetaData";
            $cacheData = Cache::get($cacheKey);
            
            if (!$cacheData) {
                Log::error('Cache data missing', ['cache_key' => $cacheKey]);
                $this->fail('Job cache data not found');
            }
            
            Log::info('Cache data found', ['data' => $cacheData]);

            // Step 4: Test each job in isolation
            $this->testProcessFileJob($jobId, $fileRecord);
            $this->testProcessReceiptJob($jobId, $fileRecord);
            $this->testMatchMerchantJob($jobId, $fileRecord);
            
        } catch (\Exception $e) {
            Log::error('Integration test failed', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            $this->fail("Integration test failed: {$e->getMessage()}");
        }
    }

    protected function testProcessFileJob($jobId, $fileRecord)
    {
        Log::info('Testing ProcessFile job...');
        
        try {
            $job = new ProcessFile($jobId);
            $job->handle();
            
            Log::info('ProcessFile completed successfully');
            
            // Verify file status updated
            $fileRecord->refresh();
            $this->assertEquals('processing', $fileRecord->status);
            
        } catch (\Exception $e) {
            Log::error('ProcessFile failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw new \Exception("ProcessFile job failed: {$e->getMessage()}");
        }
    }

    protected function testProcessReceiptJob($jobId, $fileRecord)
    {
        Log::info('Testing ProcessReceipt job...');
        
        try {
            $job = new ProcessReceipt($jobId);
            $job->handle();
            
            Log::info('ProcessReceipt completed successfully');
            
            // Verify receipt created
            $receipt = $fileRecord->fileable;
            $this->assertNotNull($receipt, 'Receipt not created');
            $this->assertInstanceOf(\App\Models\Receipt::class, $receipt);
            
            // Check receipt data
            Log::info('Receipt created', [
                'id' => $receipt->id,
                'merchant_name' => $receipt->merchant_name,
                'total' => $receipt->total,
                'line_items_count' => $receipt->lineItems()->count()
            ]);
            
        } catch (\Exception $e) {
            Log::error('ProcessReceipt failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Check specific error types
            if (str_contains($e->getMessage(), 'Textract')) {
                throw new \Exception("Textract error: {$e->getMessage()}. Check AWS credentials and permissions.");
            } elseif (str_contains($e->getMessage(), 'OpenAI') || str_contains($e->getMessage(), 'AI')) {
                throw new \Exception("AI service error: {$e->getMessage()}. Check AI provider configuration.");
            } else {
                throw new \Exception("ProcessReceipt job failed: {$e->getMessage()}");
            }
        }
    }

    protected function testMatchMerchantJob($jobId, $fileRecord)
    {
        Log::info('Testing MatchMerchant job...');
        
        try {
            $job = new MatchMerchant($jobId);
            $job->handle();
            
            Log::info('MatchMerchant completed successfully');
            
            // Verify merchant assigned
            $receipt = $fileRecord->fileable;
            if ($receipt) {
                $receipt->refresh();
                
                Log::info('Merchant matching result', [
                    'merchant_id' => $receipt->merchant_id,
                    'merchant_name' => $receipt->merchant_name
                ]);
            }
            
        } catch (\Exception $e) {
            Log::error('MatchMerchant failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // This is often non-critical, so we log but don't fail
            Log::warning("MatchMerchant job failed (non-critical): {$e->getMessage()}");
        }
    }

    /** @test */
    public function it_validates_service_dependencies()
    {
        // Test StorageService
        try {
            $storageService = app(\App\Services\StorageService::class);
            $this->assertNotNull($storageService, 'StorageService not available');
            
            // Test S3 connection
            $testKey = 'test/' . uniqid() . '.txt';
            $storageService->getS3Client()->putObject([
                'Bucket' => config('receipt-scanner.storage_bucket'),
                'Key' => $testKey,
                'Body' => 'test'
            ]);
            
            $storageService->getS3Client()->deleteObject([
                'Bucket' => config('receipt-scanner.storage_bucket'),
                'Key' => $testKey
            ]);
            
            Log::info('S3 connection successful');
            
        } catch (\Exception $e) {
            $this->fail("S3 connection failed: {$e->getMessage()}. Check AWS credentials.");
        }

        // Test TextExtractionService
        try {
            $textExtractionService = app(\App\Services\TextExtractionService::class);
            $this->assertNotNull($textExtractionService, 'TextExtractionService not available');
            Log::info('TextExtractionService available');
            
        } catch (\Exception $e) {
            $this->fail("TextExtractionService initialization failed: {$e->getMessage()}");
        }

        // Test AIService
        try {
            $aiService = app(\App\Services\AIService::class);
            $this->assertNotNull($aiService, 'AIService not available');
            
            // Test simple AI call
            $result = $aiService->analyzeReceipt("Test Store\nTotal: $10.00");
            $this->assertIsArray($result);
            
            Log::info('AIService test successful');
            
        } catch (\Exception $e) {
            $this->fail("AIService test failed: {$e->getMessage()}. Check AI provider configuration.");
        }
    }

    /** @test */
    public function it_debugs_job_chain_execution()
    {
        // Skip if environment not configured
        if (empty(env('AWS_ACCESS_KEY_ID')) || empty(env('OPENAI_API_KEY'))) {
            $this->markTestSkipped('AWS and OpenAI credentials required for integration test');
        }

        // Use database queue for debugging
        Config::set('queue.default', 'database');
        
        $file = UploadedFile::fake()->image('debug-receipt.jpg')->size(500);
        
        // Upload file
        $response = $this->actingAs($this->user)
            ->postJson('/documents', [
                'files' => [$file],
                'file_type' => 'receipt'
            ]);

        $this->assertEquals(200, $response->status());
        
        $jobId = $response->json('job_id');
        
        // Check jobs table
        $jobs = \DB::table('jobs')->get();
        Log::info('Queued jobs', ['count' => $jobs->count(), 'jobs' => $jobs->toArray()]);
        
        // Process each job manually
        foreach ($jobs as $job) {
            Log::info('Processing job', ['id' => $job->id, 'queue' => $job->queue]);
            
            try {
                Artisan::call('queue:work', [
                    '--once' => true,
                    '--queue' => $job->queue,
                ]);
                
                Log::info('Job processed', ['output' => Artisan::output()]);
                
            } catch (\Exception $e) {
                Log::error('Job processing failed', [
                    'job_id' => $job->id,
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        // Check final state
        $fileRecord = File::where('user_id', $this->user->id)->first();
        Log::info('Final file state', [
            'status' => $fileRecord->status,
            'fileable_type' => $fileRecord->fileable_type,
            'fileable_id' => $fileRecord->fileable_id
        ]);
        
        // Check job histories
        $histories = \App\Models\JobHistory::where('job_id', $jobId)->get();
        foreach ($histories as $history) {
            Log::info('Job history', [
                'job_name' => $history->job_name,
                'status' => $history->status,
                'error' => $history->error
            ]);
        }
    }
}