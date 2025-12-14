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
        Schema::table('invitations', function (Blueprint $table) {
            // Add fields from BetaRequest to consolidate the two systems
            $table->string('name')->nullable()->after('email');
            $table->string('company')->nullable()->after('name');
            $table->string('status')->default('pending')->after('company'); // pending, sent, rejected
            $table->text('notes')->nullable()->after('status');

            // Make token nullable since it's only generated when invitation is sent
            $table->string('token')->nullable()->change();

            // Rename invited_at for clarity (when invitation was sent)
            $table->timestamp('sent_at')->nullable()->after('notes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invitations', function (Blueprint $table) {
            $table->dropColumn(['name', 'company', 'status', 'notes', 'sent_at']);

            // Revert token to non-nullable
            $table->string('token')->nullable(false)->change();
        });
    }
};
