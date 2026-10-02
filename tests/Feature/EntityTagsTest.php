<?php

use App\Models\BankStatement;
use App\Models\Contract;
use App\Models\Document;
use App\Models\File;
use App\Models\Invoice;
use App\Models\Receipt;
use App\Models\ReturnPolicy;
use App\Models\Tag;
use App\Models\User;
use App\Models\Voucher;
use App\Models\Warranty;
use Illuminate\Support\Facades\DB;

it('eager loads and filters tags through file IDs for every entity', function (string $model) {
    $owner = User::factory()->create();
    $this->actingAs($owner);
    $files = File::factory()->count(4)->create(['user_id' => $owner->id]);
    $entities = collect([$files[1], $files[2], $files[3]])->map(
        fn (File $file) => $model::factory()->create(['user_id' => $owner->id, 'file_id' => $file->id])
    );
    $tag = Tag::factory()->create(['user_id' => $owner->id]);
    $files[1]->addTag($tag);
    $files[3]->addTag($tag);

    DB::enableQueryLog();
    DB::flushQueryLog();
    $loaded = $model::with('tags')->whereKey($entities->pluck('id')->all())->orderBy('id')->get();
    expect($loaded->map->getTagNames()->all())->toBe([[$tag->name], [], [$tag->name]])
        ->and(DB::getQueryLog())->toHaveCount(2);
    DB::disableQueryLog();

    expect($entities[0]->tags()->pluck('tags.id')->all())->toBe([$tag->id])
        ->and($model::whereHas('tags', fn ($query) => $query->whereKey($tag->id))->pluck('id')->all())
        ->toEqualCanonicalizing([$entities[0]->id, $entities[2]->id]);
})->with([
    Receipt::class, Document::class, Invoice::class, Contract::class,
    Voucher::class, Warranty::class, ReturnPolicy::class, BankStatement::class,
]);

it('returns no tags without a file and excludes another tenant', function () {
    $document = new Document;
    expect($document->getTagNames())->toBe([]);

    $foreign = Document::factory()->create();
    $tag = Tag::factory()->create(['user_id' => $foreign->user_id]);
    $foreign->file->addTag($tag);
    $this->actingAs(User::factory()->create());

    expect(Document::whereHas('tags', fn ($query) => $query->whereKey($tag->id))->count())->toBe(0);
});
