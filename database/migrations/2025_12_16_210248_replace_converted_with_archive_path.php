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
     * Replace s3_converted_path with s3_archive_path to better reflect
     * that this field stores PDF/A-2b archival versions for long-term storage.
     */
    public function up(): void
    {
        // Add new s3_archive_path column
        Schema::table('files', function (Blueprint $table) {
            $table->string('s3_archive_path', 512)->nullable()->after('s3_processed_path');
        });

        // Migrate existing data from s3_converted_path to s3_archive_path
        DB::statement('UPDATE files SET s3_archive_path = s3_converted_path WHERE s3_converted_path IS NOT NULL');

        // Drop the old s3_converted_path column
        Schema::table('files', function (Blueprint $table) {
            $table->dropColumn('s3_converted_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Re-add s3_converted_path column
        Schema::table('files', function (Blueprint $table) {
            $table->string('s3_converted_path', 512)->nullable()->after('s3_processed_path');
        });

        // Migrate data back from s3_archive_path to s3_converted_path
        DB::statement('UPDATE files SET s3_converted_path = s3_archive_path WHERE s3_archive_path IS NOT NULL');

        // Drop s3_archive_path column
        Schema::table('files', function (Blueprint $table) {
            $table->dropColumn('s3_archive_path');
        });
    }
};
