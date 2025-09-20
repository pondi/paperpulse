<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('file_shares', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('file_id');
            $table->string('file_type'); // receipt, document
            $table->foreignId('shared_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('shared_with_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('permission')->default('view'); // view, edit
            $table->timestamp('shared_at')->useCurrent();
            $table->timestamp('expires_at')->nullable();

            $table->index(['file_id', 'file_type']);
            $table->index('shared_with_user_id');
            $table->index('shared_by_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('file_shares');
    }
};
