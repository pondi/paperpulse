<?php

namespace App\Services\PulseDav\Import;

use App\Models\User;
use Illuminate\Support\Facades\Log;

class ImportValidator
{
    public static function validateSelections(array $selections, User $user): array
    {
        $valid = [];
        $invalid = [];
        
        foreach ($selections as $selection) {
            if (empty($selection['s3_path'])) {
                $invalid[] = ['reason' => 'Missing s3_path', 'data' => $selection];
                continue;
            }
            
            if (!S3PathResolver::pathExists($selection['s3_path'])) {
                Log::warning('[ImportValidator] S3 path does not exist', [
                    's3_path' => $selection['s3_path'],
                    'user_id' => $user->id
                ]);
            }
            
            $valid[] = $selection;
        }
        
        return ['valid' => $valid, 'invalid' => $invalid];
    }
}