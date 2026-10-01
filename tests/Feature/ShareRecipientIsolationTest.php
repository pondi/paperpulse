<?php

use App\Models\Collection;
use App\Models\CollectionShare;
use App\Models\Document;
use App\Models\File;
use App\Models\FileShare;
use App\Models\Receipt;
use App\Models\User;

test('collection recipients remain isolated and exclude expired shares', function () {
    $owner = User::factory()->create();
    $recipients = User::factory()->count(3)->create();
    $collections = Collection::factory()->count(2)->create(['user_id' => $owner->id]);
    foreach ([[$collections[0], $recipients[0], null], [$collections[1], $recipients[1], null], [$collections[0], $recipients[2], now()->subMinute()]] as [$collection, $recipient, $expires]) {
        CollectionShare::create(['collection_id' => $collection->id, 'shared_by_user_id' => $owner->id,
            'shared_with_user_id' => $recipient->id, 'permission' => 'view', 'shared_at' => now(), 'expires_at' => $expires]);
    }
    expect($collections[0]->sharedUsers->modelKeys())->toBe([$recipients[0]->id]);
    expect($collections[1]->sharedUsers->modelKeys())->toBe([$recipients[1]->id]);
});

test('entity recipients use file keys and remain isolated by type and expiry', function (string $model, string $type) {
    $owner = User::factory()->create();
    $recipients = User::factory()->count(3)->create();
    File::factory()->count(3)->create(['user_id' => $owner->id]);
    $entity = $model::factory()->create(['user_id' => $owner->id]);
    $other = $model::factory()->create(['user_id' => $owner->id]);
    expect($entity->id)->not->toBe($entity->file_id);
    foreach ([[$entity, $recipients[0], $type, null], [$other, $recipients[1], $type, null], [$entity, $recipients[2], $type, now()->subMinute()]] as [$item, $recipient, $shareType, $expires]) {
        FileShare::create(['file_id' => $item->file_id, 'file_type' => $shareType, 'shared_by_user_id' => $owner->id,
            'shared_with_user_id' => $recipient->id, 'permission' => 'view', 'shared_at' => now(), 'expires_at' => $expires]);
    }
    expect($entity->sharedUsers->modelKeys())->toBe([$recipients[0]->id]);
    expect($other->sharedUsers->modelKeys())->toBe([$recipients[1]->id]);
})->with([[Receipt::class, 'receipt'], [Document::class, 'document']]);

