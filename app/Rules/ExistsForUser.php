<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\DB;

/**
 * Validates that a record exists in the given table AND belongs to the
 * authenticated user. Replaces unscoped `exists:table,id` rules to
 * prevent cross-tenant data access.
 *
 * Usage:
 *   'tag_ids.*'    => ['integer', new ExistsForUser('tags')],
 *   'category_id'  => ['nullable', new ExistsForUser('categories')],
 *   'merchant_id'  => ['nullable', new ExistsForUser('merchants')],
 */
class ExistsForUser implements ValidationRule
{
    public function __construct(
        private string $table,
        private string $column = 'id',
        private string $ownerColumn = 'user_id',
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $exists = DB::table($this->table)
            ->where($this->column, $value)
            ->where($this->ownerColumn, auth()->id())
            ->exists();

        if (! $exists) {
            $fail('The selected :attribute does not exist.');
        }
    }
}
