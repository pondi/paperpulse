<?php

use App\Models\Contract;
use App\Models\Document;
use App\Models\ExtractableEntity;
use App\Models\File;
use App\Models\Invoice;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('downloads the correct original for documents and invoices with matching entity IDs', function (): void {
    Storage::fake('paperpulse');
    $user = User::factory()->create();
    $documentFile = File::factory()->for($user)->create(['file_type' => 'document', 'status' => 'completed', 'fileExtension' => 'pdf', 's3_original_path' => 'originals/document.pdf']);
    $invoiceFile = File::factory()->for($user)->create(['file_type' => 'document', 'status' => 'completed', 'fileExtension' => 'pdf', 's3_original_path' => 'originals/invoice.pdf']);
    $document = Document::factory()->create(['user_id' => $user->id, 'file_id' => $documentFile->id]);
    $invoice = Invoice::factory()->create(['id' => $document->id, 'user_id' => $user->id, 'file_id' => $invoiceFile->id]);
    foreach ([[$document, $documentFile, 'document'], [$invoice, $invoiceFile, 'invoice']] as [$entity, $file, $type]) {
        ExtractableEntity::create(['user_id' => $user->id, 'file_id' => $file->id, 'entity_type' => $type, 'entity_id' => $entity->id, 'is_primary' => true, 'extracted_at' => now()]);
        Storage::disk('paperpulse')->put($file->s3_original_path, $type.' bytes');
    }
    $this->actingAs($user);
    $rows = $this->get(route('documents.index'))->assertOk()->inertiaProps('documents.data');
    foreach ($rows as $row) {
        expect($this->get($row['file']['url'])->assertOk()->streamedContent())->toBe($row['entity_type'].' bytes');
    }
    $this->get(route('invoices.download', $invoice))->assertOk()->assertContent('invoice bytes');
    Storage::disk('paperpulse')->delete($invoiceFile->s3_original_path);
    $this->get(collect($rows)->firstWhere('entity_type', 'invoice')['file']['url'])->assertNotFound();
    $this->actingAs(User::factory()->create())->get($rows[0]['file']['url'])->assertForbidden();
});

dataset('entityRedirects', [
    'contract' => [Contract::class, 'contracts.show', 'contract'],
    'invoice' => [Invoice::class, 'invoices.show', 'invoice'],
    'voucher' => [Voucher::class, 'vouchers.show', 'voucher'],
]);

it('links uploaded documents to their canonical extractable entity pages', function (string $modelClass, string $routeName, string $entityType): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $file = File::factory()->create([
        'user_id' => $user->id,
        'file_type' => 'document',
        'status' => 'completed',
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

    $this->get(route('documents.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('documents.data', 1)
            ->where('documents.data.0.id', $entity->id)
            ->where('documents.data.0.file_id', $file->id)
            ->where('documents.data.0.entity_type', $entityType));
    $this->get(route($routeName, $entity->id))->assertOk();
    $this->get(route('documents.show', $entity->id))->assertNotFound();

    $this->actingAs(User::factory()->create())->get(route($routeName, $entity->id))->assertNotFound();
})->with('entityRedirects');

it('returns not found when no document or extractable entity exists', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->get(route('documents.show', 999999))
        ->assertNotFound();
});
