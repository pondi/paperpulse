<?php

use App\Models\UserPreference;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('weekly_summary_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->string('status')->default('queued');
            $table->timestamps();
            $table->unique(['user_id', 'period_start', 'period_end'], 'weekly_summary_user_period_unique');
        });
        UserPreference::query()->where('email_weekly_summary', true)->update(['email_notify_weekly_summary' => true]);
        Schema::table('user_preferences', fn (Blueprint $table) => $table->dropColumn('email_weekly_summary'));
    }

    public function down(): void
    {
        Schema::table('user_preferences', fn (Blueprint $table) => $table->boolean('email_weekly_summary')->default(false));
        Schema::dropIfExists('weekly_summary_deliveries');
    }
};
