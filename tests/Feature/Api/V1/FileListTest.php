<?php

use App\Models\Category;
use App\Models\Document;
use App\Models\File;
use App\Models\Merchant;
use App\Models\Receipt;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->user = User::factory()->create();
    Sanctum::actingAs($this->user);
});

it('lists all files for the authenticated user', function () {
    File::factory()->count(3)->create([
        'user_id' => $this->user->id,
        'file_type' => 'receipt',
    ]);

    File::factory()->count(2)->create([
        'user_id' => $this->user->id,
        'file_type' => 'document',
    ]);

    $response = $this->get(route('api.files.index'));

    $response->assertStatus(200);
    $response->assertJsonCount(5, 'data');
    expect($response->json('data.0.checksum_sha256'))->not->toBeNull();
});

it('filters files by type receipt', function () {
    File::factory()->count(3)->create([
        'user_id' => $this->user->id,
        'file_type' => 'receipt',
    ]);

    File::factory()->count(2)->create([
        'user_id' => $this->user->id,
        'file_type' => 'document',
    ]);

    $response = $this->get(route('api.files.index', ['file_type' => 'receipt']));

    $response->assertStatus(200);
    $response->assertJsonCount(3, 'data');

    $fileTypes = collect($response->json('data'))->pluck('file_type')->unique();
    expect($fileTypes->toArray())->toBe(['receipt']);
});

it('filters files by type document', function () {
    File::factory()->count(3)->create([
        'user_id' => $this->user->id,
        'file_type' => 'receipt',
    ]);

    File::factory()->count(2)->create([
        'user_id' => $this->user->id,
        'file_type' => 'document',
    ]);

    $response = $this->get(route('api.files.index', ['file_type' => 'document']));

    $response->assertStatus(200);
    $response->assertJsonCount(2, 'data');

    $fileTypes = collect($response->json('data'))->pluck('file_type')->unique();
    expect($fileTypes->toArray())->toBe(['document']);
});

it('filters files by status', function () {
    File::factory()->count(2)->create([
        'user_id' => $this->user->id,
        'status' => 'completed',
    ]);

    File::factory()->create([
        'user_id' => $this->user->id,
        'status' => 'pending',
    ]);

    File::factory()->create([
        'user_id' => $this->user->id,
        'status' => 'failed',
    ]);

    $response = $this->get(route('api.files.index', ['status' => 'completed']));

    $response->assertStatus(200);
    $response->assertJsonCount(2, 'data');

    $statuses = collect($response->json('data'))->pluck('status')->unique();
    expect($statuses->toArray())->toBe(['completed']);
});

it('combines file type and status filters', function () {
    File::factory()->count(2)->create([
        'user_id' => $this->user->id,
        'file_type' => 'receipt',
        'status' => 'completed',
    ]);

    File::factory()->create([
        'user_id' => $this->user->id,
        'file_type' => 'receipt',
        'status' => 'pending',
    ]);

    File::factory()->create([
        'user_id' => $this->user->id,
        'file_type' => 'document',
        'status' => 'completed',
    ]);

    $response = $this->get(route('api.files.index', [
        'file_type' => 'receipt',
        'status' => 'completed',
    ]));

    $response->assertStatus(200);
    $response->assertJsonCount(2, 'data');

    $data = collect($response->json('data'));
    expect($data->every(fn ($file) => $file['file_type'] === 'receipt'))->toBeTrue();
    expect($data->every(fn ($file) => $file['status'] === 'completed'))->toBeTrue();
});

it('validates file_type parameter', function () {
    $response = $this->getJson(route('api.files.index', ['file_type' => 'invalid']));

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['file_type']);
});

it('validates status parameter', function () {
    $response = $this->getJson(route('api.files.index', ['status' => 'invalid']));

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['status']);
});

it('respects per_page parameter', function () {
    File::factory()->count(20)->create([
        'user_id' => $this->user->id,
    ]);

    $response = $this->getJson(route('api.files.index', ['per_page' => 5]));

    $response->assertStatus(200);
    $response->assertJsonCount(5, 'data');
    expect($response->json('pagination.per_page'))->toBe(5);
});

it('enforces max per_page limit', function () {
    $response = $this->getJson(route('api.files.index', ['per_page' => 200]));

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['per_page']);
});

it('validates page parameter must be positive integer', function () {
    $response = $this->getJson(route('api.files.index', ['page' => 0]));
    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['page']);

    $response = $this->getJson(route('api.files.index', ['page' => -1]));
    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['page']);

    $response = $this->getJson(route('api.files.index', ['page' => 'invalid']));
    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['page']);
});

it('navigates between pages correctly', function () {
    File::factory()->count(25)->create([
        'user_id' => $this->user->id,
    ]);

    // First page
    $response = $this->getJson(route('api.files.index', ['per_page' => 10, 'page' => 1]));
    $response->assertStatus(200);
    $response->assertJsonCount(10, 'data');
    expect($response->json('pagination.current_page'))->toBe(1);
    expect($response->json('pagination.last_page'))->toBe(3);
    expect($response->json('pagination.total'))->toBe(25);
    expect($response->json('links.prev'))->toBeNull();
    expect($response->json('links.next'))->not->toBeNull();

    // Second page
    $response = $this->getJson(route('api.files.index', ['per_page' => 10, 'page' => 2]));
    $response->assertStatus(200);
    $response->assertJsonCount(10, 'data');
    expect($response->json('pagination.current_page'))->toBe(2);
    expect($response->json('links.prev'))->not->toBeNull();
    expect($response->json('links.next'))->not->toBeNull();

    // Last page
    $response = $this->getJson(route('api.files.index', ['per_page' => 10, 'page' => 3]));
    $response->assertStatus(200);
    $response->assertJsonCount(5, 'data');
    expect($response->json('pagination.current_page'))->toBe(3);
    expect($response->json('links.prev'))->not->toBeNull();
    expect($response->json('links.next'))->toBeNull();
});

it('does not show other users files', function () {
    $otherUser = User::factory()->create();

    File::factory()->count(3)->create([
        'user_id' => $this->user->id,
    ]);

    File::factory()->count(5)->create([
        'user_id' => $otherUser->id,
    ]);

    $response = $this->get(route('api.files.index'));

    $response->assertStatus(200);
    $response->assertJsonCount(3, 'data');
});

it('includes receipt metadata for list view', function () {
    $category = Category::create([
        'user_id' => $this->user->id,
        'name' => 'Food',
        'slug' => Category::generateUniqueSlug('Food', $this->user->id),
        'color' => '#10B981',
        'is_active' => true,
    ]);

    $merchant = Merchant::create([
        'name' => 'Acme Store',
    ]);

    $file = File::factory()->create([
        'user_id' => $this->user->id,
        'file_type' => 'receipt',
        'processing_type' => 'receipt',
        'status' => 'completed',
        'fileExtension' => 'pdf',
    ]);

    Receipt::factory()->create([
        'user_id' => $this->user->id,
        'file_id' => $file->id,
        'merchant_id' => $merchant->id,
        'category_id' => $category->id,
        'receipt_date' => '2025-01-01',
        'total_amount' => 123.45,
        'currency' => 'USD',
        'note' => 'Lunch with client',
    ]);

    $response = $this->getJson(route('api.files.index', ['file_type' => 'receipt']));

    $response->assertStatus(200);
    $response->assertJsonCount(1, 'data');
    $response->assertJsonPath('data.0.title', 'Acme Store');
    $response->assertJsonPath('data.0.total', '123.45');
    $response->assertJsonPath('data.0.currency', 'USD');
    $response->assertJsonPath('data.0.receipt.merchant.name', 'Acme Store');
    $response->assertJsonPath('data.0.receipt.category.name', 'Food');
    $response->assertJsonPath('data.0.links.content', route('api.files.content', ['file' => $file->id]));
});

it('includes document metadata for list view', function () {
    $category = Category::create([
        'user_id' => $this->user->id,
        'name' => 'Finance',
        'slug' => Category::generateUniqueSlug('Finance', $this->user->id),
        'color' => '#EF4444',
        'is_active' => true,
    ]);

    $file = File::factory()->create([
        'user_id' => $this->user->id,
        'file_type' => 'document',
        'processing_type' => 'document',
        'status' => 'completed',
        'fileExtension' => 'pdf',
    ]);

    Document::factory()->create([
        'user_id' => $this->user->id,
        'file_id' => $file->id,
        'category_id' => $category->id,
        'title' => 'Invoice May',
        'document_type' => 'invoice',
        'document_date' => '2025-05-04',
        'page_count' => 2,
        'summary' => 'Monthly invoice',
    ]);

    $response = $this->getJson(route('api.files.index', ['file_type' => 'document']));

    $response->assertStatus(200);
    $response->assertJsonCount(1, 'data');
    $response->assertJsonPath('data.0.title', 'Invoice May');
    $response->assertJsonPath('data.0.document_type', 'invoice');
    $response->assertJsonPath('data.0.page_count', 2);
    $response->assertJsonPath('data.0.document.category.name', 'Finance');
    $response->assertJsonPath('data.0.links.content', route('api.files.content', ['file' => $file->id]));
});
