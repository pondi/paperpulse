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
        Schema::table('receipts', function (Blueprint $table) {
            $table->json('tags')->nullable()->after('status');
            $table->json('shared_with')->nullable()->after('tags');
            $table->json('ai_entities')->nullable()->after('shared_with');
            $table->string('language', 10)->nullable()->after('ai_entities');
            
            // Add index for language for filtering
            $table->index('language');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('receipts', function (Blueprint $table) {
            $table->dropIndex(['language']);
            
            $table->dropColumn(['tags', 'shared_with', 'ai_entities', 'language']);
        });
    }
};