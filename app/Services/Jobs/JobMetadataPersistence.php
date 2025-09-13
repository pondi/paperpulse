<?php

namespace App\Services\Jobs;

use App\Models\JobHistory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Handles persistent storage and retrieval of job metadata.
 * 
 * Provides a dual-layer storage strategy using both cache (for performance)
 * and database (for persistence) to ensure job metadata is never lost.
 */
class JobMetadataPersistence
{
    /**
     * Store job metadata in both cache and database.
     */
    public static function store(string $jobId, array $metadata): void
    {
        // Store in cache for immediate access
        Cache::put(
            "job.{$jobId}.fileMetaData",
            $metadata,
            now()->addHours(4)
        );
        
        // Also store persistently in database
        JobHistory::where('uuid', $jobId)->update([
            'metadata' => $metadata,
        ]);
        
        Log::debug('[JobMetadataPersistence] Metadata stored', [
            'job_id' => $jobId,
        ]);
    }
    
    /**
     * Retrieve job metadata from cache or database.
     * 
     * @return array|null The metadata array or null if not found
     */
    public static function retrieve(string $jobId): ?array
    {
        // Try cache first for performance
        $metadata = Cache::get("job.{$jobId}.fileMetaData");
        
        if ($metadata) {
            return $metadata;
        }
        
        // Fallback to database if cache miss
        $parentJob = JobHistory::where('uuid', $jobId)->first();
        
        if ($parentJob && $parentJob->metadata) {
            // Re-populate cache for future requests
            Cache::put(
                "job.{$jobId}.fileMetaData",
                $parentJob->metadata,
                now()->addHours(4)
            );
            
            Log::info('[JobMetadataPersistence] Metadata loaded from database', [
                'job_id' => $jobId,
            ]);
            
            return $parentJob->metadata;
        }
        
        Log::warning('[JobMetadataPersistence] No metadata found', [
            'job_id' => $jobId,
        ]);
        
        return null;
    }
}