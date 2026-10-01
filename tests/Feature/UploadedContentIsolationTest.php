<?php

use App\Models\Collection;
use App\Models\File;
use App\Models\PublicCollectionLink;
use App\Models\User;
use App\Services\StorageService;

test('uploaded content uses safe disposition and isolated headers on every endpoint', function (string $endpoint, string $extension, string $mime, string $disposition) {
    $owner = User::factory()->create();
    $file = File::factory()->create(['user_id' => $owner->id, 'fileExtension' => $extension,
        'fileType' => 'text/html', 's3_original_path' => 'uploads/test.'.$extension]);
    $body = '<script>fetch("/authenticated-action", {method: "POST"})</script>';
    $this->mock(StorageService::class, function ($mock) use ($body) {
        $mock->shouldReceive('getFile')->andReturn($body);
        $mock->shouldReceive('readStream')->andReturnUsing(function () use ($body) {
            $stream = fopen('php://temp', 'w+');
            fwrite($stream, $body);
            rewind($stream);

            return $stream;
        });
    });
    $this->actingAs($owner);
    if ($endpoint === 'public') {
        $collection = Collection::factory()->create(['user_id' => $owner->id]);
        $collection->files()->attach($file->id);
        $link = PublicCollectionLink::factory()->create(['collection_id' => $collection->id, 'created_by_user_id' => $owner->id]);
        $url = route('shared.collections.file', [$link->token, $file->guid]);
    } elseif ($endpoint === 'api') {
        $url = route('api.files.content', $file->id);
    } else {
        $url = route('documents.serve', ['guid' => $file->guid, 'type' => 'documents', 'extension' => $extension]);
    }
    $response = $this->get($url)->assertOk()->assertHeader('Content-Type', $mime)
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Content-Security-Policy', "sandbox; default-src 'none'; form-action 'none'; base-uri 'none'");
    expect($response->headers->get('Content-Disposition'))->toStartWith($disposition.';');
})->with(['web', 'api', 'public'])->with([
    ['html', 'application/octet-stream', 'attachment'],
    ['pdf', 'application/pdf', 'inline'],
    ['png', 'image/png', 'inline'],
]);
