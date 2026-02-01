<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Notes were previously stored on entity models (Receipt, Document).
     * This migration moves them to the File model to survive entity
     * deletion/recreation during reprocessing.
     */
    public function up(): void
    {
        // Step 1: Add note column to files table
        Schema::table('files', function (Blueprint $table) {
            $table->text('note')->nullable()->after('status');
        });

        // Step 2: Migrate notes from receipts to files
        $receipts = DB::table('receipts')
            ->whereNotNull('note')
            ->where('note', '!=', '')
            ->get(['id', 'file_id', 'note']);

        foreach ($receipts as $receipt) {
            if ($receipt->file_id) {
                // Only update if file doesn't already have a note
                DB::table('files')
                    ->where('id', $receipt->file_id)
                    ->whereNull('note')
                    ->update(['note' => $receipt->note]);
            }
        }

        // Step 3: Migrate notes from documents to files
        $documents = DB::table('documents')
            ->whereNotNull('note')
            ->where('note', '!=', '')
            ->get(['id', 'file_id', 'note']);

        foreach ($documents as $document) {
            if ($document->file_id) {
                // Only update if file doesn't already have a note
                DB::table('files')
                    ->where('id', $document->file_id)
                    ->whereNull('note')
                    ->update(['note' => $document->note]);
            }
        }

        // Step 4: Remove note column from entity tables
        Schema::table('receipts', function (Blueprint $table) {
            $table->dropColumn('note');
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn('note');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Step 1: Add note column back to entity tables
        Schema::table('receipts', function (Blueprint $table) {
            $table->text('note')->nullable()->after('receipt_description');
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->text('note')->nullable()->after('description');
        });

        // Step 2: Migrate notes back to entities
        $files = DB::table('files')
            ->whereNotNull('note')
            ->where('note', '!=', '')
            ->get(['id', 'note']);

        foreach ($files as $file) {
            // Try to update receipt first
            $updated = DB::table('receipts')
                ->where('file_id', $file->id)
                ->update(['note' => $file->note]);

            // If no receipt, try document
            if (! $updated) {
                DB::table('documents')
                    ->where('file_id', $file->id)
                    ->update(['note' => $file->note]);
            }
        }

        // Step 3: Remove note column from files table
        Schema::table('files', function (Blueprint $table) {
            $table->dropColumn('note');
        });
    }
};
