<?php

namespace App\Jobs;

class JobOrder
{
    /**
     * Document processing job chain order
     *
     * 1. ProcessDocument     - Initial processing, file validation
     * 2. AnalyzeDocument     - OCR, text extraction, analysis
     * 3. ApplyTags          - Auto-tag based on analysis
     * 4. DeleteWorkingFiles  - Cleanup temporary files
     */
    const DOCUMENT_CHAIN = [
        'ProcessDocument' => 1,
        'AnalyzeDocument' => 2,
        'ApplyTags' => 3,
        'DeleteWorkingFiles' => 4,
    ];

    /**
     * Receipt processing job chain order
     *
     * 1. ProcessReceipt      - OCR and basic data extraction
     * 2. MatchMerchant       - Merchant identification and matching
     * 3. ApplyTags          - Category and tag assignment
     * 4. DeleteWorkingFiles  - Cleanup temporary files
     */
    const RECEIPT_CHAIN = [
        'ProcessReceipt' => 1,
        'MatchMerchant' => 2,
        'ApplyTags' => 3,
        'DeleteWorkingFiles' => 4,
    ];

    /**
     * PulseDav sync job chain order
     *
     * 1. SyncPulseDavFiles         - Discover and sync files
     * 2. ProcessPulseDavFile       - Process individual files
     * 3. UpdatePulseDavFileStatus  - Update processing status
     */
    const PULSEDAV_CHAIN = [
        'SyncPulseDavFiles' => 1,
        'ProcessPulseDavFile' => 2,
        'UpdatePulseDavFileStatus' => 3,
    ];

    /**
     * Maintenance job priorities (independent jobs)
     */
    const MAINTENANCE_PRIORITY = [
        'CleanupRetainedFiles' => 1,   // Highest priority
        'DeletePulseDavFiles' => 2,
        'DeleteWorkingFiles' => 3,
    ];

    /**
     * Get job order for a specific domain
     */
    public static function getChainOrder(string $domain): array
    {
        return match ($domain) {
            'document' => self::DOCUMENT_CHAIN,
            'receipt' => self::RECEIPT_CHAIN,
            'pulsedav' => self::PULSEDAV_CHAIN,
            default => [],
        };
    }

    /**
     * Get next job in chain
     */
    public static function getNextJob(string $currentJob, string $domain): ?string
    {
        $chain = self::getChainOrder($domain);
        $currentOrder = $chain[$currentJob] ?? null;

        if ($currentOrder === null) {
            return null;
        }

        foreach ($chain as $job => $order) {
            if ($order === $currentOrder + 1) {
                return $job;
            }
        }

        return null;
    }

    /**
     * Get the order in the job chain for a given job name or class basename.
     * Legacy method - maintained for backward compatibility
     */
    public static function getOrder(string $jobName): int
    {
        $name = trim($jobName);

        return match ($name) {
            // Parent job for PulseDav imports
            'ProcessPulseDavFile', 'Process PulseDav File' => 0,

            // Standard processing chain
            'ProcessFile', 'Process File' => 1,
            'ProcessReceipt', 'Process Receipt' => 2,
            'ProcessDocument', 'Process Document' => 2,
            'MatchMerchant', 'Match Merchant' => 3,
            'AnalyzeDocument', 'Analyze Document' => 3,
            'ApplyTags', 'Apply Tags' => 4,
            'DeleteWorkingFiles', 'Delete Working Files' => 5,
            'UpdatePulseDavFileStatus', 'Update PulseDav File Status' => 6,

            // Maintenance/other
            'RestartJobChain', 'Restart Job Chain' => 0,

            default => 0,
        };
    }
}
