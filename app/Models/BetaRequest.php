<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BetaRequest extends Model
{
    use HasUuids;

    protected $fillable = [
        'name',
        'email',
        'company',
        'status',
        'notes',
        'invited_at',
        'invited_by_user_id',
    ];

    protected $casts = [
        'invited_at' => 'datetime',
    ];

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by_user_id');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isInvited(): bool
    {
        return $this->status === 'invited';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    public function markAsInvited(?int $invitedByUserId = null): void
    {
        $this->update([
            'status' => 'invited',
            'invited_at' => now(),
            'invited_by_user_id' => $invitedByUserId,
        ]);
    }

    public function markAsRejected(): void
    {
        $this->update([
            'status' => 'rejected',
        ]);
    }
}
