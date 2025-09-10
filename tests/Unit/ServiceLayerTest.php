<?php

namespace Tests\Unit;

use App\Contracts\Services\FileMetadataContract;
use App\Contracts\Services\FileStorageContract;
use App\Contracts\Services\FileValidationContract;
use App\Contracts\Services\PulseDavFileContract;
use App\Contracts\Services\PulseDavFolderContract;
use App\Contracts\Services\PulseDavImportContract;
use App\Contracts\Services\PulseDavSyncContract;
use App\Contracts\Services\ReceiptEnricherContract;
use App\Contracts\Services\ReceiptParserContract;
use App\Contracts\Services\ReceiptValidatorContract;
use Tests\TestCase;

class ServiceLayerTest extends TestCase
{
    /**
     * Test that all service contracts can be resolved
     */
    public function test_service_contracts_can_be_resolved()
    {
        // File Service Contracts
        $this->assertInstanceOf(
            FileValidationContract::class,
            $this->app->make(FileValidationContract::class)
        );

        $this->assertInstanceOf(
            FileStorageContract::class,
            $this->app->make(FileStorageContract::class)
        );

        $this->assertInstanceOf(
            FileMetadataContract::class,
            $this->app->make(FileMetadataContract::class)
        );

        // Receipt Service Contracts
        $this->assertInstanceOf(
            ReceiptParserContract::class,
            $this->app->make(ReceiptParserContract::class)
        );

        $this->assertInstanceOf(
            ReceiptValidatorContract::class,
            $this->app->make(ReceiptValidatorContract::class)
        );

        $this->assertInstanceOf(
            ReceiptEnricherContract::class,
            $this->app->make(ReceiptEnricherContract::class)
        );

        // PulseDav Service Contracts
        $this->assertInstanceOf(
            PulseDavSyncContract::class,
            $this->app->make(PulseDavSyncContract::class)
        );

        $this->assertInstanceOf(
            PulseDavFileContract::class,
            $this->app->make(PulseDavFileContract::class)
        );

        $this->assertInstanceOf(
            PulseDavFolderContract::class,
            $this->app->make(PulseDavFolderContract::class)
        );

        $this->assertInstanceOf(
            PulseDavImportContract::class,
            $this->app->make(PulseDavImportContract::class)
        );
    }

    /**
     * Test FileValidationService basic functionality
     */
    public function test_file_validation_service_basic_functionality()
    {
        $validator = $this->app->make(FileValidationContract::class);

        // Test file type support
        $this->assertTrue($validator->isSupported('pdf', 'receipt'));
        $this->assertTrue($validator->isSupported('jpg', 'receipt'));
        $this->assertFalse($validator->isSupported('exe', 'receipt'));

        // Test MIME type detection
        $this->assertEquals('application/pdf', $validator->getMimeType('pdf'));
        $this->assertEquals('image/jpeg', $validator->getMimeType('jpg'));

        // Test max file size
        $maxSize = $validator->getMaxFileSize('receipt');
        $this->assertIsInt($maxSize);
        $this->assertGreaterThan(0, $maxSize);
    }

    /**
     * Test FileStorageService basic functionality
     */
    public function test_file_storage_service_basic_functionality()
    {
        $storage = $this->app->make(FileStorageContract::class);

        // Test GUID generation
        $guid1 = $storage->generateFileGuid();
        $guid2 = $storage->generateFileGuid();

        $this->assertIsString($guid1);
        $this->assertIsString($guid2);
        $this->assertNotEquals($guid1, $guid2);

        // Test GUID format (should be UUID)
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            $guid1
        );
    }

    /**
     * Test FileMetadataService basic functionality
     */
    public function test_file_metadata_service_basic_functionality()
    {
        $metadata = $this->app->make(FileMetadataContract::class);

        // Test job name generation
        $jobName1 = $metadata->generateJobName();
        $jobName2 = $metadata->generateJobName();

        $this->assertIsString($jobName1);
        $this->assertIsString($jobName2);
        $this->assertNotEquals($jobName1, $jobName2);

        // Test job name format (should contain hyphen and be reasonable length)
        $this->assertStringContainsString('-', $jobName1);
        $this->assertGreaterThan(10, strlen($jobName1));
        $this->assertLessThan(50, strlen($jobName1));
    }

    /**
     * Test ReceiptValidatorService basic functionality
     */
    public function test_receipt_validator_service_basic_functionality()
    {
        $validator = $this->app->make(ReceiptValidatorContract::class);

        // Test essential data validation
        $dataWithMerchant = ['merchant' => ['name' => 'Test Store']];
        $this->assertTrue($validator->hasEssentialData($dataWithMerchant));

        $dataWithStore = ['store' => ['name' => 'Test Store']];
        $this->assertTrue($validator->hasEssentialData($dataWithStore));

        $dataWithoutMerchant = ['items' => []];
        $this->assertFalse($validator->hasEssentialData($dataWithoutMerchant));

        // Test data sanitization
        $dirtyData = ['merchant' => ['name' => '  Test   Store  ']];
        $cleanData = $validator->sanitizeData($dirtyData);
        $this->assertEquals('Test Store', $cleanData['merchant']['name']);
    }

    /**
     * Test ReceiptEnricherService basic functionality
     */
    public function test_receipt_enricher_service_basic_functionality()
    {
        $enricher = $this->app->make(ReceiptEnricherContract::class);

        // Test that suggested_category comes from AI data
        $input = [
            'merchant' => ['name' => 'Test Store', 'category' => 'Groceries'],
            'items' => [['name' => 'Item 1'], ['name' => 'Item 2']],
            'totals' => ['total_amount' => 100.50],
        ];

        $enriched = $enricher->enrichReceiptData($input);
        $this->assertEquals('Groceries', $enriched['suggested_category'] ?? null);

        // Test description generation
        $description = $enricher->generateEnhancedDescription($input, 'NOK', $input['merchant']['category']);
        $this->assertStringContainsString('Groceries', $description);
        $this->assertStringContainsString('Test Store', $description);
        $this->assertStringContainsString('100.50', $description);
    }
}
