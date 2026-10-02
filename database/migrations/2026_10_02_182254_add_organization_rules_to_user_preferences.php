<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_preferences', function (Blueprint $table): void {
            $table->json('organization_naming_rules')->nullable();
            $table->timestamp('organization_feedback_reset_at', 6)->nullable();
        });
        Schema::table('organization_recommendations', fn (Blueprint $table) => $table->timestamp('decided_at', 6)->nullable()->change());
    }

    public function down(): void
    {
        Schema::table('user_preferences', fn (Blueprint $table) => $table->dropColumn(['organization_naming_rules', 'organization_feedback_reset_at']));
    }
};
