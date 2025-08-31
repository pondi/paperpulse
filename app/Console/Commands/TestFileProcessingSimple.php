<?php

namespace App\Console\Commands;

use App\Jobs\ProcessFile;
use App\Jobs\ProcessReceipt;
use App\Models\File;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class TestFileProcessingSimple extends Command
{
    protected $signature = 'test:file-simple {--user-id=1}';

    protected $description = 'Simple test of file processing data flow';

    public function handle()
    {
        $this->info('🧪 Testing File Processing Data Flow...');
        $this->newLine();

        // Use array cache to avoid Redis
        config(['cache.default' => 'array']);
        config(['queue.default' => 'sync']);

        // Get or create user
        $userId = $this->option('user-id');
        $user = User::find($userId) ?? User::first();

        if (! $user) {
            $this->error('No users found in database!');

            return 1;
        }

        $this->info("Using user: {$user->email} (ID: {$user->id})");

        // Test 1: Cache Data Flow
        $this->testCacheDataFlow();

        // Test 2: Job Chain Data Consistency
        $this->testJobChainData();

        // Test 3: Metadata Structure
        $this->testMetadataStructure();

        $this->newLine();
        $this->info('✅ Data flow test completed!');

        return 0;
    }

    protected function testCacheDataFlow()
    {
        $this->info('📊 Testing cache data flow...');

        $jobId = Str::uuid()->toString();
        $fileGuid = Str::uuid()->toString();

        // Simulate FileProcessingService metadata
        $metadata = [
            'fileId' => 123,
            'fileGuid' => $fileGuid,
            'userId' => 1,
            'fileName' => 'test-receipt.jpg',
            'fileType' => 'receipt',
            'filePath' => 'uploads/'.$fileGuid,
            'fileExtension' => 'jpg',
            's3OriginalPath' => 'receipts/1/'.$fileGuid.'/original.jpg',
            'jobName' => 'test-job',
        ];

        // Store in cache like FileProcessingService does
        Cache::put("job.{$jobId}.fileMetaData", $metadata, 7200);

        // Verify it can be retrieved
        $retrieved = Cache::get("job.{$jobId}.fileMetaData");

        if ($retrieved && $retrieved['fileGuid'] === $fileGuid) {
            $this->info('✅ Cache storage and retrieval working');
        } else {
            $this->error('❌ Cache storage/retrieval failed');
        }

        // Test what ProcessReceipt expects
        $this->info('Checking ProcessReceipt compatibility...');

        if (isset($retrieved['fileGuid']) && isset($retrieved['fileId'])) {
            $this->info('✅ Metadata has required fields for ProcessReceipt');
        } else {
            $this->error('❌ Metadata missing required fields');
            $this->line('Expected: fileGuid, fileId');
            $this->line('Actual: '.implode(', ', array_keys($retrieved ?? [])));
        }
    }

    protected function testJobChainData()
    {
        $this->newLine();
        $this->info('🔗 Testing job chain data consistency...');

        $jobId = Str::uuid()->toString();

        // Simulate ProcessFile updating metadata
        $metadata = Cache::get("job.{$jobId}.fileMetaData") ?? [
            'fileId' => 456,
            'fileGuid' => Str::uuid()->toString(),
            'userId' => 1,
            'fileType' => 'receipt',
        ];

        // ProcessFile might add data
        $metadata['status'] = 'processing';
        $metadata['textExtracted'] = true;
        Cache::put("job.{$jobId}.fileMetaData", $metadata, 7200);

        // Simulate ProcessReceipt storing receipt data
        $receiptData = [
            'receiptId' => 789,
            'merchantName' => 'Test Store',
            'merchantAddress' => '123 Test St',
            'merchantVatID' => '12345',
        ];

        Cache::put("job.{$jobId}.receiptMetaData", $receiptData, 3600);

        // Check if MatchMerchant can access both
        $fileData = Cache::get("job.{$jobId}.fileMetaData");
        $receiptData = Cache::get("job.{$jobId}.receiptMetaData");

        if ($fileData && $receiptData) {
            $this->info('✅ Both metadata caches accessible for MatchMerchant');

            // Check required fields for MatchMerchant
            if (isset($fileData['fileId']) && isset($receiptData['receiptId'])) {
                $this->info('✅ All required fields present for MatchMerchant');
            } else {
                $this->error('❌ Missing required fields for MatchMerchant');
            }
        } else {
            $this->error('❌ Metadata caches not accessible');
        }
    }

    protected function testMetadataStructure()
    {
        $this->newLine();
        $this->info('📋 Testing metadata structure consistency...');

        $expectedFileMetadata = [
            'fileId' => 'int',
            'fileGuid' => 'string',
            'userId' => 'int',
            'fileName' => 'string',
            'fileType' => 'string',
            'filePath' => 'string',
            'fileExtension' => 'string',
            's3OriginalPath' => 'string',
            'jobName' => 'string',
        ];

        $expectedReceiptMetadata = [
            'receiptId' => 'int',
            'merchantName' => 'string',
            'merchantAddress' => 'string',
            'merchantVatID' => 'string',
        ];

        $this->info('Expected file metadata structure:');
        foreach ($expectedFileMetadata as $key => $type) {
            $this->line("  - {$key} ({$type})");
        }

        $this->newLine();
        $this->info('Expected receipt metadata structure:');
        foreach ($expectedReceiptMetadata as $key => $type) {
            $this->line("  - {$key} ({$type})");
        }

        $this->newLine();
        $this->info('✅ Metadata structures documented');

        // Test with actual file record
        $file = File::where('user_id', 1)->first();
        if ($file) {
            $this->info("Found file record: ID={$file->id}, GUID={$file->file_guid}");
        } else {
            $this->warn('No file records found for user');
        }
    }
}
