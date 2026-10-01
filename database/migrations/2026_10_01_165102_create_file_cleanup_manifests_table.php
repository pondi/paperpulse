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
        Schema::create('file_cleanup_manifests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('file_id')->unique();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('reason');
            $table->json('objects');
            $table->json('search_records');
            $table->timestamp('available_at')->nullable()->index();
            $table->timestamp('completed_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('file_cleanup_manifests');
    }
};
