<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Production analytics for AI document processing.
 *
 * Tracks classification confidence, extraction quality, and failure patterns
 * to enable data-driven prompt refinement and extractor creation.
 */
class FileProcessingAnalytic extends Model
{
    protected $fillable = [
        'file_id',
        'user_id',
        'processing_type',
        'processing_status',
        'processing_duration_ms',
        'model_used',
        'document_type',
        'classification_confidence',
        'classification_reasoning',
        'detected_entities',
        'extraction_confidence',
        'validation_warnings',
        'failure_category',
        'error_message',
        'is_retryable',
    ];

    protected function casts(): array
    {
        return [
            'classification_confidence' => 'decimal:4',
            'extraction_confidence' => 'decimal:4',
            'detected_entities' => 'array',
            'validation_warnings' => 'array',
            'is_retryable' => 'boolean',
            'processing_duration_ms' => 'integer',
        ];
    }

    public function file(): BelongsTo
    {
        return $this->belongsTo(File::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope: Unknown document types that need extractors.
     */
    public function scopeUnknownTypes($query)
    {
        return $query->where('document_type', 'unknown')
            ->where('processing_status', 'failed')
            ->where('failure_category', 'classification_unknown_type');
    }

    /**
     * Scope: Low confidence classifications.
     */
    public function scopeLowConfidence($query, float $threshold = 0.7)
    {
        return $query->where('classification_confidence', '<', $threshold)
            ->whereNotNull('classification_confidence');
    }

    /**
     * Scope: Validation failures by extractor type.
     */
    public function scopeValidationFailures($query)
    {
        return $query->where('processing_status', 'failed')
            ->where('failure_category', 'extraction_validation_failed');
    }

    /**
     * Scope: Successful extractions for a document type.
     */
    public function scopeSuccessfulType($query, string $type)
    {
        return $query->where('document_type', $type)
            ->where('processing_status', 'completed');
    }
}
