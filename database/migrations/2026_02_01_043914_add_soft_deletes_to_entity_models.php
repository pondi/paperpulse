<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tables that need soft deletes for the deletion workflow.
     * deleted_reason tracks why the record was deleted:
     * - reprocess: file is being reprocessed, preserve user data
     * - user_delete: user explicitly deleted the file/entity
     * - account_delete: user account is being deleted
     */
    private array $tables = [
        'files',
        'receipts',
        'documents',
        'line_items',
        'vouchers',
        'warranties',
        'return_policies',
        'invoices',
        'invoice_line_items',
        'contracts',
        'bank_statements',
        'bank_transactions',
        'merchants',
        'vendors',
        'categories',
        'tags',
        'collections',
        'extractable_entities',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (Schema::hasTable($table)) {
                Schema::table($table, function (Blueprint $blueprint) use ($table) {
                    if (! Schema::hasColumn($table, 'deleted_at')) {
                        $blueprint->softDeletes();
                    }
                    if (! Schema::hasColumn($table, 'deleted_reason')) {
                        $blueprint->enum('deleted_reason', ['reprocess', 'user_delete', 'account_delete'])->nullable()->after('deleted_at');
                    }
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach ($this->tables as $table) {
            if (Schema::hasTable($table)) {
                Schema::table($table, function (Blueprint $blueprint) use ($table) {
                    if (Schema::hasColumn($table, 'deleted_reason')) {
                        $blueprint->dropColumn('deleted_reason');
                    }
                    if (Schema::hasColumn($table, 'deleted_at')) {
                        $blueprint->dropSoftDeletes();
                    }
                });
            }
        }
    }
};
