<?php

declare(strict_types=1);

namespace App\Services\Factories\Concerns;

/**
 * Provides a helper to check if an array has any non-empty values
 * for a given set of keys. Used by entity factories to determine
 * whether enough data exists to create a record.
 */
trait ChecksDataPresence
{
    protected function hasAny(array $data, array $keys): bool
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $data) && ! empty($data[$key])) {
                return true;
            }
        }

        return false;
    }
}
