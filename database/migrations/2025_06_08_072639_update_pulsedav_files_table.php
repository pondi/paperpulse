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
        Schema::table('pulsedav_files', function (Blueprint $table) {
            $table->enum('file_type', ['receipt', 'document'])->default('receipt')->after('filename');
            
            // Add index for file type
            $table->index('file_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pulsedav_files', function (Blueprint $table) {
            $table->dropIndex(['file_type']);
            $table->dropColumn('file_type');
        });
    }
};