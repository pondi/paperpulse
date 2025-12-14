<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * These columns conflict with the tags() and sharedUsers() relationships
     * defined via the TaggableModel and ShareableModel traits.
     * Tags are stored in the file_tags pivot table, not as JSON columns.
     * Shared users are stored in the file_shares table.
     */
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn(['tags', 'shared_with']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->json('tags')->nullable();
            $table->json('shared_with')->nullable();
        });
    }
};
