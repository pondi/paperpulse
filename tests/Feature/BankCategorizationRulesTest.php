<?php

use App\Contracts\Services\TextAnalysisContract;
use App\Enums\TransactionCategory;
use App\Models\BankCategorizationRule;
use App\Models\BankTransaction;
use App\Models\User;
use App\Services\BankStatements\TransactionCategorizationService;

it('reuses normalized categorization rules only within the same user and preserves existing categories', function () {
    $user = User::factory()->create();
    $first = BankTransaction::factory()->create(['user_id' => $user->id, 'description' => 'SHOP ref123', 'counterparty_name' => null, 'currency' => 'NOK', 'transaction_type' => 'debit', 'amount' => -10, 'category_group' => null]);
    $ai = $this->mock(TextAnalysisContract::class, function ($mock) use ($first): void {
        $mock->shouldReceive('analyze')->once()->andReturn(['transactions' => [['id' => $first->id, 'category_group' => 'food_and_drink', 'subcategory' => 'Groceries']]]);
    });
    $service = new TransactionCategorizationService($ai);
    $service->categorize(collect([$first]));
    $second = BankTransaction::factory()->create(['user_id' => $user->id, 'description' => 'shop REF456', 'counterparty_name' => null, 'currency' => 'NOK', 'transaction_type' => 'debit', 'amount' => -20, 'category_group' => null]);
    $service->categorize(collect([$first, $second]));
    expect($first->fresh()->category_group)->toBe(TransactionCategory::FoodAndDrink)
        ->and($first->fresh()->category_source)->toBe('ai')->and($second->fresh()->category_source)->toBe('rule')
        ->and(BankCategorizationRule::where('user_id', $user->id)->count())->toBe(1);
    $other = BankTransaction::factory()->create(['user_id' => User::factory()->create()->id, 'description' => 'shop REF456', 'counterparty_name' => null, 'currency' => 'NOK', 'transaction_type' => 'debit', 'amount' => -20, 'category_group' => null]);
    $otherAi = $this->mock(TextAnalysisContract::class, function ($mock) use ($other): void {
        $mock->shouldReceive('analyze')->once()->andReturn(['transactions' => [['id' => $other->id, 'category_group' => 'entertainment', 'subcategory' => null]]]);
    });
    (new TransactionCategorizationService($otherAi))->categorize(collect([$other]));
    expect($other->fresh()->category_group)->toBe(TransactionCategory::Entertainment);
});

it('remembers owner corrections and prevents an in-flight AI response from overwriting them', function () {
    $user = User::factory()->create();
    $transaction = BankTransaction::factory()->create(['user_id' => $user->id, 'category_group' => null]);
    $this->actingAs($user);
    $ai = $this->mock(TextAnalysisContract::class, function ($mock) use ($transaction): void {
        $mock->shouldReceive('analyze')->once()->andReturnUsing(function () use ($transaction): array {
            $transaction->update(['category_group' => TransactionCategory::FoodAndDrink, 'subcategory' => 'Groceries']);

            return ['transactions' => [['id' => $transaction->id, 'category_group' => 'entertainment', 'subcategory' => null]]];
        });
    });
    (new TransactionCategorizationService($ai))->categorize(collect([$transaction]));
    expect($transaction->fresh()->category_group)->toBe(TransactionCategory::FoodAndDrink)
        ->and($transaction->fresh()->category_source)->toBe('manual')->and(BankCategorizationRule::firstOrFail()->source)->toBe('manual');
});

it('leaves transactions unchanged when returned groups or subcategories are invalid', function () {
    $transaction = BankTransaction::factory()->create(['category_group' => null]);
    $ai = $this->mock(TextAnalysisContract::class, function ($mock) use ($transaction): void {
        $mock->shouldReceive('analyze')->once()->andReturn(['transactions' => [['id' => $transaction->id, 'category_group' => 'food_and_drink', 'subcategory' => 'Invalid']]]);
    });
    (new TransactionCategorizationService($ai))->categorize(collect([$transaction]));
    expect($transaction->fresh()->category_group)->toBeNull()->and(BankCategorizationRule::count())->toBe(0);
});
