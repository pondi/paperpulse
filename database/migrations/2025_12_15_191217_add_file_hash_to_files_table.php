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
        Schema::table('files', function (Blueprint $table) {
            // Add SHA-256 hash column for file deduplication
            $table->string('file_hash', 64)->nullable()->after('guid');

            // Add composite index for efficient duplicate lookups
            $table->index(['user_id', 'file_hash'], 'files_user_id_file_hash_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('files', function (Blueprint $table) {
            // Drop the index first
            $table->dropIndex('files_user_id_file_hash_index');

            // Then drop the column
            $table->dropColumn('file_hash');
        });
    }
};
