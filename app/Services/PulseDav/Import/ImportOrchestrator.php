<?php

namespace App\Services\PulseDav\Import;

use App\Models\PulseDavImportBatch;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class ImportOrchestrator
{
    public static function orchestrateImport(User $user, array $selections, array $options): array
    {
        Log::info('[ImportOrchestrator] Starting import', [
            'user_id' => $user->id,
            'selections_count' => count($selections),
            'options' => $options
        ]);
        
        // Smart sync - only sync the specific selections if needed
        $synced = SmartSyncService::syncSelectionsIfNeeded($user, $selections);
        if ($synced > 0) {
            Log::info('[ImportOrchestrator] Smart sync created missing records', [
                'synced' => $synced
            ]);
        }
        
        $validated = ImportValidator::validateSelections($selections, $user);
        
        if (empty($validated['valid'])) {
            Log::warning('[ImportOrchestrator] No valid selections', [
                'invalid_count' => count($validated['invalid'])
            ]);
            return ['batch_id' => null, 'imported' => 0, 'skipped' => count($selections)];
        }
        
        $batch = PulseDavImportBatch::create([
            'user_id' => $user->id,
            'imported_at' => now(),
            'file_count' => 0,
            'tag_ids' => $options['tag_ids'] ?? [],
            'notes' => $options['notes'] ?? null,
        ]);
        
        $imported = 0;
        foreach ($validated['valid'] as $selection) {
            if (ImportProcessor::processItem($selection, $user, $batch, $options)) {
                $imported++;
            }
        }
        
        $batch->update(['file_count' => $imported]);
        
        Log::info('[ImportOrchestrator] Import completed', [
            'batch_id' => $batch->id,
            'imported' => $imported,
            'skipped' => count($selections) - $imported
        ]);
        
        return [
            'batch_id' => $batch->id,
            'imported' => $imported,
            'skipped' => count($selections) - $imported
        ];
    }
}