<?php

namespace App\Contracts\Services;

use App\Models\PulseDavImportBatch;
use App\Models\User;

interface PulseDavImportContract
{
    /**
     * Import selected files/folders with tags
     */
    public function importSelections(User $user, array $selections, array $options = []): array;

    /**
     * Get import batch statistics
     */
    public function getBatchStats(PulseDavImportBatch $batch): array;

    /**
     * Get user's import history
     */
    public function getUserImportHistory(User $user, int $limit = 20): array;

    /**
     * Retry failed imports from a batch
     */
    public function retryFailedImports(PulseDavImportBatch $batch): int;

    /**
     * Cancel pending imports from a batch
     */
    public function cancelPendingImports(PulseDavImportBatch $batch): int;

    /**
     * Delete an import batch and reset associated files
     */
    public function deleteBatch(PulseDavImportBatch $batch): int;
}
