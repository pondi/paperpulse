<?php

use App\Models\BankStatement;
use App\Models\Collection;
use App\Models\CollectionShare;
use App\Models\Contract;
use App\Models\Document;
use App\Models\Invoice;
use App\Models\Receipt;
use App\Models\ReturnPolicy;
use App\Models\User;
use App\Models\Voucher;
use App\Models\Warranty;
use App\Services\StorageService;

test('collection permissions apply to entity and binary reads and revoke immediately', function () {
    $owner = User::factory()->create();
    $recipient = User::factory()->create();
    $document = Document::factory()->create(['user_id' => $owner->id]);
    $file = $document->file;
    $collection = Collection::factory()->create(['user_id' => $owner->id]);
    $collection->files()->attach($file->id);
    $share = CollectionShare::create(['collection_id' => $collection->id, 'shared_by_user_id' => $owner->id,
        'shared_with_user_id' => $recipient->id, 'permission' => 'view', 'shared_at' => now()]);
    $this->actingAs($recipient);
    expect($recipient->can('view', $document))->toBeTrue();
    expect($recipient->can('view', $file))->toBeTrue();
    expect($recipient->can('update', $document))->toBeFalse();
    expect($recipient->can('delete', $document))->toBeFalse();
    $this->mock(StorageService::class, fn ($mock) => $mock->shouldReceive('getFileByUserAndGuid')->once()->andReturn('pdf bytes'));
    $url = route('documents.serve', ['guid' => $file->guid, 'type' => 'documents', 'extension' => $file->fileExtension]);
    $this->get($url)->assertOk();
    $share->update(['permission' => 'edit']);
    expect($recipient->can('update', $document))->toBeTrue();
    expect($recipient->can('delete', $file))->toBeFalse();
    $collection->files()->detach($file->id);
    $this->get($url)->assertForbidden();
    expect($recipient->can('view', $document))->toBeFalse();
    $collection->files()->attach($file->id);
    $share->update(['expires_at' => now()->subMinute()]);
    expect($recipient->can('view', $document))->toBeFalse();
    $share->update(['expires_at' => null]);
    $share->delete();
    expect($recipient->can('view', $file))->toBeFalse();
});

test('every extracted entity uses shared file permissions without granting deletion', function (string $model, string $type, string $route) {
    $owner = User::factory()->create();
    $recipient = User::factory()->create();
    $entity = $model::factory()->create(['user_id' => $owner->id]);
    $collection = Collection::factory()->create(['user_id' => $owner->id]);
    $collection->files()->attach($entity->file_id);
    $share = CollectionShare::create(['collection_id' => $collection->id, 'shared_by_user_id' => $owner->id,
        'shared_with_user_id' => $recipient->id, 'permission' => 'view', 'shared_at' => now()]);
    $this->actingAs($recipient);
    expect($recipient->can('view', $entity))->toBeTrue();
    expect($recipient->can('delete', $entity))->toBeFalse();
    if ($route !== '') {
        $this->getJson('/api/v1/'.$route.'/'.$entity->id)->assertOk();
        $share->delete();
        $this->getJson('/api/v1/'.$route.'/'.$entity->id)->assertNotFound();
    }
})->with([
    [Receipt::class, 'receipt', 'receipts'],
    [Invoice::class, 'invoice', 'invoices'],
    [Contract::class, 'contract', 'contracts'],
    [Voucher::class, 'voucher', 'vouchers'],
    [Warranty::class, 'warranty', 'warranties'],
    [ReturnPolicy::class, 'return_policy', ''],
    [BankStatement::class, 'bank_statement', 'bank-statements'],
]);
