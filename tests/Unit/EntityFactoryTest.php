<?php

namespace Tests\Unit;

use App\Models\File;
use App\Models\Merchant;
use App\Services\EntityFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EntityFactoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_skips_entities_without_data(): void
    {
        $file = File::factory()->create();
        $factory = app(EntityFactory::class);

        $created = $factory->createEntitiesFromParsedData([
            'entities' => [
                ['type' => 'voucher', 'data' => []],
            ],
        ], $file, 'receipt');

        $this->assertCount(0, $created);
        $this->assertDatabaseMissing('vouchers', ['file_id' => $file->id]);
    }

    public function test_resolves_merchant_for_voucher(): void
    {
        $file = File::factory()->create();
        $factory = app(EntityFactory::class);

        $created = $factory->createEntitiesFromParsedData([
            'entities' => [
                [
                    'type' => 'voucher',
                    'data' => [
                        'voucher_type' => 'gift_card',
                        'code' => 'TEST-123',
                        'original_value' => 100,
                        'merchant' => [
                            'name' => 'Acme Shop',
                            'vat_number' => 'NO123456789',
                        ],
                    ],
                ],
            ],
        ], $file, 'receipt');

        $this->assertCount(1, $created);
        $voucher = $created[0]['model'];
        $merchant = Merchant::first();

        $this->assertNotNull($merchant);
        $this->assertEquals($merchant->id, $voucher->merchant_id);
        $this->assertEquals('Acme Shop', $merchant->name);
    }

    public function test_repairs_flattened_structure_and_normalizes_case(): void
    {
        $file = File::factory()->create();
        $factory = app(EntityFactory::class);

        // Simulate malformed Gemini response: Uppercase type and flattened data (no 'data' key)
        $created = $factory->createEntitiesFromParsedData([
            'entities' => [
                [
                    'type' => 'RECEIPT', // Uppercase
                    'confidence_score' => 0.95,
                    // Flattened fields (should be in 'data')
                    'merchant' => ['name' => 'Test Store'],
                    'totals' => ['total_amount' => 500],
                    'receipt_info' => ['date' => '2025-01-01'],
                ],
            ],
        ], $file, 'receipt');

        $this->assertCount(1, $created);
        $receipt = $created[0]['model'];

        $this->assertEquals('receipt', $created[0]['type']);
        $this->assertEquals(500, $receipt->total_amount);
    }
}
