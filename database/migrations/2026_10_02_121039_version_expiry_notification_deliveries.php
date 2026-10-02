<?php

use App\Models\NotificationHistory;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notification_history', function (Blueprint $table) {
            $table->dropUnique('notification_history_unique');
            $table->timestamp('notified_at')->nullable()->change();
            $table->string('expiry_version')->default('legacy');
            $table->string('status')->default('sent');
            $table->json('delivered_channels')->nullable();
            $table->string('error')->nullable();
            $table->unique(['user_id', 'notification_type', 'entity_type', 'entity_id', 'expiry_version'], 'notification_history_unique');
        });
        NotificationHistory::query()->chunkById(100, function ($histories): void {
            foreach ($histories as $history) {
                $expiry = $history->meta['expiry_date'] ?? $history->meta['warranty_end_date'] ?? null;
                if ($expiry) {
                    $history->update(['expiry_version' => $expiry.':30']);
                }
            }
        });
    }

    public function down(): void
    {
        NotificationHistory::query()->whereNull('notified_at')->delete();
        $seen = [];
        NotificationHistory::query()->orderByDesc('id')->each(function (NotificationHistory $history) use (&$seen): void {
            $key = implode(':', [$history->user_id, $history->notification_type, $history->entity_type, $history->entity_id]);
            if (isset($seen[$key])) {
                $history->delete();
            }
            $seen[$key] = true;
        });
        Schema::table('notification_history', function (Blueprint $table) {
            $table->dropUnique('notification_history_unique');
            $table->dropColumn(['expiry_version', 'status', 'delivered_channels', 'error']);
            $table->timestamp('notified_at')->nullable(false)->change();
            $table->unique(['user_id', 'notification_type', 'entity_type', 'entity_id'], 'notification_history_unique');
        });
    }
};
