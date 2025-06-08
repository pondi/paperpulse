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
        Schema::table('documents', function (Blueprint $table) {
            if (!Schema::hasColumn('documents', 'description')) {
                $table->text('description')->nullable()->after('title');
            }
            if (!Schema::hasColumn('documents', 'extracted_text')) {
                $table->json('extracted_text')->nullable()->after('content');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            if (Schema::hasColumn('documents', 'description')) {
                $table->dropColumn('description');
            }
            if (Schema::hasColumn('documents', 'extracted_text')) {
                $table->dropColumn('extracted_text');
            }
        });
    }
};