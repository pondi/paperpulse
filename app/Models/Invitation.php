<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Invitation extends Model
{
    use HasUuids;

    protected $fillable = [
        'email',
        'name',
        'company',
        'status',
        'notes',
        'token',
        'expires_at',
        'sent_at',
        'used_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
        'sent_at' => 'datetime',
    ];

    protected $hidden = [
        'token',
    ];

    public static function boot()
    {
        parent::boot();

        static::creating(function ($invitation) {
            // Only generate token and expiry if status is 'sent' (when admin approves)
            // For initial requests, these remain null until approved
            if ($invitation->status === 'sent' && ! $invitation->token) {
                $invitation->token = Str::random(64);
                $invitation->expires_at = now()->addDays(7);
            }
        });
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function isUsed(): bool
    {
        return $this->used_at !== null;
    }

    public function isValid(): bool
    {
        return ! $this->isExpired() && ! $this->isUsed() && $this->status === 'sent';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isSent(): bool
    {
        return $this->status === 'sent';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    public function markAsUsed(): void
    {
        $this->update(['used_at' => now()]);
    }

    public function markAsSent(): void
    {
        $this->update([
            'status' => 'sent',
            'sent_at' => now(),
            'token' => $this->token ?? Str::random(64),
            'expires_at' => $this->expires_at ?? now()->addDays(7),
        ]);
    }

    public function markAsRejected(): void
    {
        $this->update(['status' => 'rejected']);
    }

    public static function createForEmail(string $email): self
    {
        return self::create([
            'email' => $email,
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
