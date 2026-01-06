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
        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('file_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            // Contract identification
            $table->string('contract_number')->nullable();
            $table->string('contract_title', 500)->nullable();
            $table->string('contract_type', 100)->nullable(); // 'employment', 'service', 'rental'

            // Parties
            $table->json('parties')->nullable(); // Array of party objects

            // Dates
            $table->date('effective_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->date('signature_date')->nullable();

            // Terms
            $table->string('duration', 100)->nullable(); // "12 months", "5 years"
            $table->text('renewal_terms')->nullable();
            $table->text('termination_conditions')->nullable();

            // Financial
            $table->decimal('contract_value', 15, 2)->nullable();
            $table->string('currency', 3)->default('NOK');
            $table->json('payment_schedule')->nullable(); // Array of payment milestones

            // Legal
            $table->string('governing_law')->nullable(); // "Norwegian Law"
            $table->string('jurisdiction')->nullable();

            // Status
            $table->string('status', 50)->nullable(); // 'draft', 'active', 'expired'

            // Additional
            $table->json('key_terms')->nullable(); // Extracted important clauses
            $table->json('obligations')->nullable(); // Party obligations
            $table->text('summary')->nullable(); // AI-generated summary
            $table->json('contract_data')->nullable(); // Full extraction

            $table->timestamps();

            // Indexes
            $table->index(['user_id', 'contract_type'], 'idx_contracts_user_type');
            $table->index('status', 'idx_contracts_status');
            $table->index('expiry_date', 'idx_contracts_expiry');
            $table->index('contract_number', 'idx_contracts_contract_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contracts');
    }
};
