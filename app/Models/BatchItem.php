<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BatchItem extends Model
{
    protected $fillable = [
        'batch_job_id',
        'item_index',
        'source',
        'type',
        'options',
        'status',
        'result',
        'error_message',
        'processing_time',
        'cost',
        'retries',
        'processed_at',
    ];

    protected $casts = [
        'options' => 'array',
        'result' => 'array',
        'cost' => 'decimal:4',
        'processing_time' => 'integer',
        'processed_at' => 'datetime',
    ];

    public function batchJob(): BelongsTo
    {
        return $this->belongsTo(BatchJob::class);
    }

    public function isComplete(): bool
    {
        return in_array($this->status, ['completed', 'failed']);
    }

    public function isSuccessful(): bool
    {
        return $this->status === 'completed';
    }

    public function markAsCompleted(array $result, float $cost = 0, int $processingTime = 0): void
    {
        $this->update([
            'status' => 'completed',
            'result' => $result,
            'cost' => $cost,
            'processing_time' => $processingTime,
            'processed_at' => now(),
        ]);
    }

    public function markAsFailed(string $error, int $processingTime = 0): void
    {
        $this->update([
            'status' => 'failed',
            'error_message' => $error,
            'processing_time' => $processingTime,
            'processed_at' => now(),
        ]);
    }

    public function incrementRetries(): void
    {
        $this->increment('retries');
    }
}
