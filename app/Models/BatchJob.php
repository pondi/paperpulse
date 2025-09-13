<?php

namespace App\Models;

use App\Traits\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Represents a high-level batch operation and its items with progress.
 *
 * Includes helpers for derived progress metrics and commonly-used scopes.
 */
class BatchJob extends Model
{
    use BelongsToUser;

    protected $fillable = [
        'user_id',
        'type',
        'total_items',
        'processed_items',
        'failed_items',
        'status',
        'options',
        'estimated_cost',
        'actual_cost',
        'started_at',
        'completed_at',
        'error_message',
    ];

    protected $casts = [
        'options' => 'array',
        'estimated_cost' => 'decimal:4',
        'actual_cost' => 'decimal:4',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(BatchItem::class);
    }

    public function getProgressPercentageAttribute(): float
    {
        return $this->total_items > 0
            ? round(($this->processed_items / $this->total_items) * 100, 1)
            : 0;
    }

    public function getSuccessRateAttribute(): float
    {
        return $this->processed_items > 0
            ? round((($this->processed_items - $this->failed_items) / $this->processed_items) * 100, 1)
            : 0;
    }

    public function isComplete(): bool
    {
        return in_array($this->status, ['completed', 'completed_with_errors', 'cancelled']);
    }

    public function isRunning(): bool
    {
        return in_array($this->status, ['queued', 'processing']);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }
}
