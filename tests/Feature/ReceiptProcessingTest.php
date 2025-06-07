<?php

namespace Tests\Feature;

use App\Jobs\ProcessFile;
use App\Jobs\ProcessReceipt;
use App\Jobs\MatchMerchant;
use App\Jobs\DeleteWorkingFiles;
use App\Models\File;
use App\Models\User;
use App\Models\Receipt;
use App\Models\Merchant;
use App\Models\LineItem;
use App\Services\ConversionService;
use App\Services\ReceiptService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class ReceiptProcessingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Cache::flush();
    }

    /**
     * Test the complete receipt processing pipeline
     */
    public function test_receipt_processing_pipeline_for_image_file()
    {
        Bus::fake();
        
        $user = User::factory()->create();
        $this->actingAs($user);
        
        // Create a fake image file
        $file = UploadedFile::fake()->image('receipt.jpg', 600, 800);
        
        // Upload the file
        $response = $this->post(route('files.store'), [
            'file' => $file
        ]);
        
        $response->assertStatus(200);
        $response->assertJson(['message' => 'File uploaded successfully']);
        
        // Verify file was created in database
        $fileModel = File::where('user_id', $user->id)->first();
        $this->assertNotNull($fileModel);
        $this->assertEquals('receipt.jpg', $fileModel->filename);
        $this->assertEquals('image/jpeg', $fileModel->mime_type);
        
        // Verify job chain was dispatched
        Bus::assertChained([
            ProcessFile::class,
            ProcessReceipt::class,
            MatchMerchant::class,
            DeleteWorkingFiles::class,
        ]);
    }

    /**
     * Test the complete receipt processing pipeline for PDF
     */
    public function test_receipt_processing_pipeline_for_pdf_file()
    {
        Bus::fake();
        
        $user = User::factory()->create();
        $this->actingAs($user);
        
        // Create a fake PDF file
        $file = UploadedFile::fake()->create('receipt.pdf', 500, 'application/pdf');
        
        // Upload the file
        $response = $this->post(route('files.store'), [
            'file' => $file
        ]);
        
        $response->assertStatus(200);
        $response->assertJson(['message' => 'File uploaded successfully']);
        
        // Verify file was created in database
        $fileModel = File::where('user_id', $user->id)->first();
        $this->assertNotNull($fileModel);
        $this->assertEquals('receipt.pdf', $fileModel->filename);
        $this->assertEquals('application/pdf', $fileModel->mime_type);
        
        // Verify job chain was dispatched
        Bus::assertChained([
            ProcessFile::class,
            ProcessReceipt::class,
            MatchMerchant::class,
            DeleteWorkingFiles::class,
        ]);
    }

    /**
     * Test ProcessFile job execution
     */
    public function test_process_file_job_handles_image_correctly()
    {
        $user = User::factory()->create();
        $fileModel = File::factory()->create([
            'user_id' => $user->id,
            'filename' => 'receipt.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 1024,
            'guid' => 'test-guid-123'
        ]);
        
        // Set up job metadata
        $jobId = 'job-123';
        Cache::put("job:{$jobId}", [
            'fileGUID' => $fileModel->guid,
            'filePath' => 'uploads/receipt.jpg',
            'fileExtension' => 'jpg',
            'userId' => $user->id
        ], 3600);
        
        // Mock the conversion service
        $conversionService = Mockery::mock(ConversionService::class);
        $conversionService->shouldReceive('imgToBase64')
            ->once()
            ->with('uploads/receipt.jpg')
            ->andReturn('base64encodedimage');
        
        $this->app->instance(ConversionService::class, $conversionService);
        
        // Execute the job
        $job = new ProcessFile($jobId);
        $job->handle();
        
        // Verify the image was converted to base64 and cached
        $cachedData = Cache::get("job:{$jobId}");
        $this->assertEquals('base64encodedimage', $cachedData['image']);
    }

    /**
     * Test ProcessReceipt job execution
     */
    public function test_process_receipt_job_extracts_receipt_data()
    {
        $user = User::factory()->create();
        $fileModel = File::factory()->create([
            'user_id' => $user->id,
            'filename' => 'receipt.jpg',
            'guid' => 'test-guid-123'
        ]);
        
        // Set up job metadata
        $jobId = 'job-123';
        Cache::put("job:{$jobId}", [
            'fileGUID' => $fileModel->guid,
            'filePath' => 'uploads/receipt.jpg',
            'fileExtension' => 'jpg',
            'userId' => $user->id,
            'image' => 'base64encodedimage'
        ], 3600);
        
        // Mock the receipt service
        $receiptService = Mockery::mock(ReceiptService::class);
        $receiptService->shouldReceive('extractText')
            ->once()
            ->with('base64encodedimage')
            ->andReturn('Merchant Name\nItem 1 $10.00\nTotal: $10.00');
            
        $receiptService->shouldReceive('parseReceipt')
            ->once()
            ->andReturn([
                'merchant_name' => 'Test Merchant',
                'date' => '2024-01-01',
                'total_amount' => 10.00,
                'tax_amount' => 0.00,
                'currency' => 'USD',
                'line_items' => [
                    [
                        'description' => 'Item 1',
                        'quantity' => 1,
                        'unit_price' => 10.00,
                        'total' => 10.00
                    ]
                ]
            ]);
        
        $this->app->instance(ReceiptService::class, $receiptService);
        
        // Execute the job
        $job = new ProcessReceipt($jobId);
        $job->handle();
        
        // Verify receipt was created
        $receipt = Receipt::where('file_id', $fileModel->id)->first();
        $this->assertNotNull($receipt);
        $this->assertEquals(10.00, $receipt->total_amount);
        $this->assertEquals('USD', $receipt->currency);
        
        // Verify line items were created
        $lineItems = LineItem::where('receipt_id', $receipt->id)->get();
        $this->assertCount(1, $lineItems);
        $this->assertEquals('Item 1', $lineItems[0]->description);
        $this->assertEquals(10.00, $lineItems[0]->total);
    }

    /**
     * Test MatchMerchant job execution
     */
    public function test_match_merchant_job_creates_or_matches_merchant()
    {
        $user = User::factory()->create();
        $fileModel = File::factory()->create(['user_id' => $user->id]);
        $receipt = Receipt::factory()->create([
            'file_id' => $fileModel->id,
            'user_id' => $user->id,
            'merchant_name' => 'Test Merchant'
        ]);
        
        // Set up job metadata
        $jobId = 'job-123';
        Cache::put("job:{$jobId}", [
            'fileGUID' => $fileModel->guid,
            'userId' => $user->id,
            'receiptId' => $receipt->id
        ], 3600);
        
        // Execute the job
        $job = new MatchMerchant($jobId);
        $job->handle();
        
        // Verify merchant was created
        $merchant = Merchant::where('name', 'Test Merchant')->first();
        $this->assertNotNull($merchant);
        
        // Verify receipt was updated with merchant_id
        $receipt->refresh();
        $this->assertEquals($merchant->id, $receipt->merchant_id);
    }

    /**
     * Test job failure handling
     */
    public function test_job_marks_file_as_failed_on_error()
    {
        $user = User::factory()->create();
        $fileModel = File::factory()->create([
            'user_id' => $user->id,
            'status' => 'processing'
        ]);
        
        // Set up job metadata with missing data
        $jobId = 'job-123';
        Cache::put("job:{$jobId}", [
            'fileGUID' => $fileModel->guid,
            'userId' => $user->id
            // Missing required data
        ], 3600);
        
        // Execute the job and expect it to fail
        try {
            $job = new ProcessFile($jobId);
            $job->handle();
        } catch (\Exception $e) {
            // Expected to fail
        }
        
        // Verify file was marked as failed
        $fileModel->refresh();
        $this->assertEquals('failed', $fileModel->status);
    }

    /**
     * Test file validation
     */
    public function test_upload_rejects_invalid_file_types()
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        
        // Create an invalid file type
        $file = UploadedFile::fake()->create('document.txt', 100, 'text/plain');
        
        // Try to upload the file
        $response = $this->post(route('files.store'), [
            'file' => $file
        ]);
        
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['file']);
    }

    /**
     * Test file size validation
     */
    public function test_upload_rejects_oversized_files()
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        
        // Create a file that exceeds 2MB limit
        $file = UploadedFile::fake()->image('large-receipt.jpg')->size(3000); // 3MB
        
        // Try to upload the file
        $response = $this->post(route('files.store'), [
            'file' => $file
        ]);
        
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['file']);
    }

    /**
     * Test concurrent file processing
     */
    public function test_concurrent_file_processing_maintains_data_integrity()
    {
        Bus::fake();
        
        $user = User::factory()->create();
        $this->actingAs($user);
        
        // Upload multiple files concurrently
        $files = [];
        for ($i = 0; $i < 5; $i++) {
            $files[] = UploadedFile::fake()->image("receipt{$i}.jpg");
        }
        
        foreach ($files as $file) {
            $response = $this->post(route('files.store'), [
                'file' => $file
            ]);
            $response->assertStatus(200);
        }
        
        // Verify all files were created
        $fileCount = File::where('user_id', $user->id)->count();
        $this->assertEquals(5, $fileCount);
        
        // Verify job chains were dispatched for each file
        Bus::assertChainedTimes(function ($chain) {
            return count($chain) === 4 &&
                   $chain[0] instanceof ProcessFile &&
                   $chain[1] instanceof ProcessReceipt &&
                   $chain[2] instanceof MatchMerchant &&
                   $chain[3] instanceof DeleteWorkingFiles;
        }, 5);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}