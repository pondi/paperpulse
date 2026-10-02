<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bulk_upload_sessions', function (Blueprint $table): void {
            $table->boolean('cleanup_pending')->default(true)->index();
        });
    }

    public function down(): void
    {
        Schema::table('bulk_upload_sessions', function (Blueprint $table): void {
            $table->dropColumn('cleanup_pending');
        });
    }
};
