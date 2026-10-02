<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exchange_rate_observations', function (Blueprint $table): void {
            $table->id();
            $table->string('provider');
            $table->char('currency', 3);
            $table->date('observation_date');
            $table->decimal('rate', 20, 10);
            $table->unsignedInteger('units');
            $table->timestamps();
            $table->unique(['provider', 'currency', 'observation_date'], 'exchange_rate_observation_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exchange_rate_observations');
    }
};
