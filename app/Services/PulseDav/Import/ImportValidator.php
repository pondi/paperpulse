<?php

namespace App\Services\PulseDav\Import;

use App\Models\User;
use Illuminate\Validation\ValidationException;

class ImportValidator
{
    public static function validateSelections(array $selections, User $user): array
    {
        $valid = [];
        $invalid = [];

        foreach ($selections as $selection) {
            if (! is_string($selection['s3_path'] ?? null) || $selection['s3_path'] === '') {
                $invalid[] = ['reason' => 'Missing s3_path', 'data' => $selection];

                continue;
            }

            try {
                $selection['s3_path'] = S3PathResolver::validateOwnedPath($selection['s3_path'], $user->id);
            } catch (ValidationException $e) {
                $invalid[] = ['reason' => 'Invalid owned path', 'data' => $selection];

                continue;
            }

            if (! S3PathResolver::pathExists($selection['s3_path'], $user->id)) {
                $invalid[] = ['reason' => 'Path does not exist', 'data' => $selection];

                continue;
            }

            $valid[] = $selection;
        }

        return ['valid' => $valid, 'invalid' => $invalid];
    }
}
