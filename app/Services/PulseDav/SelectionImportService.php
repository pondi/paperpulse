<?php

namespace App\Services\PulseDav;

use App\Services\PulseDav\Import\ImportOrchestrator;
use Illuminate\Support\Facades\Log;

class SelectionImportService
{
    public static function importSelected(\App\Models\User $user, array $selections, array $options = []): array
    {
        Log::info('[SelectionImportService] Delegating to ImportOrchestrator', [
            'user_id' => $user->id,
            'selections_count' => count($selections)
        ]);
        
        return ImportOrchestrator::orchestrateImport($user, $selections, $options);
    }
}