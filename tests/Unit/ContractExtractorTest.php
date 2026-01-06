<?php

namespace Tests\Unit;

use App\Exceptions\GeminiApiException;
use App\Models\File;
use App\Models\User;
use App\Services\AI\Extractors\Contract\ContractDataNormalizer;
use App\Services\AI\Extractors\Contract\ContractExtractor;
use App\Services\AI\Extractors\Contract\ContractValidator;
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

it('extracts contract data successfully', function () {
    // Mock Gemini provider response
    $mockGeminiResponse = [
        'data' => [
            'contract_number' => 'AGR-2024-001',
            'contract_title' => 'Software License Agreement',
            'contract_type' => 'License',
            'parties' => [
                ['name' => 'TechCorp AS', 'role' => 'licensor', 'contact' => 'legal@techcorp.no', 'registration_number' => '123456789'],
                ['name' => 'ClientCorp AB', 'role' => 'licensee', 'contact' => 'contracts@clientcorp.se'],
            ],
            'effective_date' => '2024-01-15',
            'expiry_date' => '2025-01-14',
            'signature_date' => '2024-01-10',
            'duration' => '12 months',
            'renewal_terms' => 'Automatic renewal unless terminated with 30 days notice',
            'contract_value' => 50000,
            'currency' => 'EUR',
            'payment_schedule' => [
                ['milestone' => 'Upon execution', 'amount' => 25000, 'date' => '2024-01-15'],
                ['milestone' => 'Final payment', 'amount' => 25000, 'date' => '2024-12-15'],
            ],
            'governing_law' => 'Norwegian law',
            'jurisdiction' => 'Oslo District Court',
            'termination_conditions' => 'Either party may terminate with 30 days written notice',
            'key_obligations' => [
                'Licensor to provide technical support',
                'Licensee to pay fees on schedule',
            ],
            'summary' => 'TechCorp grants ClientCorp a 12-month software license with technical support.',
            'status' => 'active',
            'confidence_score' => 0.92,
        ],
    ];

    $mockProvider = mock(GeminiProvider::class)
        ->shouldReceive('analyzeFileByUri')
        ->once()
        ->andReturn($mockGeminiResponse)
        ->getMock();

    $extractor = new ContractExtractor(
        $mockProvider,
        new ContractValidator,
        new ContractDataNormalizer
    );

    $result = $extractor->extract('files/test-uri', $this->file);

    expect($result)->toHaveKeys(['type', 'confidence_score', 'data', 'validation_warnings'])
        ->and($result['type'])->toBe('contract')
        ->and($result['confidence_score'])->toBe(0.92)
        ->and($result['data'])->toHaveKeys(['contract_number', 'contract_title', 'contract_type', 'parties', 'dates', 'terms', 'financial', 'legal', 'key_obligations', 'summary', 'status'])
        ->and($result['data']['contract_title'])->toBe('Software License Agreement')
        ->and($result['data']['parties'])->toHaveCount(2)
        ->and($result['data']['financial']['contract_value'])->toBe(50000);
});

it('validates required fields', function () {
    $validator = new ContractValidator;

    // Missing required fields
    $invalidData = [
        'contract_type' => 'License',
    ];

    $result = $validator->validate($invalidData);

    expect($result['valid'])->toBeFalse()
        ->and($result['errors'])->toContain('Missing contract title')
        ->and($result['errors'])->toContain('Missing effective date')
        ->and($result['errors'])->toContain('Missing contract parties');
});

it('validates date formats', function () {
    $validator = new ContractValidator;

    $dataWithInvalidDates = [
        'contract_title' => 'Test Agreement',
        'effective_date' => '15/01/2024', // Invalid format
        'expiry_date' => '2025-02-30', // Invalid format (still matches regex but would fail date validation)
        'parties' => [
            ['name' => 'Party A'],
            ['name' => 'Party B'],
        ],
    ];

    $result = $validator->validate($dataWithInvalidDates);

    expect($result['valid'])->toBeTrue() // Still valid as required fields are present
        ->and($result['warnings'])->toContain('Effective date not in YYYY-MM-DD format');
});

it('normalizes flat data to nested structure', function () {
    $normalizer = new ContractDataNormalizer;

    $flatData = [
        'contract_number' => 'AGR-2024-001',
        'contract_title' => 'Software License Agreement',
        'contract_type' => 'License',
        'parties' => [
            ['name' => 'TechCorp AS', 'role' => 'licensor'],
            ['name' => 'ClientCorp AB', 'role' => 'licensee'],
        ],
        'effective_date' => '2024-01-15',
        'expiry_date' => '2025-01-14',
        'signature_date' => '2024-01-10',
        'duration' => '12 months',
        'renewal_terms' => 'Automatic renewal',
        'contract_value' => 50000,
        'currency' => 'EUR',
        'payment_schedule' => [
            ['milestone' => 'Upon execution', 'amount' => 25000],
        ],
        'governing_law' => 'Norwegian law',
        'jurisdiction' => 'Oslo District Court',
        'termination_conditions' => 'Either party may terminate',
        'key_obligations' => ['Provide support', 'Pay fees'],
        'summary' => 'Test summary',
        'status' => 'active',
        'confidence_score' => 0.92,
    ];

    $normalized = $normalizer->normalize($flatData);

    expect($normalized)->toHaveKeys(['contract_number', 'contract_title', 'contract_type', 'parties', 'dates', 'terms', 'financial', 'legal', 'key_obligations', 'summary', 'status', 'metadata'])
        ->and($normalized['contract_title'])->toBe('Software License Agreement')
        ->and($normalized['parties'])->toHaveCount(2)
        ->and($normalized['dates'])->toHaveKeys(['effective_date', 'expiry_date', 'signature_date'])
        ->and($normalized['financial']['contract_value'])->toBe(50000)
        ->and($normalized['financial']['currency'])->toBe('EUR')
        ->and($normalized['legal']['governing_law'])->toBe('Norwegian law')
        ->and($normalized['metadata']['confidence_score'])->toBe(0.92);
});

it('throws exception on validation failure', function () {
    $mockGeminiResponse = [
        'data' => [
            // Missing required fields
            'contract_type' => 'License',
        ],
    ];

    $mockProvider = mock(GeminiProvider::class)
        ->shouldReceive('analyzeFileByUri')
        ->once()
        ->andReturn($mockGeminiResponse)
        ->getMock();

    $extractor = new ContractExtractor(
        $mockProvider,
        new ContractValidator,
        new ContractDataNormalizer
    );

    $extractor->extract('files/test-uri', $this->file);
})->throws(Exception::class, 'Contract validation failed');

it('handles gemini api exceptions', function () {
    $mockProvider = mock(GeminiProvider::class)
        ->shouldReceive('analyzeFileByUri')
        ->once()
        ->andThrow(new GeminiApiException('API error'))
        ->getMock();

    $extractor = new ContractExtractor(
        $mockProvider,
        new ContractValidator,
        new ContractDataNormalizer
    );

    $extractor->extract('files/test-uri', $this->file);
})->throws(GeminiApiException::class, 'API error');

it('returns correct schema', function () {
    $extractor = new ContractExtractor(
        mock(GeminiProvider::class),
        new ContractValidator,
        new ContractDataNormalizer
    );

    $schema = $extractor->getSchema();

    expect($schema)->toHaveKeys(['name', 'responseSchema'])
        ->and($schema['name'])->toBe('contract_extraction')
        ->and($schema['responseSchema']['properties'])->toHaveKeys([
            'contract_number',
            'contract_title',
            'contract_type',
            'parties',
            'effective_date',
            'expiry_date',
            'signature_date',
            'contract_value',
            'currency',
        ])
        ->and($schema['responseSchema']['required'])->toContain('contract_title')
        ->and($schema['responseSchema']['required'])->toContain('effective_date')
        ->and($schema['responseSchema']['required'])->toContain('parties');
});

it('returns extraction prompt', function () {
    $extractor = new ContractExtractor(
        mock(GeminiProvider::class),
        new ContractValidator,
        new ContractDataNormalizer
    );

    $prompt = $extractor->getPrompt();

    expect($prompt)->toBeString()
        ->and($prompt)->toContain('Extract all contract information')
        ->and($prompt)->toContain('Contract identification')
        ->and($prompt)->toContain('Parties')
        ->and($prompt)->toContain('YYYY-MM-DD');
});

it('warns on missing second party', function () {
    $validator = new ContractValidator;

    $dataWithSingleParty = [
        'contract_title' => 'Test Agreement',
        'effective_date' => '2024-01-15',
        'parties' => [
            ['name' => 'Party A'],
        ],
    ];

    $result = $validator->validate($dataWithSingleParty);

    expect($result['valid'])->toBeTrue()
        ->and($result['warnings'])->toContain('Contract should have at least two parties');
});

it('warns on low confidence score', function () {
    $validator = new ContractValidator;

    $dataWithLowConfidence = [
        'contract_title' => 'Test Agreement',
        'effective_date' => '2024-01-15',
        'parties' => [
            ['name' => 'Party A'],
            ['name' => 'Party B'],
        ],
        'confidence_score' => 0.3,
    ];

    $result = $validator->validate($dataWithLowConfidence);

    expect($result['valid'])->toBeTrue()
        ->and($result['warnings'])->toContain('Low confidence score: 0.3');
});
