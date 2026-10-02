<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pulsedav_files', function (Blueprint $table): void {
            $table->foreignId('file_id')->nullable()->constrained('files')->nullOnDelete();
            $table->uuid('job_id')->nullable()->index();
            $table->timestamp('claim_until')->nullable();
        });
        Schema::table('pulsedav_import_batches', function (Blueprint $table): void {
            $table->unsignedInteger('completed_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->timestamp('notified_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('pulsedav_files', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('file_id');
            $table->dropColumn(['job_id', 'claim_until']);
        });
        Schema::table('pulsedav_import_batches', function (Blueprint $table): void {
            $table->dropColumn(['completed_count', 'failed_count', 'notified_at']);
        });
    }
};
