<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('bank_transactions', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
        });

        // Backfill user_id from parent bank_statements
        DB::statement('
            UPDATE bank_transactions
            SET user_id = (
                SELECT user_id FROM bank_statements
                WHERE bank_statements.id = bank_transactions.bank_statement_id
            )
            WHERE user_id IS NULL
        ');

        // Make non-nullable after backfill
        Schema::table('bank_transactions', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable(false)->change();
            $table->index(['user_id', 'transaction_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bank_transactions', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'transaction_date']);
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
