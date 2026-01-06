<?php

namespace Tests\Unit;

use App\Exceptions\GeminiApiException;
use App\Models\File;
use App\Models\User;
use App\Services\AI\Extractors\Invoice\InvoiceDataNormalizer;
use App\Services\AI\Extractors\Invoice\InvoiceExtractor;
use App\Services\AI\Extractors\Invoice\InvoiceValidator;
use App\Services\AI\Providers\GeminiProvider;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

use function Pest\Laravel\mock;

uses(TestCase::class);
uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->file = File::factory()->create(['user_id' => $this->user->id]);
});

it('extracts invoice data successfully', function () {
    // Mock Gemini provider response
    $mockGeminiResponse = [
        'data' => [
            'vendor_name' => 'Acme Corp AS',
            'vendor_address' => 'Oslo Street 123, Oslo',
            'vendor_vat_number' => '987654321',
            'customer_name' => 'Best Company AS',
            'invoice_number' => 'INV-2025-001',
            'invoice_type' => 'invoice',
            'invoice_date' => '2025-01-15',
            'due_date' => '2025-02-14',
            'line_items' => [
                [
                    'line_number' => 1,
                    'description' => 'Consulting Services',
                    'quantity' => 40,
                    'unit_of_measure' => 'hours',
                    'unit_price' => 1200.00,
                    'tax_rate' => 0.25,
                    'tax_amount' => 12000.00,
                    'total_amount' => 60000.00,
                ],
            ],
            'subtotal' => 48000.00,
            'tax_amount' => 12000.00,
            'total_amount' => 60000.00,
            'payment_status' => 'unpaid',
            'payment_terms' => 'Net 30',
            'currency' => 'NOK',
            'confidence_score' => 0.95,
        ],
    ];

    $mockProvider = mock(GeminiProvider::class)
        ->shouldReceive('analyzeFileByUri')
        ->once()
        ->andReturn($mockGeminiResponse)
        ->getMock();

    $extractor = new InvoiceExtractor(
        $mockProvider,
        new InvoiceValidator,
        new InvoiceDataNormalizer
    );

    $result = $extractor->extract('files/test-uri', $this->file);

    expect($result)->toHaveKeys(['type', 'confidence_score', 'data', 'validation_warnings'])
        ->and($result['type'])->toBe('invoice')
        ->and($result['confidence_score'])->toBe(0.95)
        ->and($result['data'])->toHaveKeys(['vendor', 'customer', 'invoice_info', 'line_items', 'totals', 'payment'])
        ->and($result['data']['vendor']['name'])->toBe('Acme Corp AS')
        ->and($result['data']['invoice_info']['invoice_number'])->toBe('INV-2025-001')
        ->and($result['data']['line_items'])->toHaveCount(1)
        ->and($result['data']['totals']['total_amount'])->toBe(60000.00);
});

it('validates required fields', function () {
    $validator = new InvoiceValidator;

    // Missing required fields
    $invalidData = [
        'customer_name' => 'Test Customer',
    ];

    $result = $validator->validate($invalidData);

    expect($result['valid'])->toBeFalse()
        ->and($result['errors'])->toContain('Missing vendor name')
        ->and($result['errors'])->toContain('Missing invoice number')
        ->and($result['errors'])->toContain('Missing invoice date')
        ->and($result['errors'])->toContain('Missing total amount');
});

it('validates date formats', function () {
    $validator = new InvoiceValidator;

    $dataWithInvalidDates = [
        'vendor_name' => 'Acme Corp',
        'invoice_number' => 'INV-001',
        'invoice_date' => '15/01/2025', // Invalid format
        'due_date' => '2025-02-30', // Invalid format (still matches regex but would fail date validation)
        'total_amount' => 1000,
    ];

    $result = $validator->validate($dataWithInvalidDates);

    expect($result['valid'])->toBeTrue() // Still valid as required fields are present
        ->and($result['warnings'])->toContain('Invoice date not in YYYY-MM-DD format');
});

it('normalizes flat data to nested structure', function () {
    $normalizer = new InvoiceDataNormalizer;

    $flatData = [
        'vendor_name' => 'Acme Corp AS',
        'vendor_address' => 'Oslo Street 123',
        'vendor_vat_number' => '987654321',
        'customer_name' => 'Best Company AS',
        'invoice_number' => 'INV-2025-001',
        'invoice_date' => '2025-01-15',
        'due_date' => '2025-02-14',
        'line_items' => [
            [
                'description' => 'Service',
                'total_amount' => 1000,
            ],
        ],
        'total_amount' => 1000,
        'currency' => 'NOK',
        'confidence_score' => 0.9,
    ];

    $normalized = $normalizer->normalize($flatData);

    expect($normalized)->toHaveKeys(['vendor', 'customer', 'invoice_info', 'line_items', 'totals', 'payment', 'metadata'])
        ->and($normalized['vendor'])->toBe([
            'name' => 'Acme Corp AS',
            'address' => 'Oslo Street 123',
            'vat_number' => '987654321',
        ])
        ->and($normalized['customer'])->toBe([
            'name' => 'Best Company AS',
        ])
        ->and($normalized['invoice_info'])->toHaveKey('invoice_number', 'INV-2025-001')
        ->and($normalized['totals']['total_amount'])->toBe(1000)
        ->and($normalized['payment']['currency'])->toBe('NOK')
        ->and($normalized['metadata']['confidence_score'])->toBe(0.9);
});

it('throws exception on validation failure', function () {
    $mockGeminiResponse = [
        'data' => [
            // Missing required fields
            'customer_name' => 'Test Customer',
        ],
    ];

    $mockProvider = mock(GeminiProvider::class)
        ->shouldReceive('analyzeFileByUri')
        ->once()
        ->andReturn($mockGeminiResponse)
        ->getMock();

    $extractor = new InvoiceExtractor(
        $mockProvider,
        new InvoiceValidator,
        new InvoiceDataNormalizer
    );

    $extractor->extract('files/test-uri', $this->file);
})->throws(Exception::class, 'Invoice validation failed');

it('handles gemini api exceptions', function () {
    $mockProvider = mock(GeminiProvider::class)
        ->shouldReceive('analyzeFileByUri')
        ->once()
        ->andThrow(new GeminiApiException('API error'))
        ->getMock();

    $extractor = new InvoiceExtractor(
        $mockProvider,
        new InvoiceValidator,
        new InvoiceDataNormalizer
    );

    $extractor->extract('files/test-uri', $this->file);
})->throws(GeminiApiException::class, 'API error');

it('returns correct schema', function () {
    $extractor = new InvoiceExtractor(
        mock(GeminiProvider::class),
        new InvoiceValidator,
        new InvoiceDataNormalizer
    );

    $schema = $extractor->getSchema();

    expect($schema)->toHaveKeys(['name', 'responseSchema'])
        ->and($schema['name'])->toBe('invoice_extraction')
        ->and($schema['responseSchema']['properties'])->toHaveKeys([
            'vendor_name',
            'invoice_number',
            'invoice_date',
            'total_amount',
            'line_items',
        ])
        ->and($schema['responseSchema']['required'])->toContain('vendor_name')
        ->and($schema['responseSchema']['required'])->toContain('invoice_number')
        ->and($schema['responseSchema']['required'])->toContain('invoice_date')
        ->and($schema['responseSchema']['required'])->toContain('total_amount');
});

it('returns extraction prompt', function () {
    $extractor = new InvoiceExtractor(
        mock(GeminiProvider::class),
        new InvoiceValidator,
        new InvoiceDataNormalizer
    );

    $prompt = $extractor->getPrompt();

    expect($prompt)->toBeString()
        ->and($prompt)->toContain('Extract all invoice information')
        ->and($prompt)->toContain('Vendor details')
        ->and($prompt)->toContain('line items')
        ->and($prompt)->toContain('YYYY-MM-DD');
});

it('validates line items', function () {
    $validator = new InvoiceValidator;

    $dataWithInvalidLineItems = [
        'vendor_name' => 'Acme Corp',
        'invoice_number' => 'INV-001',
        'invoice_date' => '2025-01-15',
        'total_amount' => 1000,
        'line_items' => [
            [
                // Missing description and total_amount
                'quantity' => 1,
            ],
            [
                'description' => 'Valid Item',
                'total_amount' => 'not-a-number', // Invalid total
            ],
        ],
    ];

    $result = $validator->validate($dataWithInvalidLineItems);

    expect($result['valid'])->toBeTrue() // Required fields are present
        ->and($result['warnings'])->toContain('Line item 0: missing description')
        ->and($result['warnings'])->toContain('Line item 0: invalid or missing total amount')
        ->and($result['warnings'])->toContain('Line item 1: invalid or missing total amount');
});

it('warns on low confidence score', function () {
    $validator = new InvoiceValidator;

    $dataWithLowConfidence = [
        'vendor_name' => 'Acme Corp',
        'invoice_number' => 'INV-001',
        'invoice_date' => '2025-01-15',
        'total_amount' => 1000,
        'confidence_score' => 0.3,
    ];

    $result = $validator->validate($dataWithLowConfidence);

    expect($result['valid'])->toBeTrue()
        ->and($result['warnings'])->toContain('Low confidence score: 0.3');
});
