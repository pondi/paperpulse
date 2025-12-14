<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FileConversion extends Model
{
    use HasFactory;

    protected $fillable = [
        'file_id',
        'user_id',
        'status',
        'input_extension',
        'input_s3_path',
        'output_s3_path',
        'retry_count',
        'max_retries',
        'error_message',
        'metadata',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * Get the file that owns the conversion.
     */
    public function file(): BelongsTo
    {
        return $this->belongsTo(File::class);
    }

    /**
     * Get the user that owns the conversion.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if conversion is pending.
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Check if conversion is processing.
     */
    public function isProcessing(): bool
    {
        return $this->status === 'processing';
    }

    /**
     * Check if conversion is completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Check if conversion has failed.
     */
    public function hasFailed(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * Mark conversion as processing.
     */
    public function markAsProcessing(): void
    {
        $this->update([
            'status' => 'processing',
            'started_at' => now(),
        ]);
    }

    /**
     * Mark conversion as completed.
     */
    public function markAsCompleted(string $outputPath): void
    {
        $this->update([
            'status' => 'completed',
            'output_s3_path' => $outputPath,
            'completed_at' => now(),
        ]);
    }

    /**
     * Mark conversion as failed.
     */
    public function markAsFailed(string $errorMessage): void
    {
        $this->update([
            'status' => 'failed',
            'error_message' => $errorMessage,
            'completed_at' => now(),
        ]);
    }

    /**
     * Increment retry count.
     */
    public function incrementRetryCount(): void
    {
        $this->increment('retry_count');
    }

    /**
     * Check if conversion can be retried.
     */
    public function canRetry(): bool
    {
        return $this->retry_count < $this->max_retries;
    }
}
