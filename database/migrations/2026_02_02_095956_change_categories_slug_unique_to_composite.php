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
        Schema::table('categories', function (Blueprint $table) {
            // Drop the global unique constraint on slug
            $table->dropUnique(['slug']);

            // Add composite unique constraint on user_id and slug
            $table->unique(['user_id', 'slug']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            // Drop the composite unique constraint
            $table->dropUnique(['user_id', 'slug']);

            // Restore the global unique constraint on slug
            $table->unique(['slug']);
        });
    }
};
