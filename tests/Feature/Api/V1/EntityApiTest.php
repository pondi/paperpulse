<?php

use App\Models\BankStatement;
use App\Models\BankTransaction;
use App\Models\Contract;
use App\Models\Invoice;
use App\Models\Receipt;
use App\Models\User;
use App\Models\Voucher;
use App\Models\Warranty;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->user = User::factory()->create();
    Sanctum::actingAs($this->user);
});

// --- Receipts ---

describe('receipts', function () {
    it('lists receipts for the authenticated user', function () {
        Receipt::factory()->count(3)->create(['user_id' => $this->user->id]);

        $response = $this->getJson(route('api.receipts.index'));

        $response->assertSuccessful();
        $response->assertJsonCount(3, 'data');
        $response->assertJsonStructure(['pagination' => ['current_page', 'total', 'per_page']]);
    });

    it('does not list other users receipts', function () {
        $other = User::factory()->create();
        Receipt::factory()->count(2)->create(['user_id' => $this->user->id]);
        Receipt::factory()->count(3)->create(['user_id' => $other->id]);

        $response = $this->getJson(route('api.receipts.index'));

        $response->assertSuccessful();
        $response->assertJsonCount(2, 'data');
    });

    it('shows a single receipt with line items', function () {
        $receipt = Receipt::factory()->create(['user_id' => $this->user->id]);

        $response = $this->getJson(route('api.receipts.show', $receipt));

        $response->assertSuccessful();
        $response->assertJsonPath('data.id', $receipt->id);
    });

    it('returns 404 for another users receipt', function () {
        $other = User::factory()->create();
        $receipt = Receipt::factory()->create(['user_id' => $other->id]);

        $response = $this->getJson(route('api.receipts.show', $receipt));

        $response->assertNotFound();
    });

    it('paginates receipts', function () {
        Receipt::factory()->count(30)->create(['user_id' => $this->user->id]);

        $response = $this->getJson(route('api.receipts.index', ['per_page' => 10]));

        $response->assertSuccessful();
        $response->assertJsonCount(10, 'data');
        expect($response->json('pagination.total'))->toBe(30);
    });

    it('requires authentication', function () {
        $this->app['auth']->forgetGuards();

        $response = $this->getJson(route('api.receipts.index'));

        $response->assertUnauthorized();
    });
});

// --- Invoices ---

describe('invoices', function () {
    it('lists invoices for the authenticated user', function () {
        Invoice::factory()->count(3)->create(['user_id' => $this->user->id]);

        $response = $this->getJson(route('api.invoices.index'));

        $response->assertSuccessful();
        $response->assertJsonCount(3, 'data');
    });

    it('does not list other users invoices', function () {
        $other = User::factory()->create();
        Invoice::factory()->count(2)->create(['user_id' => $this->user->id]);
        Invoice::factory()->count(3)->create(['user_id' => $other->id]);

        $response = $this->getJson(route('api.invoices.index'));

        $response->assertSuccessful();
        $response->assertJsonCount(2, 'data');
    });

    it('shows a single invoice', function () {
        $invoice = Invoice::factory()->create(['user_id' => $this->user->id]);

        $response = $this->getJson(route('api.invoices.show', $invoice));

        $response->assertSuccessful();
        $response->assertJsonPath('data.id', $invoice->id);
        $response->assertJsonPath('data.invoice_number', $invoice->invoice_number);
    });

    it('returns 404 for another users invoice', function () {
        $other = User::factory()->create();
        $invoice = Invoice::factory()->create(['user_id' => $other->id]);

        $response = $this->getJson(route('api.invoices.show', $invoice));

        $response->assertNotFound();
    });

    it('filters invoices by payment_status', function () {
        Invoice::factory()->create(['user_id' => $this->user->id, 'payment_status' => 'paid']);
        Invoice::factory()->create(['user_id' => $this->user->id, 'payment_status' => 'unpaid']);

        $response = $this->getJson(route('api.invoices.index', ['payment_status' => 'paid']));

        $response->assertSuccessful();
        $response->assertJsonCount(1, 'data');
    });
});

// --- Contracts ---

describe('contracts', function () {
    it('lists contracts for the authenticated user', function () {
        Contract::factory()->count(3)->create(['user_id' => $this->user->id]);

        $response = $this->getJson(route('api.contracts.index'));

        $response->assertSuccessful();
        $response->assertJsonCount(3, 'data');
    });

    it('does not list other users contracts', function () {
        $other = User::factory()->create();
        Contract::factory()->count(2)->create(['user_id' => $this->user->id]);
        Contract::factory()->count(3)->create(['user_id' => $other->id]);

        $response = $this->getJson(route('api.contracts.index'));

        $response->assertSuccessful();
        $response->assertJsonCount(2, 'data');
    });

    it('shows a single contract', function () {
        $contract = Contract::factory()->create(['user_id' => $this->user->id]);

        $response = $this->getJson(route('api.contracts.show', $contract));

        $response->assertSuccessful();
        $response->assertJsonPath('data.id', $contract->id);
    });

    it('returns 404 for another users contract', function () {
        $other = User::factory()->create();
        $contract = Contract::factory()->create(['user_id' => $other->id]);

        $response = $this->getJson(route('api.contracts.show', $contract));

        $response->assertNotFound();
    });

    it('filters contracts by status', function () {
        Contract::factory()->create(['user_id' => $this->user->id, 'status' => 'active']);
        Contract::factory()->create(['user_id' => $this->user->id, 'status' => 'expired']);

        $response = $this->getJson(route('api.contracts.index', ['status' => 'active']));

        $response->assertSuccessful();
        $response->assertJsonCount(1, 'data');
    });
});

// --- Bank Statements ---

describe('bank statements', function () {
    it('lists bank statements for the authenticated user', function () {
        BankStatement::factory()->count(3)->create(['user_id' => $this->user->id]);

        $response = $this->getJson(route('api.bank-statements.index'));

        $response->assertSuccessful();
        $response->assertJsonCount(3, 'data');
    });

    it('does not list other users bank statements', function () {
        $other = User::factory()->create();
        BankStatement::factory()->count(2)->create(['user_id' => $this->user->id]);
        BankStatement::factory()->count(3)->create(['user_id' => $other->id]);

        $response = $this->getJson(route('api.bank-statements.index'));

        $response->assertSuccessful();
        $response->assertJsonCount(2, 'data');
    });

    it('shows a single bank statement with transactions', function () {
        $statement = BankStatement::factory()->create(['user_id' => $this->user->id]);
        BankTransaction::factory()->count(5)->create([
            'user_id' => $this->user->id,
            'bank_statement_id' => $statement->id,
        ]);

        $response = $this->getJson(route('api.bank-statements.show', $statement));

        $response->assertSuccessful();
        $response->assertJsonPath('data.id', $statement->id);
        $response->assertJsonCount(5, 'data.transactions');
    });

    it('returns 404 for another users bank statement', function () {
        $other = User::factory()->create();
        $statement = BankStatement::factory()->create(['user_id' => $other->id]);

        $response = $this->getJson(route('api.bank-statements.show', $statement));

        $response->assertNotFound();
    });
});

// --- Vouchers ---

describe('vouchers', function () {
    it('lists vouchers for the authenticated user', function () {
        Voucher::factory()->count(3)->create(['user_id' => $this->user->id]);

        $response = $this->getJson(route('api.vouchers.index'));

        $response->assertSuccessful();
        $response->assertJsonCount(3, 'data');
    });

    it('does not list other users vouchers', function () {
        $other = User::factory()->create();
        Voucher::factory()->count(2)->create(['user_id' => $this->user->id]);
        Voucher::factory()->count(3)->create(['user_id' => $other->id]);

        $response = $this->getJson(route('api.vouchers.index'));

        $response->assertSuccessful();
        $response->assertJsonCount(2, 'data');
    });

    it('shows a single voucher', function () {
        $voucher = Voucher::factory()->create(['user_id' => $this->user->id]);

        $response = $this->getJson(route('api.vouchers.show', $voucher));

        $response->assertSuccessful();
        $response->assertJsonPath('data.id', $voucher->id);
    });

    it('returns 404 for another users voucher', function () {
        $other = User::factory()->create();
        $voucher = Voucher::factory()->create(['user_id' => $other->id]);

        $response = $this->getJson(route('api.vouchers.show', $voucher));

        $response->assertNotFound();
    });

    it('filters vouchers by type', function () {
        Voucher::factory()->create(['user_id' => $this->user->id, 'voucher_type' => 'gift_card']);
        Voucher::factory()->create(['user_id' => $this->user->id, 'voucher_type' => 'coupon']);

        $response = $this->getJson(route('api.vouchers.index', ['voucher_type' => 'gift_card']));

        $response->assertSuccessful();
        $response->assertJsonCount(1, 'data');
    });
});

// --- Warranties ---

describe('warranties', function () {
    it('lists warranties for the authenticated user', function () {
        Warranty::factory()->count(3)->create(['user_id' => $this->user->id]);

        $response = $this->getJson(route('api.warranties.index'));

        $response->assertSuccessful();
        $response->assertJsonCount(3, 'data');
    });

    it('does not list other users warranties', function () {
        $other = User::factory()->create();
        Warranty::factory()->count(2)->create(['user_id' => $this->user->id]);
        Warranty::factory()->count(3)->create(['user_id' => $other->id]);

        $response = $this->getJson(route('api.warranties.index'));

        $response->assertSuccessful();
        $response->assertJsonCount(2, 'data');
    });

    it('shows a single warranty', function () {
        $warranty = Warranty::factory()->create(['user_id' => $this->user->id]);

        $response = $this->getJson(route('api.warranties.show', $warranty));

        $response->assertSuccessful();
        $response->assertJsonPath('data.id', $warranty->id);
    });

    it('returns 404 for another users warranty', function () {
        $other = User::factory()->create();
        $warranty = Warranty::factory()->create(['user_id' => $other->id]);

        $response = $this->getJson(route('api.warranties.show', $warranty));

        $response->assertNotFound();
    });

    it('filters warranties by type', function () {
        Warranty::factory()->create(['user_id' => $this->user->id, 'warranty_type' => 'manufacturer']);
        Warranty::factory()->create(['user_id' => $this->user->id, 'warranty_type' => 'extended']);

        $response = $this->getJson(route('api.warranties.index', ['warranty_type' => 'manufacturer']));

        $response->assertSuccessful();
        $response->assertJsonCount(1, 'data');
    });
});
