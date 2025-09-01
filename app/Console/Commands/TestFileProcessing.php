<?php

namespace App\Console\Commands;

use App\Jobs\Files\ProcessFile;
use App\Jobs\Receipts\ProcessReceipt;
use App\Models\File;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class TestFileProcessing extends Command
{
    protected $signature = 'test:file-processing {--user-id=1} {--mock-services}';

    protected $description = 'Test the complete file processing pipeline';

    public function handle()
    {
        $this->info('🧪 Testing File Processing Pipeline...');
        $this->newLine();

        // Configure for testing - use array cache to avoid Redis issues
        config(['cache.default' => 'array']);
        config(['queue.default' => 'sync']);

        // Check if we have a user
        $userId = $this->option('user-id');
        $user = User::find($userId);

        if (! $user) {
            $this->error("User with ID {$userId} not found!");
            $this->info('Creating a test user...');

            $user = User::create([
                'name' => 'Test User',
                'email' => 'test@example.com',
                'password' => bcrypt('password'),
            ]);

            $this->info("Created user: {$user->email} (ID: {$user->id})");
        } else {
            $this->info("Using user: {$user->email} (ID: {$user->id})");
        }

        // Create a test receipt image
        $this->info('Creating test receipt image...');
        $imagePath = $this->createTestReceiptImage();

        try {
            // Test 1: File Upload and Initial Processing
            $this->info('📤 Testing file upload...');
            $result = $this->testFileUpload($imagePath, $user);

            if (! $result) {
                $this->error('File upload failed!');

                return 1;
            }

            $jobId = $result['job_id'];
            $fileId = $result['file_id'];

            // Test 2: ProcessFile Job
            $this->info('⚙️ Testing ProcessFile job...');
            $this->testProcessFileJob($jobId, $fileId);

            // Test 3: ProcessReceipt Job
            $this->info('🧾 Testing ProcessReceipt job...');
            $this->testProcessReceiptJob($jobId, $fileId);

            // Test 4: Check Results
            $this->info('📊 Checking results...');
            $this->checkResults($fileId);

            $this->newLine();
            $this->info('✅ File processing pipeline test completed!');

        } catch (\Exception $e) {
            $this->error('❌ Test failed: '.$e->getMessage());
            $this->line($e->getTraceAsString());

            return 1;
        } finally {
            // Clean up
            @unlink($imagePath);
        }

        return 0;
    }

    protected function createTestReceiptImage(): string
    {
        $image = imagecreatetruecolor(800, 600);
        $bgColor = imagecolorallocate($image, 255, 255, 255);
        $textColor = imagecolorallocate($image, 0, 0, 0);

        imagefill($image, 0, 0, $bgColor);

        // Add receipt-like text
        $y = 50;
        imagestring($image, 5, 100, $y, 'REMA 1000', $textColor);
        $y += 30;
        imagestring($image, 4, 100, $y, 'Storgata 123', $textColor);
        $y += 20;
        imagestring($image, 4, 100, $y, '0123 OSLO', $textColor);
        $y += 20;
        imagestring($image, 4, 100, $y, 'Org.nr: 123456789', $textColor);
        $y += 40;

        imagestring($image, 3, 100, $y, '------------------------', $textColor);
        $y += 20;

        // Add items
        imagestring($image, 4, 100, $y, 'Melk 1L                   25.90', $textColor);
        $y += 20;
        imagestring($image, 4, 100, $y, 'Brod                      32.50', $textColor);
        $y += 20;
        imagestring($image, 4, 100, $y, 'Ost                       89.90', $textColor);
        $y += 20;

        imagestring($image, 3, 100, $y, '------------------------', $textColor);
        $y += 20;

        imagestring($image, 5, 100, $y, 'TOTAL:                   148.30', $textColor);
        $y += 30;

        imagestring($image, 3, 100, $y, 'MVA 25%:                  29.66', $textColor);
        $y += 30;

        imagestring($image, 3, 100, $y, 'Dato: '.date('d.m.Y H:i'), $textColor);
        $y += 20;
        imagestring($image, 3, 100, $y, 'Kvittering: 12345', $textColor);

        $tempPath = storage_path('app/test-receipt-'.uniqid().'.jpg');
        imagejpeg($image, $tempPath, 90);
        imagedestroy($image);

        $this->info("Test receipt created at: {$tempPath}");

        return $tempPath;
    }

    protected function testFileUpload(string $imagePath, User $user): ?array
    {
        try {
            $uploadedFile = new UploadedFile(
                $imagePath,
                'test-receipt.jpg',
                'image/jpeg',
                null,
                true
            );

            // Authenticate as the user for this test
            auth()->login($user);

            $documentService = app(\App\Services\DocumentService::class);

            $result = $documentService->processUpload($uploadedFile, 'receipt');

            $this->info('✅ File upload successful');

            // Check if result has the expected structure
            if (isset($result['job_id']) && isset($result['files'])) {
                $this->table(
                    ['Field', 'Value'],
                    [
                        ['Job ID', $result['job_id']],
                        ['File ID', $result['files'][0]['id']],
                        ['File Name', $result['files'][0]['name']],
                        ['Status', $result['files'][0]['status']],
                    ]
                );

                return [
                    'job_id' => $result['job_id'],
                    'file_id' => $result['files'][0]['id'],
                ];
            } else {
                // Handle different result format
                $this->line('Result: '.json_encode($result));

                // Try to extract job_id and file_id from different formats
                $jobId = $result['job_id'] ?? $result['jobId'] ?? null;
                $fileId = $result['file_id'] ?? $result['fileId'] ??
                         (isset($result['files'][0]['id']) ? $result['files'][0]['id'] : null) ??
                         $result['id'] ?? null;

                if ($jobId && $fileId) {
                    return [
                        'job_id' => $jobId,
                        'file_id' => $fileId,
                    ];
                }

                throw new \Exception('Unexpected result format from processUpload');
            }

        } catch (\Exception $e) {
            $this->error('File upload failed: '.$e->getMessage());

            return null;
        }
    }

    protected function testProcessFileJob(string $jobId, int $fileId): void
    {
        try {
            // Check cache data
            $metadata = Cache::get("job.{$jobId}.fileMetaData");

            if (! $metadata) {
                throw new \Exception('No metadata found in cache');
            }

            $this->info('Cache metadata found:');
            $this->line(json_encode($metadata, JSON_PRETTY_PRINT));

            // Run ProcessFile job
            $job = new ProcessFile($jobId);

            if ($this->option('mock-services')) {
                $this->info('(Using mock services)');
            } else {
                $job->handle();
            }

            $this->info('✅ ProcessFile job completed');

        } catch (\Exception $e) {
            $this->error('ProcessFile job failed: '.$e->getMessage());
            throw $e;
        }
    }

    protected function testProcessReceiptJob(string $jobId, int $fileId): void
    {
        try {
            if ($this->option('mock-services')) {
                $this->info('Skipping ProcessReceipt job (mock mode)');

                return;
            }

            // Run ProcessReceipt job
            $job = new ProcessReceipt($jobId);
            $job->handle();

            $this->info('✅ ProcessReceipt job completed');

            // Check receipt data
            $receiptData = Cache::get("job.{$jobId}.receiptMetaData");
            if ($receiptData) {
                $this->info('Receipt data cached:');
                $this->table(
                    ['Field', 'Value'],
                    [
                        ['Receipt ID', $receiptData['receiptID'] ?? 'N/A'],
                        ['Merchant', $receiptData['merchantName'] ?? 'N/A'],
                        ['Total', $receiptData['total'] ?? 'N/A'],
                    ]
                );
            }

        } catch (\Exception $e) {
            $this->error('ProcessReceipt job failed: '.$e->getMessage());

            // Check specific error types
            if (str_contains($e->getMessage(), 'Textract')) {
                $this->error('💡 Textract error - check AWS credentials and configuration');
            } elseif (str_contains($e->getMessage(), 'AI') || str_contains($e->getMessage(), 'OpenAI')) {
                $this->error('💡 AI service error - check OpenAI API key and configuration');
            }

            throw $e;
        }
    }

    protected function checkResults(int $fileId): void
    {
        // Check file status
        $file = File::find($fileId);
        if (! $file) {
            $this->error('File record not found!');

            return;
        }

        $this->info('File status: '.$file->status);

        // Check if receipt was created
        if ($file->fileable_type === 'App\\Models\\Receipt') {
            $receipt = $file->fileable;
            if ($receipt) {
                $this->info('✅ Receipt created successfully');
                $this->table(
                    ['Field', 'Value'],
                    [
                        ['Receipt ID', $receipt->id],
                        ['Merchant', $receipt->merchant_name ?? 'N/A'],
                        ['Total', $receipt->total],
                        ['Date', $receipt->date],
                        ['Line Items', $receipt->lineItems()->count()],
                    ]
                );
            } else {
                $this->warn('⚠️ Receipt not found');
            }
        } else {
            $this->warn('⚠️ File not linked to receipt');
        }

        // Check job history
        $histories = DB::table('job_histories')
            ->where('job_id', 'like', '%'.substr($file->file_guid, 0, 8).'%')
            ->get();

        if ($histories->count() > 0) {
            $this->info('Job History:');
            $this->table(
                ['Job Name', 'Status', 'Error'],
                $histories->map(function ($h) {
                    return [
                        $h->job_name,
                        $h->status,
                        $h->error ? substr($h->error, 0, 50).'...' : '-',
                    ];
                })->toArray()
            );
        }
    }
}
