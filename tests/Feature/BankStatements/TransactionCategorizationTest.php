<?php

use App\Contracts\Services\TextAnalysisContract;
use App\Enums\TransactionCategory;
use App\Models\BankStatement;
use App\Models\BankTransaction;
use App\Models\File;
use App\Models\User;
use App\Services\BankStatements\TransactionCategorizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ==========================================
// TransactionCategory Enum
// ==========================================

it('has 16 transaction category cases', function () {
    expect(TransactionCategory::cases())->toHaveCount(16);
});

it('provides labels for all categories', function () {
    foreach (TransactionCategory::cases() as $case) {
        expect($case->label())->toBeString()->not->toBeEmpty();
    }
});

it('provides subcategories for all groups', function () {
    $subcategories = TransactionCategory::subcategories();

    expect($subcategories)->toHaveCount(16);

    foreach (TransactionCategory::cases() as $case) {
        expect($subcategories)->toHaveKey($case->value);
        expect($subcategories[$case->value])->toBeArray()->not->toBeEmpty();
    }
});

it('can be created from valid string values', function () {
    expect(TransactionCategory::from('food_and_drink'))->toBe(TransactionCategory::FoodAndDrink);
    expect(TransactionCategory::from('entertainment'))->toBe(TransactionCategory::Entertainment);
    expect(TransactionCategory::from('income'))->toBe(TransactionCategory::Income);
});

it('returns null for invalid string values via tryFrom', function () {
    expect(TransactionCategory::tryFrom('invalid_category'))->toBeNull();
    expect(TransactionCategory::tryFrom('NOT_A_CATEGORY'))->toBeNull();
});

// ==========================================
// TransactionCategorizationService
// ==========================================

it('categorizes transactions using AI results', function () {
    $user = User::factory()->create();
    $file = File::factory()->create([
        'user_id' => $user->id,
        'file_type' => 'bank_statement',
    ]);
    $statement = BankStatement::factory()->create([
        'user_id' => $user->id,
        'file_id' => $file->id,
    ]);

    $tx1 = BankTransaction::factory()->create([
        'bank_statement_id' => $statement->id,
        'description' => 'Random Store Purchase',
        'category_group' => null,
    ]);
    $tx2 = BankTransaction::factory()->create([
        'bank_statement_id' => $statement->id,
        'description' => 'Fancy Restaurant',
        'category_group' => null,
    ]);

    $ai = Mockery::mock(TextAnalysisContract::class);
    $ai->shouldReceive('analyze')
        ->once()
        ->andReturn([
            ['id' => $tx1->id, 'category_group' => 'general_merchandise', 'subcategory' => 'Online Shopping'],
            ['id' => $tx2->id, 'category_group' => 'food_and_drink', 'subcategory' => 'Restaurant'],
        ]);
    $ai->shouldReceive('getProviderName')->andReturn('mock');

    $service = new TransactionCategorizationService($ai);
    $service->categorize(collect([$tx1, $tx2]));

    expect($tx1->fresh()->category_group)->toBe(TransactionCategory::GeneralMerchandise);
    expect($tx1->fresh()->subcategory)->toBe('Online Shopping');
    expect($tx2->fresh()->category_group)->toBe(TransactionCategory::FoodAndDrink);
    expect($tx2->fresh()->subcategory)->toBe('Restaurant');
});

it('handles empty transaction collection gracefully', function () {
    $ai = Mockery::mock(TextAnalysisContract::class);
    $ai->shouldNotReceive('analyze');

    $service = new TransactionCategorizationService($ai);
    $service->categorize(collect());

    expect(true)->toBeTrue();
});

it('leaves transactions uncategorized when AI fails', function () {
    $user = User::factory()->create();
    $file = File::factory()->create([
        'user_id' => $user->id,
        'file_type' => 'bank_statement',
    ]);
    $statement = BankStatement::factory()->create([
        'user_id' => $user->id,
        'file_id' => $file->id,
    ]);

    $tx1 = BankTransaction::factory()->create([
        'bank_statement_id' => $statement->id,
        'description' => 'Netflix Monthly Subscription',
        'category_group' => null,
    ]);
    $tx2 = BankTransaction::factory()->create([
        'bank_statement_id' => $statement->id,
        'description' => 'Rema 1000 Grocery Store',
        'category_group' => null,
    ]);

    $ai = Mockery::mock(TextAnalysisContract::class);
    $ai->shouldReceive('analyze')
        ->andThrow(new Exception('API error'));
    $ai->shouldReceive('getProviderName')->andReturn('mock');

    $service = new TransactionCategorizationService($ai);
    $service->categorize(collect([$tx1, $tx2]));

    // AI failed — transactions should remain uncategorized (no keyword fallback)
    expect($tx1->fresh()->category_group)->toBeNull();
    expect($tx2->fresh()->category_group)->toBeNull();
});

it('leaves transactions uncategorized when missing from AI results', function () {
    $user = User::factory()->create();
    $file = File::factory()->create([
        'user_id' => $user->id,
        'file_type' => 'bank_statement',
    ]);
    $statement = BankStatement::factory()->create([
        'user_id' => $user->id,
        'file_id' => $file->id,
    ]);

    $tx1 = BankTransaction::factory()->create([
        'bank_statement_id' => $statement->id,
        'description' => 'Monthly salary deposit',
        'category_group' => null,
    ]);
    $tx2 = BankTransaction::factory()->create([
        'bank_statement_id' => $statement->id,
        'description' => 'Netflix Premium',
        'category_group' => null,
    ]);

    $ai = Mockery::mock(TextAnalysisContract::class);
    $ai->shouldReceive('analyze')
        ->once()
        ->andReturn([
            // Only returns result for tx1, tx2 is missing
            ['id' => $tx1->id, 'category_group' => 'income', 'subcategory' => 'Wages'],
        ]);
    $ai->shouldReceive('getProviderName')->andReturn('mock');

    $service = new TransactionCategorizationService($ai);
    $service->categorize(collect([$tx1, $tx2]));

    expect($tx1->fresh()->category_group)->toBe(TransactionCategory::Income);
    expect($tx1->fresh()->subcategory)->toBe('Wages');
    // tx2 missing from AI results — should remain uncategorized
    expect($tx2->fresh()->category_group)->toBeNull();
});

it('handles invalid AI category values gracefully', function () {
    $user = User::factory()->create();
    $file = File::factory()->create([
        'user_id' => $user->id,
        'file_type' => 'bank_statement',
    ]);
    $statement = BankStatement::factory()->create([
        'user_id' => $user->id,
        'file_id' => $file->id,
    ]);

    $tx = BankTransaction::factory()->create([
        'bank_statement_id' => $statement->id,
        'description' => 'Some transaction',
        'category_group' => null,
    ]);

    $ai = Mockery::mock(TextAnalysisContract::class);
    $ai->shouldReceive('analyze')
        ->once()
        ->andReturn([
            ['id' => $tx->id, 'category_group' => 'invalid_category_value', 'subcategory' => 'Test'],
        ]);
    $ai->shouldReceive('getProviderName')->andReturn('mock');

    $service = new TransactionCategorizationService($ai);
    $service->categorize(collect([$tx]));

    // Invalid enum value — transaction should remain uncategorized
    expect($tx->fresh()->category_group)->toBeNull();
});

it('handles AI returning a single object instead of array', function () {
    $user = User::factory()->create();
    $file = File::factory()->create([
        'user_id' => $user->id,
        'file_type' => 'bank_statement',
    ]);
    $statement = BankStatement::factory()->create([
        'user_id' => $user->id,
        'file_id' => $file->id,
    ]);

    $tx = BankTransaction::factory()->create([
        'bank_statement_id' => $statement->id,
        'description' => 'Grocery purchase',
        'category_group' => null,
    ]);

    $ai = Mockery::mock(TextAnalysisContract::class);
    $ai->shouldReceive('analyze')
        ->once()
        ->andReturn([
            'id' => $tx->id,
            'category_group' => 'food_and_drink',
            'subcategory' => 'Groceries',
        ]);
    $ai->shouldReceive('getProviderName')->andReturn('mock');

    $service = new TransactionCategorizationService($ai);
    $service->categorize(collect([$tx]));

    // AI returned a single object, service wraps it in an array
    expect($tx->fresh()->category_group)->toBe(TransactionCategory::FoodAndDrink);
    expect($tx->fresh()->subcategory)->toBe('Groceries');
});
