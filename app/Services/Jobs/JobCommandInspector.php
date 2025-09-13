<?php

namespace App\Services\Jobs;

use Illuminate\Support\Facades\Log;

class JobCommandInspector
{
    public static function extractJobID(mixed $command): ?string
    {
        if (! $command) {
            return null;
        }

        try {
            if (method_exists($command, 'getJobID')) {
                return $command->getJobID();
            }

            $reflection = new \ReflectionClass($command);
            $property = $reflection->getProperty('jobID');
            $property->setAccessible(true);

            return $property->getValue($command);
        } catch (\Throwable $e) {
            Log::warning('Failed to extract jobID from command', [
                'command_class' => is_object($command) ? $command::class : gettype($command),
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}

