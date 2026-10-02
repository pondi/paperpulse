<?php

use App\Jobs\Search\ReindexFile;
use App\Models\Category;
use App\Models\Collection;
use App\Models\Document;
use App\Models\File;
use App\Models\Receipt;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Support\Facades\Bus;
use Laravel\Scout\EngineManager;
use Laravel\Scout\Engines\Engine;

it('queues reindexing for file relationships and notes', function (string $mutation) {
    $file = File::factory()->create();
    $tag = Tag::factory()->create(['user_id' => $file->user_id]);
    $folder = Collection::factory()->create(['user_id' => $file->user_id]);
    if (str_contains($mutation, 'detach')) {
        $file->addTag($tag);
        $file->collections()->attach($folder);
    }
    Bus::fake();
    match ($mutation) {
        'file tag attach' => $file->addTag($tag),
        'file tag detach' => $file->removeTag($tag),
        'tag file attach' => $tag->files()->attach($file),
        'tag file detach' => $tag->files()->detach($file),
        'file collection attach' => $file->collections()->attach($folder),
        'collection file detach' => $folder->files()->detach($file),
        'note' => $file->update(['note' => 'Searchable note']),
    };
    Bus::assertDispatchedTimes(ReindexFile::class, 1);
    Bus::assertDispatched(ReindexFile::class, fn ($job) => $job->fileId === $file->id);
})->with(['file tag attach', 'file tag detach', 'tag file attach', 'tag file detach', 'file collection attach', 'collection file detach', 'note']);

it('reindexes the parent of added updated deleted and restored line items', function () {
    $receipt = Receipt::factory()->create();
    Bus::fake();
    $item = $receipt->lineItems()->create(['text' => 'Original item', 'qty' => 1, 'price' => 5, 'total' => 5]);
    $item->update(['text' => 'Changed item']);
    $item->delete();
    $item->restore();
    Bus::assertDispatched(ReindexFile::class, fn ($job) => $job->fileId === $receipt->file_id);
    Bus::assertDispatchedTimes(ReindexFile::class, 1);
});

it('reindexes fresh entity contents and excludes foreign or deleted entities', function () {
    $file = File::factory()->create(['status' => 'completed']);
    $receipt = Receipt::factory()->create(['user_id' => $file->user_id, 'file_id' => $file->id]);
    $document = Document::factory()->create(['user_id' => $file->user_id, 'file_id' => $file->id]);
    $deleted = Receipt::factory()->create(['user_id' => $file->user_id, 'file_id' => $file->id]);
    $deleted->delete();
    $foreign = Receipt::factory()->create(['file_id' => $file->id, 'user_id' => User::factory()]);
    $tag = Tag::factory()->create(['user_id' => $file->user_id]);
    Bus::fake();
    $file->addTag($tag);
    $file->update(['note' => 'Updated note']);
    $engine = Mockery::mock(Engine::class);
    $indexed = [];
    $engine->shouldReceive('update')->andReturnUsing(function ($models) use (&$indexed): void {
        foreach ($models as $model) {
            $indexed[$model::class] = $model->toSearchableArray();
        }
    });
    $this->app->make(EngineManager::class)->extend('mutation-test', fn () => $engine);
    config(['scout.driver' => 'mutation-test', 'scout.queue' => false]);
    (new ReindexFile($file->id))->handle();

    expect(array_keys($indexed))->toBe([Receipt::class, Document::class])
        ->and($indexed[Receipt::class]['id'])->toBe($receipt->id)
        ->and($indexed[Receipt::class]['tags'])->toContain($tag->name)
        ->and($indexed[Document::class]['note'])->toBe('Updated note');
});

it('keeps bulk category IDs and labels consistent and reindexes owned files', function () {
    $user = User::factory()->create();
    $receipt = Receipt::factory()->create(['user_id' => $user->id]);
    $category = Category::create(['user_id' => $user->id, 'name' => 'Groceries', 'slug' => 'groceries']);
    $foreign = Receipt::factory()->create();
    Bus::fake();
    $this->actingAs($user)->post(route('bulk.receipts.categorize'), [
        'receipt_ids' => [$receipt->id], 'category_id' => $category->id, 'category' => 'Stale label',
    ])->assertRedirect()->assertSessionHasNoErrors();
    expect($receipt->fresh()->category_id)->toBe($category->id)
        ->and($receipt->fresh()->receipt_category)->toBe($category->name);
    Bus::assertDispatchedTimes(ReindexFile::class, 1);
    $this->post(route('bulk.receipts.categorize'), ['receipt_ids' => [$foreign->id], 'category_id' => $category->id])
        ->assertSessionHasErrors('receipt_ids.0');
});

it('does not reindex rolled back changes', function () {
    $file = File::factory()->create();
    Bus::fake();
    try {
        $file->getConnection()->transaction(function () use ($file): void {
            $file->update(['note' => 'Rolled back']);
            throw new RuntimeException('Rollback');
        });
    } catch (RuntimeException) {
    }
    Bus::assertNothingDispatched();
});

it('keeps receipt labels aligned with category changes and reindexes category renames', function () {
    $user = User::factory()->create();
    $category = Category::create(['user_id' => $user->id, 'name' => 'Before', 'slug' => 'before']);
    $receipt = Receipt::factory()->create(['user_id' => $user->id, 'category_id' => $category->id, 'receipt_category' => 'Stale']);
    expect($receipt->receipt_category)->toBe('Before');
    Bus::fake();
    $category->update(['name' => 'After']);
    expect($receipt->fresh()->receipt_category)->toBe('After');
    Bus::assertDispatched(ReindexFile::class, fn ($job) => $job->fileId === $receipt->file_id);
    $receipt->refresh()->update(['receipt_category' => 'Unassigned name']);
    expect($receipt->fresh()->category_id)->toBeNull();
});

it('reindexes renamed tags and folders without affecting another owner', function () {
    $file = File::factory()->create();
    $tag = Tag::factory()->create(['user_id' => $file->user_id]);
    $folder = Collection::factory()->create(['user_id' => $file->user_id]);
    $file->addTag($tag);
    $file->collections()->attach($folder);
    Bus::fake();
    $tag->update(['name' => 'Renamed tag']);
    $folder->update(['name' => 'Renamed folder']);
    Bus::assertDispatchedTimes(ReindexFile::class, 1);
    Bus::assertDispatched(ReindexFile::class, fn ($job) => $job->fileId === $file->id);
});
