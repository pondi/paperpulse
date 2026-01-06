<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('bank_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_statement_id')->constrained()->onDelete('cascade');

            $table->date('transaction_date')->nullable();
            $table->date('posting_date')->nullable();
            $table->text('description')->nullable();
            $table->string('reference')->nullable();

            $table->string('transaction_type', 50)->nullable(); // 'debit', 'credit', 'fee'
            $table->string('category')->nullable(); // Auto-categorized

            $table->decimal('amount', 15, 2)->nullable();
            $table->decimal('balance_after', 15, 2)->nullable();
            $table->string('currency', 3)->default('NOK');

            $table->string('counterparty_name', 500)->nullable();
            $table->string('counterparty_account', 100)->nullable();

            $table->timestamps();

            // Indexes
            $table->index('bank_statement_id', 'idx_bank_transactions_statement');
            $table->index('transaction_date', 'idx_bank_transactions_date');
            $table->index('transaction_type', 'idx_bank_transactions_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bank_transactions');
    }
};
