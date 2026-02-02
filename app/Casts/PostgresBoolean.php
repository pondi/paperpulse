<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Custom cast for PostgreSQL boolean columns.
 *
 * PostgreSQL is strict about boolean types and doesn't accept integer 0/1.
 * This cast ensures proper boolean handling in queries.
 */
class PostgresBoolean implements CastsAttributes
{
    /**
     * Cast the given value from the database.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): bool
    {
        return (bool) $value;
    }

    /**
     * Prepare the given value for storage.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        // Return a raw expression for PostgreSQL boolean compatibility
        return DB::raw($value ? 'true' : 'false');
    }
}
