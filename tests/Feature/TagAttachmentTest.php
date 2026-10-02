<?php

use App\Jobs\System\ApplyTags;
use App\Models\Document;
use App\Models\File;
use App\Models\Tag;
use App\Models\User;
use App\Services\Tags\TagAttachmentService;
use Illuminate\Support\Str;

it('syncs and attaches owned tags on the current file pivot', function () {
    $owner = User::factory()->create();
    $file = File::factory()->create(['user_id' => $owner->id]);
    $document = Document::factory()->create(['file_id' => $file->id, 'user_id' => $owner->id]);
    $tags = Tag::factory()->count(2)->create(['user_id' => $owner->id]);
    $foreign = Tag::factory()->create();

    TagAttachmentService::syncTags($document, [$tags[0]->id, $foreign->id]);
    TagAttachmentService::attachTags($document, [$tags[1]->id, $foreign->id]);

    expect($file->tags()->pluck('tags.id')->all())->toEqualCanonicalizing($tags->modelKeys());

    $document->delete();
    $replacement = Document::factory()->create(['file_id' => $file->id, 'user_id' => $owner->id]);
    expect($replacement->tags()->count())->toBe(2);

    TagAttachmentService::syncTags($replacement, []);
    expect($file->tags()->count())->toBe(0);
});

it('applies queued tags without requiring an extracted entity', function () {
    $file = File::factory()->create();
    $tags = Tag::factory()->count(2)->create(['user_id' => $file->user_id]);
    $foreign = Tag::factory()->create();
    $file->syncTags([$tags[0]->id]);
    $job = new ApplyTags((string) Str::uuid(), $file, [$tags[1]->id, $foreign->id]);

    $job->handle();
    $job->handle();

    expect($file->tags()->pluck('tags.id')->all())->toEqualCanonicalizing($tags->modelKeys());
});
