<?php

namespace App\Models;

use App\Jobs\Notifications\DeliverExpiryReminder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationHistory extends Model
{
    use HasFactory;

    protected $table = 'notification_history';

    protected $fillable = [
        'user_id',
        'notification_type',
        'entity_type',
        'entity_id',
        'notified_at',
        'meta',
        'expiry_version',
        'status',
        'delivered_channels',
        'error',
    ];

    protected $casts = [
        'notified_at' => 'datetime',
        'meta' => 'array',
        'delivered_channels' => 'array',
    ];

    public static function queueReminder(User $user, string $type, int $entityId, string $expiryDate, int $window, int $daysRemaining): bool
    {
        return (new self)->getConnection()->transaction(function () use ($user, $type, $entityId, $expiryDate, $window, $daysRemaining): bool {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $key = ['user_id' => $user->id, 'notification_type' => $type === 'voucher' ? 'voucher_expiring' : 'warranty_ending',
                'entity_type' => $type, 'entity_id' => $entityId];
            $version = $expiryDate.':'.$window;
            self::query()->where($key)->where('expiry_version', '!=', $version)->update(['status' => 'obsolete']);
            $history = self::query()->firstOrCreate([...$key, 'expiry_version' => $version], [
                'status' => 'queued', 'meta' => ['expiry_date' => $expiryDate, 'days_remaining' => $daysRemaining],
            ]);
            if (! $history->wasRecentlyCreated && $history->status !== 'failed') {
                return false;
            }
            $history->update(['status' => 'queued', 'error' => null]);
            DeliverExpiryReminder::dispatch($history->id)->afterCommit();

            return true;
        });
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
