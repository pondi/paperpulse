<?php

use App\Models\Collection;
use App\Models\Contract;
use App\Models\ExtractableEntity;
use App\Models\File;
use App\Models\Merchant;
use App\Models\PublicCollectionLink;
use App\Models\Receipt;
use App\Models\User;
use App\Models\Warranty;
use Inertia\Testing\AssertableInertia;

beforeEach(fn () => $this->withoutVite());

test('public receipt and contract cards expose correct fields and safe supplemental metadata', function () {
    $owner = User::factory()->create();
    $visitor = User::factory()->create();
    $collection = Collection::factory()->create(['user_id' => $owner->id]);
    $receiptFile = File::factory()->create(['user_id' => $owner->id, 'fileName' => 'a_receipt']);
    $contractFile = File::factory()->create(['user_id' => $owner->id, 'fileName' => 'b_contract']);
    $merchant = Merchant::create(['user_id' => $owner->id, 'name' => 'Public merchant', 'email' => 'private@example.com']);
    $receipt = Receipt::factory()->create(['user_id' => $owner->id, 'file_id' => $receiptFile->id,
        'merchant_id' => $merchant->id, 'receipt_date' => '2026-01-02', 'total_amount' => 0, 'currency' => 'NOK']);
    $contract = Contract::factory()->create(['user_id' => $owner->id, 'file_id' => $contractFile->id,
        'contract_title' => 'Public agreement', 'parties' => [['name' => 'Acme', 'contact' => 'private-phone'], 'Client', ['contact' => 'hidden']]]);
    $warranty = Warranty::factory()->create(['user_id' => $owner->id, 'file_id' => $receiptFile->id,
        'product_name' => 'Covered product', 'serial_number' => 'PRIVATE-SERIAL']);
    foreach ([[$receipt, 'receipt', true], [$contract, 'contract', true], [$warranty, 'warranty', false]] as [$entity, $type, $primary]) {
        ExtractableEntity::create(['user_id' => $owner->id, 'file_id' => $entity->file_id,
            'entity_type' => $type, 'entity_id' => $entity->id, 'is_primary' => $primary, 'extracted_at' => now()]);
    }
    $collection->files()->attach([$receiptFile->id, $contractFile->id]);
    $link = PublicCollectionLink::factory()->create(['collection_id' => $collection->id, 'created_by_user_id' => $owner->id]);
    $response = $this->actingAs($visitor)->get(route('shared.collections.show', $link->token));
    $response->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->where('files.0.entity_title', 'Public merchant')
        ->where('files.0.entity_details.merchant_name', 'Public merchant')
        ->where('files.0.entity_details.purchase_date', '2026-01-02')
        ->where('files.0.entity_details.total', '0.00')
        ->where('files.1.entity_details.parties', 'Acme & Client')
        ->where('files.0.supplemental_entities.0.title', 'Covered product')
        ->missing('files.0.supplemental_entities.0.details.serial_number')
        ->missing('files.0.entity_details.email'));
    $response->assertDontSee('PRIVATE-SERIAL')->assertDontSee('private-phone')->assertDontSee('private@example.com');
});
