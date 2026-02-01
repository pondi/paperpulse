<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Entity type to table mapping.
     * file_type value => [table_name, file_id_column]
     */
    private array $entityTypeMap = [
        'receipt' => ['receipts', 'file_id'],
        'document' => ['documents', 'file_id'],
        'voucher' => ['vouchers', 'file_id'],
        'warranty' => ['warranties', 'file_id'],
        'return_policy' => ['return_policies', 'file_id'],
        'invoice' => ['invoices', 'file_id'],
        'contract' => ['contracts', 'file_id'],
        'bank_statement' => ['bank_statements', 'file_id'],
    ];

    /**
     * Run the migrations.
     *
     * Tags were previously associated with entity IDs (receipt.id, document.id, etc.)
     * This migration moves them to be associated with File IDs (files.id) instead.
     * This allows tags to survive entity deletion/recreation during reprocessing.
     */
    public function up(): void
    {
        // Step 1: Create a temporary table to store the migrated data
        $migratedTags = [];

        // Step 2: For each entity type, find the file_id and collect migrated records
        foreach ($this->entityTypeMap as $fileType => [$table, $fileIdColumn]) {
            $records = DB::table('file_tags')
                ->where('file_type', $fileType)
                ->get();

            foreach ($records as $record) {
                // Get the file_id from the entity table
                $entity = DB::table($table)->where('id', $record->file_id)->first();

                if ($entity && $entity->{$fileIdColumn}) {
                    $key = $entity->{$fileIdColumn}.'_'.$record->tag_id;
                    // Only keep one record per file_id + tag_id combination
                    if (! isset($migratedTags[$key])) {
                        $migratedTags[$key] = [
                            'file_id' => $entity->{$fileIdColumn},
                            'tag_id' => $record->tag_id,
                            'created_at' => $record->created_at,
                            'updated_at' => $record->updated_at,
                        ];
                    }
                }
            }
        }

        // Step 3: Drop old constraints and file_type column
        Schema::table('file_tags', function (Blueprint $table) {
            $table->dropUnique(['file_id', 'file_type', 'tag_id']);
            $table->dropIndex(['file_id', 'file_type']);
            $table->dropColumn('file_type');
        });

        // Step 4: Clear the table and insert migrated data
        DB::table('file_tags')->truncate();

        // Step 5: Insert migrated records
        foreach ($migratedTags as $record) {
            DB::table('file_tags')->insert($record);
        }

        // Step 6: Add new constraints
        Schema::table('file_tags', function (Blueprint $table) {
            $table->foreign('file_id')->references('id')->on('files')->cascadeOnDelete();
            $table->unique(['file_id', 'tag_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Step 1: Drop new constraints
        Schema::table('file_tags', function (Blueprint $table) {
            $table->dropForeign(['file_id']);
            $table->dropUnique(['file_id', 'tag_id']);
        });

        // Step 2: Add back file_type column
        Schema::table('file_tags', function (Blueprint $table) {
            $table->string('file_type')->default('receipt')->after('file_id');
        });

        // Note: Data cannot be fully restored as we don't know which entity the tag
        // was originally associated with. Tags will remain associated with files.

        // Step 3: Add back original constraints
        Schema::table('file_tags', function (Blueprint $table) {
            $table->unique(['file_id', 'file_type', 'tag_id']);
            $table->index(['file_id', 'file_type']);
        });
    }
};
