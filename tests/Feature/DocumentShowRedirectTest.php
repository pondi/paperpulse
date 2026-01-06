<?php

use App\Models\Contract;
use App\Models\ExtractableEntity;
use App\Models\File;
use App\Models\Invoice;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

dataset('entityRedirects', [
    'contract' => [Contract::class, 'contracts.show', 'contract'],
    'invoice' => [Invoice::class, 'invoices.show', 'invoice'],
    'voucher' => [Voucher::class, 'vouchers.show', 'voucher'],
]);

it('redirects document show to extractable entity show pages', function (string $modelClass, string $routeName, string $entityType) {
    $user = User::factory()->create();
    $this->actingAs($user);

    $file = File::factory()->create([
        'user_id' => $user->id,
        'file_type' => 'document',
    ]);

    $entity = $modelClass::factory()->create([
        'user_id' => $user->id,
        'file_id' => $file->id,
    ]);

    ExtractableEntity::create([
        'file_id' => $file->id,
        'user_id' => $user->id,
        'entity_type' => $entityType,
        'entity_id' => $entity->id,
        'is_primary' => true,
        'extraction_provider' => 'gemini',
        'extraction_metadata' => ['entity_type_name' => $entityType],
        'extracted_at' => now(),
    ]);

    $this->get(route('documents.show', $entity->id))
        ->assertRedirect(route($routeName, $entity->id));
})->with('entityRedirects');

it('returns not found when no document or extractable entity exists', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->get(route('documents.show', 999999))
        ->assertNotFound();
});
