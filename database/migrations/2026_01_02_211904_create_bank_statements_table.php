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
        Schema::create('bank_statements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('file_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            // Account info
            $table->string('bank_name')->nullable();
            $table->string('account_holder_name', 500)->nullable();
            $table->string('account_number', 100)->nullable();
            $table->string('iban', 100)->nullable();
            $table->string('swift_code', 20)->nullable();

            // Statement period
            $table->date('statement_date')->nullable();
            $table->date('statement_period_start')->nullable();
            $table->date('statement_period_end')->nullable();

            // Balances
            $table->decimal('opening_balance', 15, 2)->nullable();
            $table->decimal('closing_balance', 15, 2)->nullable();
            $table->string('currency', 3)->default('NOK');

            // Summary
            $table->decimal('total_credits', 15, 2)->nullable();
            $table->decimal('total_debits', 15, 2)->nullable();
            $table->integer('transaction_count')->nullable();

            $table->json('statement_data')->nullable(); // Full extraction

            $table->timestamps();

            // Indexes
            $table->index(['user_id', 'statement_date'], 'idx_bank_statements_user_date');
            $table->index('account_number', 'idx_bank_statements_account');
            $table->index(['statement_period_start', 'statement_period_end'], 'idx_bank_statements_period');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bank_statements');
    }
};
