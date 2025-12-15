<?php

use App\Models\File;
use App\Models\User;
use App\Services\Files\StoragePathBuilder;
use App\Services\SearchService;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Storage::fake('paperpulse');
    Storage::fake('pulsedav');
});

afterEach(function () {
    \Mockery::close();
});

it('requires authentication for v1 search', function () {
    $this->getJson('/api/v1/search?q=test')
        ->assertStatus(401);
});

it('returns lightweight search results with content links', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $mock = \Mockery::mock(SearchService::class);
    $mock->shouldReceive('search')
        ->once()
        ->andReturn([
            'results' => [
                [
                    'id' => 123,
                    'type' => 'document',
                    'title' => 'Untitled',
                    'description' => 'Some snippet',
                    'date' => '2025-01-01',
                    'filename' => 'mydoc.pdf',
                    'file' => [
                        'id' => 10,
                        'guid' => '2c2d0c66-24f6-4f86-b894-c20dc2d63eaa',
                        'extension' => 'pdf',
                        'has_image_preview' => false,
                        'has_converted_pdf' => true,
                    ],
                ],
                [
                    'id' => 456,
                    'type' => 'receipt',
                    'title' => 'ACME',
                    'description' => 'Milk and eggs',
                    'date' => '2025-01-02',
                    'filename' => 'receipt.jpg',
                    'total' => '12.34 USD',
                    'file' => [
                        'id' => 11,
                        'guid' => 'b1f10574-e0ed-4f24-86e5-57c12ae45a9e',
                        'extension' => 'jpg',
                        'has_image_preview' => true,
                        'has_converted_pdf' => false,
                    ],
                ],
            ],
            'facets' => [
                'total' => 2,
                'receipts' => 1,
                'documents' => 1,
            ],
        ]);

    app()->instance(SearchService::class, $mock);

    $this->getJson('/api/v1/search?q=milk')
        ->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonCount(2, 'data.results')
        ->assertJsonPath('data.results.0.type', 'document')
        ->assertJsonPath('data.results.0.links.content', route('api.files.content', ['file' => 10]))
        ->assertJsonPath('data.results.0.links.preview', null)
        ->assertJsonPath('data.results.0.links.pdf', route('api.files.content', ['file' => 10]).'?variant=archive')
        ->assertJsonPath('data.results.1.type', 'receipt')
        ->assertJsonPath('data.results.1.links.preview', route('api.files.content', ['file' => 11]).'?variant=preview')
        ->assertJsonPath('data.results.1.links.pdf', null);
});

it('streams file content for the owner', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $guid = 'b1f10574-e0ed-4f24-86e5-57c12ae45a9e';
    $path = StoragePathBuilder::storagePath($user->id, $guid, 'receipt', 'original', 'pdf');
    Storage::disk('paperpulse')->put($path, '%PDF-1.4 test');

    $file = File::factory()->create([
        'user_id' => $user->id,
        'guid' => $guid,
        'file_type' => 'receipt',
        'fileExtension' => 'pdf',
        's3_original_path' => $path,
    ]);

    $response = $this->get('/api/v1/files/'.$file->id.'/content');

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/pdf');
    expect($response->streamedContent())->toContain('%PDF-1.4 test');
});

it('does not allow streaming file content for non-owners', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    Sanctum::actingAs($otherUser);

    $file = File::factory()->create([
        'user_id' => $owner->id,
    ]);

    $this->getJson('/api/v1/files/'.$file->id.'/content')
        ->assertStatus(404);
});
