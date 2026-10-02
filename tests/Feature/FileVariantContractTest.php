<?php

use App\Http\Resources\Inertia\FileInertiaResource;
use App\Http\Resources\Inertia\PublicCollectionFileResource;
use App\Models\Collection;
use App\Models\File;
use App\Models\PublicCollectionLink;
use App\Models\User;
use App\Services\Files\StoragePathBuilder;
use App\Services\Search\SearchResultFormatter;
use App\Services\SearchService;
use Illuminate\Support\Facades\Storage;

it('uses the same recorded variant across API web and public content', function (string $extension, string $variant, bool $hasArchive, ?string $pdfVariant): void {
    Storage::fake('paperpulse');
    Storage::fake('pulsedav');
    $owner = User::factory()->create();
    $file = File::factory()->create([
        'user_id' => $owner->id, 'fileExtension' => $extension, 'file_type' => 'document',
        's3_original_path' => 'assets/original.'.$extension,
        's3_archive_path' => $hasArchive ? 'assets/converted.pdf' : null,
        'has_image_preview' => $variant === 'preview',
        's3_image_path' => $variant === 'preview' ? 'assets/preview.jpg' : null,
    ]);
    $expectedPath = match ($variant) {
        'archive' => $hasArchive ? $file->s3_archive_path : $file->s3_original_path,
        'preview' => $file->s3_image_path,
        default => $file->s3_original_path,
    };
    Storage::disk('paperpulse')->put($expectedPath, 'Recorded variant bytes');
    $collection = Collection::factory()->create(['user_id' => $owner->id]);
    $collection->files()->attach($file);
    $link = PublicCollectionLink::factory()->create(['collection_id' => $collection->id, 'created_by_user_id' => $owner->id]);
    $this->actingAs($owner);

    $api = $this->get(route('api.files.content', ['file' => $file->id, 'variant' => $variant]))->assertOk();
    $web = $this->get(route('documents.serve', ['guid' => $file->guid, 'type' => 'documents', 'extension' => $extension, 'variant' => $variant]))->assertOk();
    $public = $this->get(route('shared.collections.file', ['token' => $link->token, 'guid' => $file->guid, 'variant' => $variant]))->assertOk();
    foreach ([$api, $web, $public] as $response) {
        expect($response->streamedContent())->toBe('Recorded variant bytes');
    }

    $webInfo = FileInertiaResource::forShow($file)->resolve();
    $publicInfo = (new PublicCollectionFileResource($file, $link->token))->resolve();
    $searchInfo = (new SearchResultFormatter)->buildEntityFileInfo($file);
    expect(StoragePathBuilder::pdfVariant($file))->toBe($pdfVariant)
        ->and($searchInfo['pdf_variant'])->toBe($pdfVariant);
    foreach ([$webInfo, $publicInfo, $searchInfo] as $info) {
        if ($pdfVariant === null) {
            expect($info['pdfUrl'])->toBeNull();
        } else {
            parse_str(parse_url($info['pdfUrl'], PHP_URL_QUERY), $query);
            expect($query['variant'])->toBe($pdfVariant);
        }
    }
})->with([
    ['pdf', 'archive', false, 'original'],
    ['docx', 'archive', true, 'archive'],
    ['png', 'preview', false, null],
    ['png', 'original', false, null],
]);

it('does not guess unrecorded variants or serve originals in their place', function (string $variant, bool $recordMissing): void {
    Storage::fake('paperpulse');
    Storage::fake('pulsedav');
    $owner = User::factory()->create();
    $file = File::factory()->create([
        'user_id' => $owner->id, 'file_type' => 'document', 'fileExtension' => 'png',
        's3_original_path' => 'assets/original.png',
        's3_archive_path' => $recordMissing && $variant === 'archive' ? 'assets/missing.pdf' : null,
        'has_image_preview' => true, 's3_image_path' => $recordMissing && $variant === 'preview' ? 'assets/missing.jpg' : null,
    ]);
    Storage::disk('paperpulse')->put($file->s3_original_path, 'Original bytes');
    Storage::disk('paperpulse')->put(StoragePathBuilder::storagePath($owner->id, $file->guid, 'document', $variant, $variant === 'archive' ? 'pdf' : 'jpg'), 'Unrecorded bytes');
    $collection = Collection::factory()->create(['user_id' => $owner->id]);
    $collection->files()->attach($file);
    $link = PublicCollectionLink::factory()->create(['collection_id' => $collection->id, 'created_by_user_id' => $owner->id]);
    $this->actingAs($owner);

    $this->getJson(route('api.files.content', ['file' => $file->id, 'variant' => $variant]))->assertNotFound();
    $this->getJson(route('documents.serve', ['guid' => $file->guid, 'type' => 'documents', 'extension' => 'png', 'variant' => $variant]))->assertNotFound();
    $this->get(route('shared.collections.file', ['token' => $link->token, 'guid' => $file->guid, 'variant' => $variant]))->assertNotFound();
})->with(['archive', 'preview'])->with([false, true]);

it('advertises a working original PDF search link without an archive', function (): void {
    Storage::fake('paperpulse');
    Storage::fake('pulsedav');
    $owner = User::factory()->create();
    $file = File::factory()->create(['user_id' => $owner->id, 'fileExtension' => 'pdf', 's3_original_path' => 'assets/plain.pdf']);
    Storage::disk('paperpulse')->put($file->s3_original_path, '%PDF plain');
    $info = (new SearchResultFormatter)->buildEntityFileInfo($file);
    $this->mock(SearchService::class, fn ($mock) => $mock->shouldReceive('search')->once()->andReturn(['results' => [['file' => $info]]]));
    $response = $this->actingAs($owner)->getJson('/api/v1/search?q=plain')->assertOk();
    $url = $response->json('data.results.0.links.pdf');
    expect($url)->toBe(route('api.files.content', $file).'?variant=original');
    expect($this->get($url)->assertOk()->streamedContent())->toBe('%PDF plain');
});
