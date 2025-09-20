<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Invitation extends Model
{
    use HasUuids;

    protected $fillable = [
        'email',
        'token',
        'expires_at',
        'invited_by_user_id',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
    ];

    protected $hidden = [
        'token',
    ];

    public static function boot()
    {
        parent::boot();

        static::creating(function ($invitation) {
            $invitation->token = Str::random(64);
            $invitation->expires_at = now()->addDays(7);
        });
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by_user_id');
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isUsed(): bool
    {
        return $this->used_at !== null;
    }

    public function isValid(): bool
    {
        return ! $this->isExpired() && ! $this->isUsed();
    }

    public function markAsUsed(): void
    {
        $this->update(['used_at' => now()]);
    }

    public static function createForEmail(string $email, ?int $invitedByUserId): self
    {
        return self::create([
            'email' => $email,
            'invited_by_user_id' => $invitedByUserId,
        ]);
    }

    public static function findValidByToken(string $token): ?self
    {
        return self::where('token', $token)
            ->where('expires_at', '>', now())
            ->whereNull('used_at')
            ->first();
    }
}
