<?php

namespace App\Services\Analytics;

use App\Models\FileProcessingAnalytic;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Analytics service for AI document processing.
 *
 * Provides queries to identify missing extractors, fine-tune prompts,
 * and track extraction quality from production data.
 */
class ProcessingAnalyticsService
{
    /**
     * Find unknown document types that need new extractors.
     *
     * Returns grouped data showing how many files failed classification
     * with "unknown" type, along with the AI's reasoning.
     *
     * @return Collection
     */
    public function findUnknownDocumentTypes(int $limit = 20)
    {
        return FileProcessingAnalytic::unknownTypes()
            ->select(
                'classification_reasoning',
                DB::raw('COUNT(*) as count'),
                DB::raw('MIN(created_at) as first_seen'),
                DB::raw('MAX(created_at) as last_seen')
            )
            ->groupBy('classification_reasoning')
            ->orderByDesc('count')
            ->limit($limit)
            ->get();
    }

    /**
     * Find low-confidence classifications that may need prompt refinement.
     *
     * @param  float  $threshold  Confidence threshold (default 0.7)
     * @return Collection
     */
    public function findLowConfidenceClassifications(float $threshold = 0.7, int $limit = 50)
    {
        return FileProcessingAnalytic::lowConfidence($threshold)
            ->with('file:id,filename,guid')
            ->select(
                'id',
                'file_id',
                'document_type',
                'classification_confidence',
                'classification_reasoning',
                'created_at'
            )
            ->orderBy('classification_confidence')
            ->limit($limit)
            ->get();
    }

    /**
     * Find validation failures by extractor type.
     *
     * Helps identify which extractors have validation issues
     * that need schema/prompt refinement.
     *
     * @return Collection
     */
    public function findValidationFailuresByType(int $limit = 20)
    {
        return FileProcessingAnalytic::validationFailures()
            ->select(
                'document_type',
                DB::raw('COUNT(*) as failure_count'),
                DB::raw('AVG(classification_confidence) as avg_classification_confidence'),
                DB::raw('MIN(created_at) as first_failure'),
                DB::raw('MAX(created_at) as last_failure')
            )
            ->groupBy('document_type')
            ->orderByDesc('failure_count')
            ->limit($limit)
            ->get();
    }

    /**
     * Get document type distribution with success rates.
     *
     * Shows how many files of each type are being processed
     * and their success/failure rates.
     *
     * @return Collection
     */
    public function getDocumentTypeDistribution()
    {
        return FileProcessingAnalytic::query()
            ->select(
                'document_type',
                DB::raw('COUNT(*) as total_count'),
                DB::raw('SUM(CASE WHEN processing_status = \'completed\' THEN 1 ELSE 0 END) as success_count'),
                DB::raw('SUM(CASE WHEN processing_status = \'failed\' THEN 1 ELSE 0 END) as failure_count'),
                DB::raw('AVG(classification_confidence) as avg_confidence'),
                DB::raw('AVG(extraction_confidence) as avg_extraction_confidence'),
                DB::raw('AVG(processing_duration_ms) as avg_duration_ms')
            )
            ->whereNotNull('document_type')
            ->groupBy('document_type')
            ->orderByDesc('total_count')
            ->get();
    }

    /**
     * Get failure distribution by category.
     *
     * Shows common failure patterns to prioritize fixes.
     *
     * @return Collection
     */
    public function getFailureDistribution()
    {
        return FileProcessingAnalytic::query()
            ->where('processing_status', 'failed')
            ->select(
                'failure_category',
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(CASE WHEN is_retryable = true THEN 1 ELSE 0 END) as retryable_count'),
                DB::raw('MIN(created_at) as first_seen'),
                DB::raw('MAX(created_at) as last_seen')
            )
            ->whereNotNull('failure_category')
            ->groupBy('failure_category')
            ->orderByDesc('count')
            ->get();
    }

    /**
     * Get extraction quality metrics for a document type.
     *
     * Provides detailed statistics for fine-tuning a specific extractor.
     */
    public function getExtractionQualityMetrics(string $documentType): array
    {
        $stats = FileProcessingAnalytic::successfulType($documentType)
            ->select(
                DB::raw('COUNT(*) as total_extractions'),
                DB::raw('AVG(extraction_confidence) as avg_confidence'),
                DB::raw('MIN(extraction_confidence) as min_confidence'),
                DB::raw('MAX(extraction_confidence) as max_confidence'),
                DB::raw('AVG(processing_duration_ms) as avg_duration_ms')
            )
            ->first();

        $validationWarnings = FileProcessingAnalytic::successfulType($documentType)
            ->whereNotNull('validation_warnings')
            ->whereRaw('JSON_LENGTH(validation_warnings) > 0')
            ->count();

        return [
            'document_type' => $documentType,
            'total_extractions' => $stats->total_extractions ?? 0,
            'avg_confidence' => round($stats->avg_confidence ?? 0, 4),
            'min_confidence' => round($stats->min_confidence ?? 0, 4),
            'max_confidence' => round($stats->max_confidence ?? 0, 4),
            'avg_duration_ms' => round($stats->avg_duration_ms ?? 0),
            'extractions_with_warnings' => $validationWarnings,
        ];
    }

    /**
     * Get recent processing timeline (last 7 days by default).
     *
     * Shows processing volume and success rate over time.
     *
     * @return Collection
     */
    public function getProcessingTimeline(int $days = 7)
    {
        return FileProcessingAnalytic::query()
            ->where('created_at', '>=', now()->subDays($days))
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as total_count'),
                DB::raw('SUM(CASE WHEN processing_status = \'completed\' THEN 1 ELSE 0 END) as success_count'),
                DB::raw('SUM(CASE WHEN processing_status = \'failed\' THEN 1 ELSE 0 END) as failure_count'),
                DB::raw('AVG(processing_duration_ms) as avg_duration_ms')
            )
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date')
            ->get();
    }

    /**
     * Get validation warnings for a document type.
     *
     * Identifies common validation issues to improve schema.
     *
     * @return Collection
     */
    public function getValidationWarningsByType(string $documentType, int $limit = 20)
    {
        return FileProcessingAnalytic::successfulType($documentType)
            ->whereNotNull('validation_warnings')
            ->whereRaw('JSON_LENGTH(validation_warnings) > 0')
            ->with('file:id,filename,guid')
            ->select('id', 'file_id', 'validation_warnings', 'extraction_confidence', 'created_at')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }
}
