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
            if (!Schema::hasColumn('files', 'status')) {
                $table->enum('status', ['pending', 'processing', 'completed', 'failed'])
                    ->default('pending')
                    ->after('processing_type');
                $table->index('status');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('files', function (Blueprint $table) {
            if (Schema::hasColumn('files', 'status')) {
                $table->dropIndex(['status']);
                $table->dropColumn('status');
            }
        });
    }
};