<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PulseDavFile extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'pulsedav_files';

    protected $fillable = [
        'user_id',
        's3_path',
        'filename',
        'size',
        'status',
        'file_type',
        'uploaded_at',
        'processed_at',
        'error_message',
        'receipt_id',
        'document_id',
    ];

    protected $casts = [
        'uploaded_at' => 'datetime',
        'processed_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Get the user that owns the S3 file.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the receipt associated with this S3 file.
     */
    public function receipt()
    {
        return $this->belongsTo(Receipt::class);
    }

    /**
     * Get the document associated with this S3 file.
     */
    public function document()
    {
        return $this->belongsTo(Document::class);
    }

    /**
     * Scope a query to only include files for a specific user.
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope a query to only include files with a specific status.
     */
    public function scopeWithStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope a query to only include pending files.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Check if the file is processable.
     */
    public function isProcessable()
    {
        return in_array($this->status, ['pending', 'failed']);
    }

    /**
     * Mark the file as processing.
     */
    public function markAsProcessing()
    {
        $this->update(['status' => 'processing']);
    }

    /**
     * Mark the file as completed.
     */
    public function markAsCompleted($relatedId = null, $type = 'receipt')
    {
        $updateData = [
            'status' => 'completed',
            'processed_at' => now(),
            'error_message' => null,
        ];
        
        if ($type === 'receipt') {
            $updateData['receipt_id'] = $relatedId;
        } else {
            $updateData['document_id'] = $relatedId;
        }
        
        $this->update($updateData);
    }

    /**
     * Mark the file as failed.
     */
    public function markAsFailed($errorMessage)
    {
        $this->update([
            'status' => 'failed',
            'error_message' => $errorMessage,
        ]);
    }
}
