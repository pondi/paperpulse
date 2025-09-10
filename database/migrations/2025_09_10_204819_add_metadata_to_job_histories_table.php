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
        Schema::table('job_history', function (Blueprint $table) {
            $table->json('metadata')->nullable()->after('exception');
            $table->string('file_name')->nullable()->after('metadata');
            $table->string('file_type')->nullable()->after('file_name');
            $table->integer('file_id')->nullable()->after('file_type');
            
            $table->index('file_id');
            $table->index('file_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('job_history', function (Blueprint $table) {
            $table->dropColumn(['metadata', 'file_name', 'file_type', 'file_id']);
        });
    }
};