<?php

namespace App\Console\Commands;

use App\Models\File;
use App\Models\JobHistory;
use App\Models\User;
use App\Services\AI\AIService;
use App\Services\FileProcessingService;
use App\Services\StorageService;
use App\Services\TextExtractionService;
use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;

class DiagnoseFileProcessing extends Command
{
    protected $signature = 'diagnose:file-processing {--user-id=1} {--test-file=}';

    protected $description = 'Diagnose issues with the file processing pipeline';

    public function handle()
    {
        $this->info('🔍 Diagnosing File Processing Pipeline...');
        $this->newLine();

        // Step 1: Check environment configuration
        $this->checkEnvironmentConfiguration();

        // Step 2: Test service connections
        $this->testServiceConnections();

        // Step 3: Test file upload process
        if ($this->option('test-file')) {
            $this->testFileUpload($this->option('test-file'));
        } else {
            $this->testFileUploadWithFakeFile();
        }

        $this->newLine();
        $this->info('✅ Diagnosis complete!');
    }

    protected function checkEnvironmentConfiguration()
    {
        $this->info('1️⃣ Checking Environment Configuration...');

        $configs = [
            'Queue Driver' => config('queue.default'),
            'Cache Driver' => config('cache.default'),
            'Storage Driver' => config('filesystems.default'),
            'S3 Storage Bucket' => config('filesystems.disks.paperpulse.bucket'),
            'S3 Incoming Bucket' => config('filesystems.disks.pulsedav.bucket'),
            'AI Provider' => env('AI_PROVIDER', 'not set'),
            'Textract Region' => env('TEXTRACT_REGION', 'not set'),
        ];

        $this->table(['Configuration', 'Value'], collect($configs)->map(function ($value, $key) {
            return [$key, $value ?: '<fg=red>not configured</>'];
        })->toArray());

        // Check required environment variables
        $required = [
            'AWS_ACCESS_KEY_ID',
            'AWS_SECRET_ACCESS_KEY',
            'AWS_DEFAULT_REGION',
            'TEXTRACT_KEY',
            'TEXTRACT_SECRET',
            'OPENAI_API_KEY',
        ];

        $missing = [];
        foreach ($required as $var) {
            if (empty(env($var))) {
                $missing[] = $var;
            }
        }

        if (! empty($missing)) {
            $this->error('❌ Missing required environment variables:');
            foreach ($missing as $var) {
                $this->line("   - {$var}");
            }
        } else {
            $this->info('✅ All required environment variables are set');
        }

        $this->newLine();
    }

    protected function testServiceConnections()
    {
        $this->info('2️⃣ Testing Service Connections...');

        // Test S3 Connection
        $this->line('Testing S3 connection...');
        try {
            $storageService = app(StorageService::class);

            // Test if we can write a test file
            $testContent = 'test-'.uniqid();
            $testPath = $storageService->storeFile(
                $testContent,
                1, // test user ID
                'test-'.uniqid(),
                'receipt',
                'test',
                'txt'
            );

            // Try to read it back
            $readContent = $storageService->getFile($testPath);

            if ($readContent === $testContent) {
                $this->info('   ✅ S3 connection successful');
                // Clean up
                $storageService->deleteFile($testPath);
            } else {
                $this->error('   ❌ S3 read/write test failed');
            }
        } catch (\Exception $e) {
            $this->error('   ❌ S3 connection failed: '.$e->getMessage());
        }

        // Test Textract Connection
        $this->line('Testing AWS Textract connection...');
        try {
            $textExtractionService = app(TextExtractionService::class);

            // Check if service was initialized properly
            if ($textExtractionService) {
                // Try to check configuration
                $textractKey = env('TEXTRACT_KEY');
                $textractRegion = env('TEXTRACT_REGION');

                if (! empty($textractKey) && ! empty($textractRegion)) {
                    $this->info('   ✅ Textract configured with region: '.$textractRegion);
                } else {
                    $this->error('   ❌ Textract configuration missing');
                }
            } else {
                $this->error('   ❌ Textract service initialization failed');
            }
        } catch (\Exception $e) {
            $this->error('   ❌ Textract connection failed: '.$e->getMessage());
        }

        // Test AI Service
        $this->line('Testing AI Service...');
        try {
            $aiService = app(AIService::class);
            $result = $aiService->analyzeReceipt("Test Store\nTotal: $10.00");

            if (is_array($result) && isset($result['success'])) {
                if ($result['success']) {
                    $this->info('   ✅ AI Service working (Provider: '.($result['provider'] ?? 'unknown').')');
                } else {
                    $this->error('   ❌ AI Service analysis failed: '.($result['error'] ?? 'Unknown error'));
                }
            } else {
                $this->error('   ❌ AI Service returned unexpected format');
                $this->line('     Response: '.json_encode($result));
            }
        } catch (\Exception $e) {
            $this->error('   ❌ AI Service failed: '.$e->getMessage());
        }

        // Test Redis/Cache
        $this->line('Testing Redis/Cache connection...');
        try {
            // Check if Redis extension is loaded
            if (! extension_loaded('redis')) {
                $this->warn('   ⚠️ PHP Redis extension not installed - using fallback');
            }

            Cache::put('diagnose-test', 'value', 60);
            $value = Cache::get('diagnose-test');
            Cache::forget('diagnose-test');

            if ($value === 'value') {
                $this->info('   ✅ Cache connection successful');
            } else {
                $this->error('   ❌ Cache test failed');
            }
        } catch (\Exception $e) {
            $this->error('   ❌ Cache connection failed: '.$e->getMessage());
            if (str_contains($e->getMessage(), 'Redis')) {
                $this->line('     💡 Install PHP Redis extension: pecl install redis');
            }
        }

        $this->newLine();
    }

    protected function testFileUpload($filePath)
    {
        $this->info('3️⃣ Testing File Upload Process...');

        if (! file_exists($filePath)) {
            $this->error("File not found: {$filePath}");

            return;
        }

        $user = User::find($this->option('user-id'));
        if (! $user) {
            $this->error('User not found with ID: '.$this->option('user-id'));

            return;
        }

        $this->line("Using file: {$filePath}");
        $this->line("User: {$user->email} (ID: {$user->id})");

        try {
            $uploadedFile = new UploadedFile(
                $filePath,
                basename($filePath),
                mime_content_type($filePath),
                null,
                true
            );

            $this->processFileUpload($uploadedFile, $user);
        } catch (\Exception $e) {
            $this->error('Upload failed: '.$e->getMessage());
            $this->line($e->getTraceAsString());
        }
    }

    protected function testFileUploadWithFakeFile()
    {
        $this->info('3️⃣ Testing File Upload Process with Fake File...');

        $user = User::find($this->option('user-id'));
        if (! $user) {
            $this->error('User not found with ID: '.$this->option('user-id'));

            return;
        }

        $this->line('Creating test image...');
        $this->line("User: {$user->email} (ID: {$user->id})");

        try {
            // Create a simple test image
            $image = imagecreatetruecolor(640, 480);
            $bgColor = imagecolorallocate($image, 255, 255, 255);
            $textColor = imagecolorallocate($image, 0, 0, 0);

            imagefill($image, 0, 0, $bgColor);
            imagestring($image, 5, 50, 50, 'Test Receipt', $textColor);
            imagestring($image, 4, 50, 100, 'Store: Test Store', $textColor);
            imagestring($image, 4, 50, 130, 'Item 1: $10.00', $textColor);
            imagestring($image, 4, 50, 160, 'Item 2: $15.00', $textColor);
            imagestring($image, 4, 50, 200, 'Total: $25.00', $textColor);
            imagestring($image, 3, 50, 250, 'Date: '.date('Y-m-d'), $textColor);

            $tempPath = storage_path('app/test-receipt.jpg');
            imagejpeg($image, $tempPath, 90);
            imagedestroy($image);

            $uploadedFile = new UploadedFile(
                $tempPath,
                'test-receipt.jpg',
                'image/jpeg',
                null,
                true
            );

            $this->processFileUpload($uploadedFile, $user);

            // Clean up
            @unlink($tempPath);

        } catch (\Exception $e) {
            $this->error('Test file creation failed: '.$e->getMessage());
            $this->line($e->getTraceAsString());
        }
    }

    protected function processFileUpload(UploadedFile $file, User $user)
    {
        $this->newLine();
        $this->line('📤 Starting file upload process...');

        try {
            // Step 1: Process upload
            $fileProcessingService = app(FileProcessingService::class);

            $this->line('Processing upload...');
            $result = $fileProcessingService->processUpload(
                $file,
                'receipt',
                $user->id
            );

            $this->info('✅ Upload successful!');
            $this->line("Job ID: {$result['jobId']}");
            $this->line("File ID: {$result['fileId']}");
            $this->line("File GUID: {$result['fileGuid']}");

            // Step 2: Check cache data
            $this->newLine();
            $this->line('🔍 Checking cache data...');

            $cacheKey = "job.{$result['jobId']}.fileMetaData";
            $cacheData = Cache::get($cacheKey);

            if ($cacheData) {
                $this->info('✅ Cache data found');
                $this->line('Cache contents:');
                $this->line(json_encode($cacheData, JSON_PRETTY_PRINT));
            } else {
                $this->error('❌ Cache data not found!');
            }

            // Step 3: Check file record
            $this->newLine();
            $this->line('🔍 Checking database record...');

            $fileRecord = File::find($result['fileId']);
            if ($fileRecord) {
                $this->info('✅ File record found');
                $this->table(
                    ['Field', 'Value'],
                    [
                        ['ID', $fileRecord->id],
                        ['User ID', $fileRecord->user_id],
                        ['Status', $fileRecord->status],
                        ['Original Name', $fileRecord->original_name],
                        ['S3 Path', $fileRecord->s3_path ?? 'not set'],
                    ]
                );
            } else {
                $this->error('❌ File record not found!');
            }

            // Step 4: Check job queue
            $this->newLine();
            $this->line('🔍 Checking job queue...');

            $jobs = \DB::table('jobs')->where('payload', 'like', "%{$result['jobId']}%")->get();

            if ($jobs->count() > 0) {
                $this->info("✅ Found {$jobs->count()} jobs in queue");
                foreach ($jobs as $job) {
                    $payload = json_decode($job->payload, true);
                    $this->line("  - {$payload['displayName']} (attempts: {$job->attempts})");
                }
            } else {
                if (config('queue.default') === 'sync') {
                    $this->warn('⚠️ Using sync queue driver - jobs executed immediately');
                } else {
                    $this->warn('⚠️ No jobs found in queue');
                }
            }

            // Step 5: Check job history
            $this->newLine();
            $this->line('🔍 Checking job history...');

            $histories = JobHistory::where('uuid', $result['jobId'])
                ->orWhere('parent_uuid', $result['jobId'])
                ->get();

            if ($histories->count() > 0) {
                $this->info("✅ Found {$histories->count()} job history records");
                $this->table(
                    ['Job Name', 'Status', 'Error'],
                    $histories->map(function ($h) {
                        return [
                            $h->name,
                            $h->status,
                            $h->exception ? substr($h->exception, 0, 50).'...' : '-',
                        ];
                    })->toArray()
                );
            } else {
                $this->warn('⚠️ No job history records found');
            }

        } catch (\Exception $e) {
            $this->error('❌ Upload process failed: '.$e->getMessage());
            $this->line($e->getTraceAsString());
        }
    }
}
