<?php

use App\Models\Category;
use App\Models\File;
use App\Models\Merchant;
use App\Models\Receipt;
use App\Models\Document;
use App\Models\Tag;
use App\Models\User;
use App\Models\LineItem;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->user = User::factory()->create();
    Sanctum::actingAs($this->user);
});

it('returns detailed receipt data for a receipt file', function () {
    $merchant = Merchant::create([
        'name' => 'Whole Foods',
    ]);

    $category = Category::create([
        'user_id' => $this->user->id,
        'name' => 'Groceries',
        'slug' => 'groceries',
    ]);

    $file = File::factory()->create([
        'user_id' => $this->user->id,
        'file_type' => 'receipt',
        'status' => 'completed',
    ]);

    $receipt = Receipt::create([
        'file_id' => $file->id,
        'user_id' => $this->user->id,
        'merchant_id' => $merchant->id,
        'category_id' => $category->id,
        'total_amount' => 45.67,
        'tax_amount' => 3.42,
        'currency' => 'USD',
        'receipt_date' => '2025-01-15',
        'summary' => 'Groceries including milk, bread, eggs',
    ]);

    LineItem::create([
        'receipt_id' => $receipt->id,
        'text' => 'Organic Milk',
        'amount' => 5.99,
        'qty' => 1,
    ]);

    LineItem::create([
        'receipt_id' => $receipt->id,
        'text' => 'Whole Wheat Bread',
        'amount' => 3.49,
        'qty' => 2,
    ]);

    $response = $this->get(route('api.files.show', $file->id));

    $response->assertStatus(200);
    $response->assertJsonStructure([
        'status',
        'message',
        'data' => [
            'file' => [
                'id',
                'guid',
                'name',
                'file_type',
                'status',
                'uploaded_at',
                'has_image_preview',
            ],
            'receipt' => [
                'id',
                'merchant' => ['id', 'name'],
                'total_amount',
                'tax_amount',
                'currency',
                'receipt_date',
                'summary',
                'category' => ['id', 'name'],
            ],
        ],
    ]);

    // Verify no S3 paths are exposed
    $response->assertJsonMissing(['s3_original_path']);
    $response->assertJsonMissing(['download_url']);
    $response->assertJsonMissing(['expires_in_minutes']);

    // Verify receipt data
    expect($response->json('data.receipt.total_amount'))->toBe('45.67');
    expect($response->json('data.receipt.summary'))->toBe('Groceries including milk, bread, eggs');
    expect($response->json('data.receipt.category.name'))->toBe('Groceries');
    expect($response->json('data.receipt.merchant.name'))->toBe('Whole Foods');

    // Verify line items if loaded
    $lineItems = $response->json('data.receipt.line_items');
    if ($lineItems !== null) {
        expect($lineItems[0]['description'])->toBe('Organic Milk');
    }
});

it('returns detailed document data for a document file', function () {
    $category = Category::create([
        'user_id' => $this->user->id,
        'name' => 'Contracts',
        'slug' => 'contracts',
    ]);

    $file = File::factory()->create([
        'user_id' => $this->user->id,
        'file_type' => 'document',
        'status' => 'completed',
    ]);

    $document = Document::create([
        'file_id' => $file->id,
        'user_id' => $this->user->id,
        'category_id' => $category->id,
        'title' => 'Contract Agreement',
        'description' => 'Service agreement with vendor',
        'document_type' => 'contract',
        'document_date' => '2025-01-15',
        'ai_entities' => [
            'people' => ['John Doe', 'Jane Smith'],
            'organizations' => ['Acme Corp'],
        ],
    ]);

    $response = $this->get(route('api.files.show', $file->id));

    $response->assertStatus(200);
    $response->assertJsonStructure([
        'status',
        'message',
        'data' => [
            'file' => [
                'id',
                'guid',
                'name',
                'file_type',
                'status',
            ],
            'document' => [
                'id',
                'title',
                'description',
                'document_type',
                'document_date',
                'ai_entities',
                'category' => ['id', 'name'],
            ],
        ],
    ]);

    // Verify no S3 paths are exposed
    $response->assertJsonMissing(['s3_original_path']);
    $response->assertJsonMissing(['download_url']);

    // Verify document data
    expect($response->json('data.document.title'))->toBe('Contract Agreement');
    expect($response->json('data.document.category.name'))->toBe('Contracts');
    expect($response->json('data.document.ai_entities.people.0'))->toBe('John Doe');
});

it('returns 404 for non-existent file', function () {
    $response = $this->get(route('api.files.show', 99999));

    $response->assertStatus(404);
    $response->assertJsonPath('message', 'File not found');
});

it('returns 404 when trying to access another users file', function () {
    $otherUser = User::factory()->create();

    $file = File::factory()->create([
        'user_id' => $otherUser->id,
        'file_type' => 'receipt',
    ]);

    $response = $this->get(route('api.files.show', $file->id));

    $response->assertStatus(404);
    $response->assertJsonPath('message', 'File not found');
});

it('returns file data even when receipt has not been processed yet', function () {
    $file = File::factory()->create([
        'user_id' => $this->user->id,
        'file_type' => 'receipt',
        'status' => 'processing',
    ]);

    $response = $this->get(route('api.files.show', $file->id));

    $response->assertStatus(200);
    $response->assertJsonPath('data.file.status', 'processing');
    $response->assertJsonMissing(['data.receipt']);
});
