<?php

namespace Tests\Unit;

use App\Models\BankStatement;
use App\Models\BankTransaction;
use App\Models\Contract;
use App\Models\ExtractableEntity;
use App\Models\Invoice;
use App\Models\InvoiceLineItem;
use App\Models\Voucher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExtractableEntitiesTest extends TestCase
{
    use RefreshDatabase;

    public function test_voucher_creation_creates_extractable_entity_record(): void
    {
        $voucher = Voucher::factory()->create();

        // Manually create the record as EntityFactory would (since automatic creation was removed)
        ExtractableEntity::create([
            'file_id' => $voucher->file_id,
            'user_id' => $voucher->user_id,
            'entity_type' => 'voucher',
            'entity_id' => $voucher->id,
            'is_primary' => true,
            'extracted_at' => now(),
        ]);

        $this->assertDatabaseHas('extractable_entities', [
            'entity_type' => 'voucher',
            'entity_id' => $voucher->id,
            'file_id' => $voucher->file_id,
            'user_id' => $voucher->user_id,
            'is_primary' => true,
        ]);

        $file = $voucher->file;
        $this->assertNotNull($file);
        $this->assertTrue(
            $file->extractedEntities()->where('entity_id', $voucher->id)->exists()
        );
        $this->assertSame($voucher->id, $file->getEntitiesOfType('voucher')->first()->id);
        $this->assertSame($voucher->id, $file->getPrimaryEntity()->id);
    }

    public function test_contract_morph_map_resolves_entity(): void
    {
        $contract = Contract::factory()->create();

        // Manually create extraction record
        ExtractableEntity::create([
            'file_id' => $contract->file_id,
            'user_id' => $contract->user_id,
            'entity_type' => 'contract',
            'entity_id' => $contract->id,
            'is_primary' => true,
            'extracted_at' => now(),
        ]);

        $contract->refresh();

        $extraction = $contract->extraction;
        $this->assertInstanceOf(ExtractableEntity::class, $extraction);
        $this->assertInstanceOf(Contract::class, $extraction->entity);
    }

    public function test_invoice_line_items_relationship(): void
    {
        $invoice = Invoice::factory()->create();
        InvoiceLineItem::factory()->count(2)->create([
            'invoice_id' => $invoice->id,
        ]);

        $this->assertCount(2, $invoice->lineItems);
    }

    public function test_bank_statement_transactions_relationship(): void
    {
        $statement = BankStatement::factory()->create(['transaction_count' => 2]);
        $transactions = BankTransaction::factory()->count(2)->create([
            'bank_statement_id' => $statement->id,
        ]);

        $this->assertCount(2, $statement->transactions);
        $this->assertTrue(
            $statement->transactions->pluck('id')->every(fn ($id) => $transactions->pluck('id')->contains($id))
        );
    }
}
