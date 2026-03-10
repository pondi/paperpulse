<?php

use App\Enums\TransactionCategory;
use App\Models\BankStatement;
use App\Models\BankTransaction;
use App\Models\ExtractableEntity;
use App\Models\File;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

// ==========================================
// Index
// ==========================================

it('renders bank statements index with structured data', function () {
    $user = User::factory()->create();
    $file = File::factory()->create([
        'user_id' => $user->id,
        'file_type' => 'bank_statement',
        'processing_type' => 'bank_statement',
    ]);

    $statement = BankStatement::factory()->create([
        'user_id' => $user->id,
        'file_id' => $file->id,
        'bank_name' => 'DNB',
        'account_holder_name' => 'Ola Nordmann',
        'account_number' => '12345678901',
        'closing_balance' => 15000.50,
        'transaction_count' => 42,
    ]);

    $this->actingAs($user)
        ->get(route('bank-statements.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('BankStatements/Index')
            ->has('statements', 1)
            ->where('statements.0.id', $statement->id)
            ->where('statements.0.bank_name', 'DNB')
            ->where('statements.0.account_holder_name', 'Ola Nordmann')
            ->where('statements.0.closing_balance', '15000.50')
            ->where('statements.0.transaction_count', 42)
        );
});

it('only shows statements for the authenticated user', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $file = File::factory()->create([
        'user_id' => $user->id,
        'file_type' => 'bank_statement',
    ]);
    BankStatement::factory()->create([
        'user_id' => $user->id,
        'file_id' => $file->id,
    ]);

    $otherFile = File::factory()->create([
        'user_id' => $otherUser->id,
        'file_type' => 'bank_statement',
    ]);
    BankStatement::factory()->create([
        'user_id' => $otherUser->id,
        'file_id' => $otherFile->id,
    ]);

    $this->actingAs($user)
        ->get(route('bank-statements.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('BankStatements/Index')
            ->has('statements', 1)
        );
});

it('renders empty state when no bank statements exist', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('bank-statements.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('BankStatements/Index')
            ->has('statements', 0)
        );
});

it('masks account number in index', function () {
    $user = User::factory()->create();
    $file = File::factory()->create([
        'user_id' => $user->id,
        'file_type' => 'bank_statement',
    ]);

    BankStatement::factory()->create([
        'user_id' => $user->id,
        'file_id' => $file->id,
        'account_number' => '12345678901',
    ]);

    $this->actingAs($user)
        ->get(route('bank-statements.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('statements.0.account_number', '*******8901')
        );
});

// ==========================================
// Show
// ==========================================

it('renders bank statement show with full details', function () {
    $user = User::factory()->create();
    $file = File::factory()->create([
        'user_id' => $user->id,
        'file_type' => 'bank_statement',
        'processing_type' => 'bank_statement',
    ]);

    $statement = BankStatement::factory()->create([
        'user_id' => $user->id,
        'file_id' => $file->id,
        'bank_name' => 'Nordea',
    ]);

    BankTransaction::factory()->count(3)->create([
        'bank_statement_id' => $statement->id,
        'user_id' => $user->id,
    ]);

    $this->actingAs($user)
        ->get(route('bank-statements.show', $statement))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('BankStatements/Show')
            ->where('statement.id', $statement->id)
            ->where('statement.bank_name', 'Nordea')
            ->where('statement.file.id', $file->id)
            ->has('statement.transactions', 3)
            ->has('available_tags')
            ->has('category_groups', 16)
        );
});

it('cannot view another users bank statement', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $file = File::factory()->create([
        'user_id' => $otherUser->id,
        'file_type' => 'bank_statement',
    ]);
    $statement = BankStatement::factory()->create([
        'user_id' => $otherUser->id,
        'file_id' => $file->id,
    ]);

    $this->actingAs($user)
        ->get(route('bank-statements.show', $statement))
        ->assertNotFound();
});

// ==========================================
// Update
// ==========================================

it('can update a bank statement', function () {
    $user = User::factory()->create();
    $file = File::factory()->create([
        'user_id' => $user->id,
        'file_type' => 'bank_statement',
    ]);
    $statement = BankStatement::factory()->create([
        'user_id' => $user->id,
        'file_id' => $file->id,
        'bank_name' => 'Old Bank',
    ]);

    $this->actingAs($user)
        ->patch(route('bank-statements.update', $statement), [
            'bank_name' => 'New Bank',
            'account_holder_name' => 'Updated Name',
        ])
        ->assertRedirect();

    expect($statement->fresh())
        ->bank_name->toBe('New Bank')
        ->account_holder_name->toBe('Updated Name');
});

it('cannot update another users bank statement', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $file = File::factory()->create([
        'user_id' => $otherUser->id,
        'file_type' => 'bank_statement',
    ]);
    $statement = BankStatement::factory()->create([
        'user_id' => $otherUser->id,
        'file_id' => $file->id,
    ]);

    $this->actingAs($user)
        ->patch(route('bank-statements.update', $statement), [
            'bank_name' => 'HACKED',
        ])
        ->assertNotFound();
});

// ==========================================
// Delete
// ==========================================

it('can delete a bank statement', function () {
    $user = User::factory()->create();
    $file = File::factory()->create([
        'user_id' => $user->id,
        'file_type' => 'bank_statement',
        'guid' => 'test-guid-bankstatement',
    ]);
    $statement = BankStatement::factory()->create([
        'user_id' => $user->id,
        'file_id' => $file->id,
    ]);

    ExtractableEntity::create([
        'file_id' => $file->id,
        'user_id' => $user->id,
        'entity_type' => 'bank_statement',
        'entity_id' => $statement->id,
        'is_primary' => true,
        'extraction_provider' => 'test',
        'extracted_at' => now(),
    ]);

    $storageService = $this->mock(\App\Services\StorageService::class);
    $storageService->shouldReceive('deleteFile')->once()->andReturn(true);

    $this->actingAs($user)
        ->delete(route('bank-statements.destroy', $statement))
        ->assertRedirect(route('bank-statements.index'));

    expect(BankStatement::withTrashed()->find($statement->id)->trashed())->toBeTrue();
});

// ==========================================
// Download
// ==========================================

it('can download a bank statement file', function () {
    $user = User::factory()->create();
    $file = File::factory()->create([
        'user_id' => $user->id,
        'file_type' => 'bank_statement',
        'guid' => 'test-guid-bs-download',
        'fileExtension' => 'pdf',
    ]);
    $statement = BankStatement::factory()->create([
        'user_id' => $user->id,
        'file_id' => $file->id,
    ]);

    $storageService = $this->mock(\App\Services\StorageService::class);
    $storageService->shouldReceive('getFileByUserAndGuid')
        ->once()
        ->andReturn('fake-pdf-content');

    $this->actingAs($user)
        ->get(route('bank-statements.download', $statement))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/octet-stream')
        ->assertHeader('Content-Disposition');
});

// ==========================================
// Tags
// ==========================================

it('can attach a tag to a bank statement', function () {
    $user = User::factory()->create();
    $file = File::factory()->create([
        'user_id' => $user->id,
        'file_type' => 'bank_statement',
    ]);
    $statement = BankStatement::factory()->create([
        'user_id' => $user->id,
        'file_id' => $file->id,
    ]);

    $this->actingAs($user)
        ->post(route('bank-statements.tags.store', $statement), [
            'name' => 'finance',
        ])
        ->assertRedirect();

    expect($file->fresh()->tags)->toHaveCount(1);
    expect($file->fresh()->tags->first()->name)->toBe('finance');
});

it('can detach a tag from a bank statement', function () {
    $user = User::factory()->create();
    $file = File::factory()->create([
        'user_id' => $user->id,
        'file_type' => 'bank_statement',
    ]);
    $statement = BankStatement::factory()->create([
        'user_id' => $user->id,
        'file_id' => $file->id,
    ]);

    $tag = Tag::create([
        'name' => 'removeme',
        'user_id' => $user->id,
        'slug' => 'removeme-bs',
        'color' => '#ff0000',
    ]);
    $file->tags()->attach($tag);

    $this->actingAs($user)
        ->delete(route('bank-statements.tags.destroy', [$statement, $tag]))
        ->assertRedirect();

    expect($file->fresh()->tags)->toHaveCount(0);
});

// ==========================================
// Transactions Endpoint
// ==========================================

it('returns paginated transactions as json', function () {
    $user = User::factory()->create();
    $file = File::factory()->create([
        'user_id' => $user->id,
        'file_type' => 'bank_statement',
    ]);
    $statement = BankStatement::factory()->create([
        'user_id' => $user->id,
        'file_id' => $file->id,
    ]);

    BankTransaction::factory()->count(5)->create([
        'bank_statement_id' => $statement->id,
        'user_id' => $user->id,
    ]);

    $response = $this->actingAs($user)
        ->getJson(route('bank-statements.transactions', $statement));

    $response->assertOk()
        ->assertJsonStructure([
            'data' => ['data' => [['id', 'transaction_date', 'description', 'amount', 'transaction_type']]],
            'meta' => ['current_page', 'last_page', 'per_page', 'total'],
        ])
        ->assertJsonPath('meta.total', 5);
});

it('filters transactions by type', function () {
    $user = User::factory()->create();
    $file = File::factory()->create([
        'user_id' => $user->id,
        'file_type' => 'bank_statement',
    ]);
    $statement = BankStatement::factory()->create([
        'user_id' => $user->id,
        'file_id' => $file->id,
    ]);

    BankTransaction::factory()->count(3)->create([
        'bank_statement_id' => $statement->id,
        'user_id' => $user->id,
        'transaction_type' => 'credit',
        'amount' => 100,
    ]);
    BankTransaction::factory()->count(2)->create([
        'bank_statement_id' => $statement->id,
        'user_id' => $user->id,
        'transaction_type' => 'debit',
        'amount' => -50,
    ]);

    $response = $this->actingAs($user)
        ->getJson(route('bank-statements.transactions', ['bankStatement' => $statement, 'type' => 'credit']));

    $response->assertOk()
        ->assertJsonPath('meta.total', 3);
});

it('filters transactions by category group', function () {
    $user = User::factory()->create();
    $file = File::factory()->create([
        'user_id' => $user->id,
        'file_type' => 'bank_statement',
    ]);
    $statement = BankStatement::factory()->create([
        'user_id' => $user->id,
        'file_id' => $file->id,
    ]);

    BankTransaction::factory()->count(2)->create([
        'bank_statement_id' => $statement->id,
        'user_id' => $user->id,
        'category_group' => TransactionCategory::FoodAndDrink,
    ]);
    BankTransaction::factory()->create([
        'bank_statement_id' => $statement->id,
        'user_id' => $user->id,
        'category_group' => TransactionCategory::Transportation,
    ]);

    $response = $this->actingAs($user)
        ->getJson(route('bank-statements.transactions', ['bankStatement' => $statement, 'category_group' => 'food_and_drink']));

    $response->assertOk()
        ->assertJsonPath('meta.total', 2);
});

it('searches transactions by description', function () {
    $user = User::factory()->create();
    $file = File::factory()->create([
        'user_id' => $user->id,
        'file_type' => 'bank_statement',
    ]);
    $statement = BankStatement::factory()->create([
        'user_id' => $user->id,
        'file_id' => $file->id,
    ]);

    BankTransaction::factory()->create([
        'bank_statement_id' => $statement->id,
        'user_id' => $user->id,
        'description' => 'Rema 1000 Groenland',
    ]);
    BankTransaction::factory()->create([
        'bank_statement_id' => $statement->id,
        'user_id' => $user->id,
        'description' => 'Spotify Premium',
    ]);

    $response = $this->actingAs($user)
        ->getJson(route('bank-statements.transactions', ['bankStatement' => $statement, 'search' => 'Rema']));

    $response->assertOk()
        ->assertJsonPath('meta.total', 1);
});

it('filters transactions by date range', function () {
    $user = User::factory()->create();
    $file = File::factory()->create([
        'user_id' => $user->id,
        'file_type' => 'bank_statement',
    ]);
    $statement = BankStatement::factory()->create([
        'user_id' => $user->id,
        'file_id' => $file->id,
    ]);

    BankTransaction::factory()->create([
        'bank_statement_id' => $statement->id,
        'user_id' => $user->id,
        'transaction_date' => '2026-01-15',
    ]);
    BankTransaction::factory()->create([
        'bank_statement_id' => $statement->id,
        'user_id' => $user->id,
        'transaction_date' => '2026-02-01',
    ]);

    $response = $this->actingAs($user)
        ->getJson(route('bank-statements.transactions', [
            'bankStatement' => $statement,
            'date_from' => '2026-01-01',
            'date_to' => '2026-01-31',
        ]));

    $response->assertOk()
        ->assertJsonPath('meta.total', 1);
});

it('cannot view transactions for another users statement', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $file = File::factory()->create([
        'user_id' => $otherUser->id,
        'file_type' => 'bank_statement',
    ]);
    $statement = BankStatement::factory()->create([
        'user_id' => $otherUser->id,
        'file_id' => $file->id,
    ]);

    $this->actingAs($user)
        ->getJson(route('bank-statements.transactions', $statement))
        ->assertNotFound();
});

// ==========================================
// Authentication
// ==========================================

it('requires authentication for bank statement routes', function () {
    $this->get(route('bank-statements.index'))->assertRedirect(route('login'));
    $this->get(route('bank-statements.show', 1))->assertRedirect(route('login'));
    $this->getJson(route('bank-statements.transactions', 1))->assertUnauthorized();
});
