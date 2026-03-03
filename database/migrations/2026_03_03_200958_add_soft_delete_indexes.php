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
        // User-scoped tables: compound index on (user_id, deleted_at)
        $userScopedTables = [
            'files',
            'receipts',
            'documents',
            'invoices',
            'contracts',
            'bank_statements',
            'bank_transactions',
            'merchants',
            'categories',
            'tags',
            'collections',
            'vouchers',
            'warranties',
            'return_policies',
            'extractable_entities',
        ];

        foreach ($userScopedTables as $table) {
            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                $blueprint->index(['user_id', 'deleted_at'], "idx_{$table}_user_deleted");
            });
        }

        // Child tables: compound index on (parent FK, deleted_at)
        Schema::table('line_items', function (Blueprint $table) {
            $table->index(['receipt_id', 'deleted_at'], 'idx_line_items_receipt_deleted');
        });

        Schema::table('invoice_line_items', function (Blueprint $table) {
            $table->index(['invoice_id', 'deleted_at'], 'idx_invoice_line_items_invoice_deleted');
        });

        // Vendors: no user_id, simple deleted_at index
        Schema::table('vendors', function (Blueprint $table) {
            $table->index('deleted_at', 'idx_vendors_deleted');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $userScopedTables = [
            'files',
            'receipts',
            'documents',
            'invoices',
            'contracts',
            'bank_statements',
            'bank_transactions',
            'merchants',
            'categories',
            'tags',
            'collections',
            'vouchers',
            'warranties',
            'return_policies',
            'extractable_entities',
        ];

        foreach ($userScopedTables as $table) {
            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                $blueprint->dropIndex("idx_{$table}_user_deleted");
            });
        }

        Schema::table('line_items', function (Blueprint $table) {
            $table->dropIndex('idx_line_items_receipt_deleted');
        });

        Schema::table('invoice_line_items', function (Blueprint $table) {
            $table->dropIndex('idx_invoice_line_items_invoice_deleted');
        });

        Schema::table('vendors', function (Blueprint $table) {
            $table->dropIndex('idx_vendors_deleted');
        });
    }
};
