<?php

use App\Models\BankStatement;
use App\Models\BankTransaction;
use App\Models\Contract;
use App\Models\Document;
use App\Models\File;
use App\Models\FileProcessingAnalytic;
use App\Models\Invoice;
use App\Models\Merchant;
use App\Models\Receipt;
use App\Models\User;
use App\Services\Analytics\ProcessingAnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->withoutVite());

it('requires authentication', function () {
    $this->get(route('analytics.index'))
        ->assertRedirect(route('login'));
});

it('defaults to overview tab with all-time period', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('analytics.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Analytics/Dashboard')
            ->where('current_tab', 'overview')
            ->where('current_period', 'all')
            ->has('overview_counts')
            ->has('tab_data')
        );
});

it('shows overview counts for all entity types', function () {
    $user = User::factory()->create();

    Receipt::factory()->count(2)->create(['user_id' => $user->id]);
    Invoice::factory()->create(['user_id' => $user->id]);
    Contract::factory()->create(['user_id' => $user->id]);
    Document::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->get(route('analytics.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('overview_counts.receipts', 2)
            ->where('overview_counts.invoices', 1)
            ->where('overview_counts.contracts', 1)
            ->where('overview_counts.documents', 1)
        );
});

it('isolates data by user', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    Receipt::factory()->count(3)->create(['user_id' => $user->id]);
    Receipt::factory()->count(5)->create(['user_id' => $other->id]);

    $this->actingAs($user)
        ->get(route('analytics.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('overview_counts.receipts', 3)
        );
});

// --- Overview Tab ---

it('shows financial totals on overview tab', function () {
    $user = User::factory()->create();

    Receipt::factory()->create([
        'user_id' => $user->id,
        'receipt_date' => now()->subMonths(3),
        'currency' => 'NOK', 'total_amount' => 250.00,
    ]);

    Invoice::factory()->create([
        'user_id' => $user->id,
        'invoice_date' => now()->subMonths(2),
        'currency' => 'NOK', 'total_amount' => 500.00,
    ]);

    $this->actingAs($user)
        ->get(route('analytics.index', ['tab' => 'overview']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('tab_data.financial_totals.receipts', 250)
            ->where('tab_data.financial_totals.invoices', 500)
            ->has('tab_data.expiring_soon')
            ->has('tab_data.monthly_trend')
        );
});

// --- Receipts Tab ---

it('shows receipt analytics with all-time data', function () {
    $user = User::factory()->create();
    $merchant = Merchant::create(['name' => 'Test Store', 'user_id' => $user->id]);
    $file = File::factory()->create([
        'user_id' => $user->id,
        'file_type' => 'receipt',
        'processing_type' => 'receipt',
    ]);

    Receipt::factory()->create([
        'user_id' => $user->id,
        'file_id' => $file->id,
        'merchant_id' => $merchant->id,
        'receipt_date' => now()->subMonths(6),
        'currency' => 'NOK', 'total_amount' => 100.00,
        'tax_amount' => 10.00,
        'receipt_category' => 'Groceries',
    ]);

    $this->actingAs($user)
        ->get(route('analytics.index', ['tab' => 'receipts']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('current_tab', 'receipts')
            ->where('tab_data.stats.count', 1)
            ->where('tab_data.stats.total', 100)
            ->has('tab_data.spending_by_category', 1)
            ->has('tab_data.top_merchants', 1)
            ->has('tab_data.monthly_trend', 1)
            ->has('tab_data.day_of_week', 7)
        );
});

it('filters receipts by month period', function () {
    $user = User::factory()->create();

    Receipt::factory()->create([
        'user_id' => $user->id,
        'receipt_date' => now()->subDays(5),
        'currency' => 'NOK', 'total_amount' => 50.00,
        'receipt_category' => 'Food',
    ]);

    Receipt::factory()->create([
        'user_id' => $user->id,
        'receipt_date' => now()->subMonths(6),
        'currency' => 'NOK', 'total_amount' => 200.00,
        'receipt_category' => 'Electronics',
    ]);

    $this->actingAs($user)
        ->get(route('analytics.index', ['tab' => 'receipts', 'period' => 'month']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('current_period', 'month')
            ->where('tab_data.stats.count', 1)
            ->where('tab_data.stats.total', 50)
            ->has('tab_data.spending_by_category', 1)
        );
});

// --- Invoices Tab ---

it('shows invoice analytics with recipient breakdown', function () {
    $user = User::factory()->create();

    Invoice::factory()->create([
        'user_id' => $user->id,
        'invoice_date' => now()->subDays(10),
        'currency' => 'NOK', 'total_amount' => 1000.00,
        'to_name' => 'Acme Corp',
    ]);

    Invoice::factory()->create([
        'user_id' => $user->id,
        'invoice_date' => now()->subDays(5),
        'currency' => 'NOK', 'total_amount' => 500.00,
        'to_name' => 'Acme Corp',
    ]);

    Invoice::factory()->create([
        'user_id' => $user->id,
        'invoice_date' => now()->subDays(3),
        'currency' => 'NOK', 'total_amount' => 300.00,
        'to_name' => 'Beta LLC',
    ]);

    $this->actingAs($user)
        ->get(route('analytics.index', ['tab' => 'invoices']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('current_tab', 'invoices')
            ->where('tab_data.stats.count', 3)
            ->where('tab_data.stats.total', 1800)
            ->where('tab_data.stats.avg', 600)
            ->where('tab_data.stats.recipient_count', 2)
            ->has('tab_data.top_recipients', 2)
            ->has('tab_data.monthly_trend')
        );
});

// --- Banking Tab ---

it('shows banking analytics with statement data', function () {
    $user = User::factory()->create();

    $statement = BankStatement::factory()->create([
        'user_id' => $user->id,
        'statement_date' => now()->subDays(15),
        'opening_balance' => 1000.00,
        'closing_balance' => 1500.00,
        'total_credits' => 2000.00,
        'total_debits' => 1500.00,
    ]);

    BankTransaction::factory()->create(['user_id' => $user->id, 'bank_statement_id' => $statement->id, 'transaction_date' => now()->subDays(15), 'currency' => 'NOK', 'amount' => 2000, 'balance_after' => null]);
    BankTransaction::factory()->create(['user_id' => $user->id, 'bank_statement_id' => $statement->id, 'transaction_date' => now()->subDays(14), 'currency' => 'NOK', 'amount' => -1500, 'balance_after' => 1500]);

    $this->actingAs($user)
        ->get(route('analytics.index', ['tab' => 'banking']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('current_tab', 'banking')
            ->where('tab_data.stats.statement_count', 1)
            ->where('tab_data.stats.total_credits', 2000)
            ->where('tab_data.stats.total_debits', 1500)
            ->where('tab_data.stats.net_flow', 500)
            ->has('tab_data.balance_trend', 1)
        );
});

// --- Contracts Tab ---

it('shows contract analytics with status breakdown', function () {
    $user = User::factory()->create();

    Contract::factory()->create([
        'user_id' => $user->id,
        'status' => 'active',
        'contract_type' => 'service',
        'currency' => 'NOK', 'contract_value' => 50000.00,
        'effective_date' => now()->subMonths(3),
        'expiry_date' => now()->addMonths(9),
    ]);

    Contract::factory()->create([
        'user_id' => $user->id,
        'status' => 'expired',
        'contract_type' => 'rental',
        'currency' => 'NOK', 'contract_value' => 12000.00,
        'effective_date' => now()->subYears(2),
        'expiry_date' => now()->subMonths(1),
    ]);

    $this->actingAs($user)
        ->get(route('analytics.index', ['tab' => 'contracts']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('current_tab', 'contracts')
            ->where('tab_data.stats.total', 2)
            ->where('tab_data.stats.active', 1)
            ->has('tab_data.status_breakdown')
            ->has('tab_data.type_distribution')
        );
});

// --- Documents Tab ---

it('shows document analytics with type distribution', function () {
    $user = User::factory()->create();

    Document::factory()->create([
        'user_id' => $user->id,
        'document_type' => 'report',
        'page_count' => 5,
    ]);

    Document::factory()->create([
        'user_id' => $user->id,
        'document_type' => 'letter',
        'page_count' => 2,
    ]);

    $this->actingAs($user)
        ->get(route('analytics.index', ['tab' => 'documents']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('current_tab', 'documents')
            ->where('tab_data.stats.total', 2)
            ->where('tab_data.stats.total_pages', 7)
            ->has('tab_data.type_distribution', 2)
            ->has('tab_data.monthly_trend')
        );
});

// --- Empty States ---

it('returns empty data gracefully for all tabs', function (string $tab) {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('analytics.index', ['tab' => $tab]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Analytics/Dashboard')
            ->where('current_tab', $tab)
            ->has('tab_data')
        );
})->with(['overview', 'receipts', 'invoices', 'banking', 'contracts', 'documents']);

it('uses transaction boundaries categories and positive spending magnitudes for banking analytics', function () {
    $this->travelTo(Carbon\Carbon::parse('2026-03-31 12:00:00'));
    $user = User::factory()->create();
    $statement = BankStatement::factory()->create(['user_id' => $user->id, 'statement_date' => '2025-01-01', 'total_debits' => 9999]);
    foreach ([
        ['2026-02-27', -999, 'food_and_drink', 'Groceries', 'Excluded'],
        ['2026-02-28', -200, 'food_and_drink', 'Groceries', 'Large shop'],
        ['2026-03-01', -10, 'entertainment', 'Games', 'Small shop'],
        ['2026-03-31', 50, 'food_and_drink', 'Groceries', 'Large shop'],
        ['2026-04-01', -888, 'food_and_drink', 'Groceries', 'Future'],
    ] as [$date, $amount, $group, $subcategory, $counterparty]) {
        BankTransaction::factory()->create(['user_id' => $user->id, 'bank_statement_id' => $statement->id,
            'transaction_date' => $date, 'currency' => 'NOK', 'amount' => $amount, 'category' => 'Legacy incorrect category',
            'category_group' => $group, 'subcategory' => $subcategory, 'counterparty_name' => $counterparty]);
    }
    $this->actingAs($user)->get(route('analytics.index', ['tab' => 'banking', 'period' => 'month']))
        ->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('tab_data.stats.statement_count', 1)
        ->where('tab_data.stats.transaction_count', 3)
        ->where('tab_data.stats.total_credits', 50)
        ->where('tab_data.stats.total_debits', 210)
        ->where('tab_data.stats.net_flow', -160)
        ->where('tab_data.spending_by_category.0.category_group', 'food_and_drink')
        ->where('tab_data.spending_by_category.0.subcategory', 'Groceries')
        ->where('tab_data.spending_by_category.0.total', 200)
        ->where('tab_data.spending_by_category.1.total', 10)
        ->where('tab_data.top_counterparties.0.name', 'Large shop')
        ->where('tab_data.top_counterparties.0.total', 200));
});

it('renders processing analytics with PostgreSQL warning arrays', function (): void {
    $admin = User::factory()->create(['is_admin' => true]);
    $this->actingAs($admin)->get(route('analytics.processing'))->assertOk();

    foreach ([null, [], ['Missing invoice number']] as $warnings) {
        FileProcessingAnalytic::create([
            'file_id' => File::factory()->create(['user_id' => $admin->id])->id,
            'user_id' => $admin->id,
            'processing_type' => 'invoice',
            'document_type' => 'invoice',
            'processing_status' => 'completed',
            'classification_confidence' => 0.95,
            'validation_warnings' => $warnings,
        ]);
    }

    $this->get(route('analytics.processing'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Analytics/Processing')
            ->where('qualityMetrics.invoice.total_extractions', 3)
            ->where('qualityMetrics.invoice.extractions_with_warnings', 1));
});

it('loads processing analytic file identities and retains records for missing files', function (): void {
    $admin = User::factory()->create(['is_admin' => true]);
    $this->actingAs($admin);
    $owner = User::factory()->create();
    $file = File::factory()->create(['user_id' => $owner->id, 'fileName' => 'source-invoice.pdf']);
    $analytic = FileProcessingAnalytic::create([
        'file_id' => $file->id,
        'user_id' => $owner->id,
        'processing_type' => 'invoice',
        'document_type' => 'invoice',
        'processing_status' => 'completed',
        'classification_confidence' => 0.5,
        'validation_warnings' => ['Missing invoice number'],
    ]);
    $service = app(ProcessingAnalyticsService::class);

    expect($service->findLowConfidenceClassifications()->sole()->file->fileName)->toBe('source-invoice.pdf')
        ->and($service->getValidationWarningsByType('invoice')->sole()->file->guid)->toBe($file->guid);
    $this->get(route('analytics.processing'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('lowConfidence.0.filename', 'source-invoice.pdf'));

    expect(File::query()->find($file->id))->toBeNull();
    $this->actingAs($owner)->get(route('analytics.processing'))->assertForbidden();
    $this->actingAs($admin);
    $file->delete();

    foreach ([$service->findLowConfidenceClassifications(), $service->getValidationWarningsByType('invoice')] as $records) {
        expect($records->sole()->id)->toBe($analytic->id)->and($records->sole()->file)->toBeNull();
    }
    $this->get(route('analytics.processing'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('lowConfidence.0.filename', 'N/A'));
});
