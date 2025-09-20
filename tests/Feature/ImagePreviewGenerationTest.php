<?php

namespace Tests\Feature;

use App\Models\File;
use App\Models\User;
use App\Services\Files\FilePreviewManager;
use App\Services\Files\ImagePreviewGenerator;
use App\Services\Files\StoragePathBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ImagePreviewGenerationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected File $file;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('paperpulse');
        Storage::fake('pulsedav');

        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    /**
     * Test that preview path generation works correctly.
     */
    public function test_preview_path_generation()
    {
        $guid = Str::uuid()->toString();
        $userId = 123;

        $path = StoragePathBuilder::previewPath($userId, $guid, 'receipt');

        $this->assertEquals("receipts/{$userId}/{$guid}/preview.jpg", $path);
    }

    /**
     * Test that File model accepts new preview fields.
     */
    public function test_file_model_accepts_preview_fields()
    {
        $file = File::create([
            'user_id' => $this->user->id,
            'fileName' => 'test.pdf',
            'fileExtension' => 'pdf',
            'fileType' => 'application/pdf',
            'fileSize' => 1024,
            'guid' => Str::uuid()->toString(),
            'file_type' => 'receipt',
            'status' => 'pending',
            'uploaded_at' => now(),
            's3_original_path' => 'receipts/1/test/original.pdf',
            's3_image_path' => 'receipts/1/test/preview.jpg',
            'has_image_preview' => true,
            'image_generation_error' => null,
        ]);

        $this->assertNotNull($file);
        $this->assertTrue($file->has_image_preview);
        $this->assertEquals('receipts/1/test/preview.jpg', $file->s3_image_path);
        $this->assertNull($file->image_generation_error);
    }

    /**
     * Test that preview manager handles non-PDF files correctly.
     */
    public function test_preview_manager_skips_non_pdf_files()
    {
        $file = File::create([
            'user_id' => $this->user->id,
            'fileName' => 'test.jpg',
            'fileExtension' => 'jpg',
            'fileType' => 'image/jpeg',
            'fileSize' => 1024,
            'guid' => Str::uuid()->toString(),
            'file_type' => 'receipt',
            'status' => 'pending',
            'uploaded_at' => now(),
        ]);

        $manager = app(FilePreviewManager::class);
        $result = $manager->generatePreviewForFile($file, '/fake/path.jpg');

        $this->assertFalse($result);
        $file->refresh();
        $this->assertFalse($file->has_image_preview);
    }

    /**
     * Test that preview manager handles missing files gracefully.
     */
    public function test_preview_manager_handles_missing_files()
    {
        $file = File::create([
            'user_id' => $this->user->id,
            'fileName' => 'test.pdf',
            'fileExtension' => 'pdf',
            'fileType' => 'application/pdf',
            'fileSize' => 1024,
            'guid' => Str::uuid()->toString(),
            'file_type' => 'receipt',
            'status' => 'pending',
            'uploaded_at' => now(),
        ]);

        $manager = app(FilePreviewManager::class);
        $result = $manager->generatePreviewForFile($file, '/non/existent/file.pdf');

        $this->assertFalse($result);
        $this->assertFalse($file->has_image_preview);
        $this->assertNotNull($file->image_generation_error);
    }

    /**
     * Test that receipt transformer includes preview information.
     */
    public function test_receipt_transformer_includes_preview_info()
    {
        $file = File::create([
            'user_id' => $this->user->id,
            'fileName' => 'test.pdf',
            'fileExtension' => 'pdf',
            'fileType' => 'application/pdf',
            'fileSize' => 1024,
            'guid' => Str::uuid()->toString(),
            'file_type' => 'receipt',
            'status' => 'completed',
            'uploaded_at' => now(),
            's3_image_path' => 'receipts/1/test/preview.jpg',
            'has_image_preview' => true,
        ]);

        $receipt = \App\Models\Receipt::create([
            'user_id' => $this->user->id,
            'file_id' => $file->id,
            'receipt_date' => now(),
            'total_amount' => 100.00,
            'currency' => 'USD',
        ]);

        $receipt->load('file');
        $transformed = \App\Services\Receipts\ReceiptTransformer::forShow($receipt);

        $this->assertArrayHasKey('file', $transformed);
        $this->assertTrue($transformed['file']['has_preview']);
        $this->assertTrue($transformed['file']['is_pdf']);
    }
}