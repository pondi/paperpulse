<?php

declare(strict_types=1);

namespace App\Traits;

use App\Services\Search\SearchFacetService;

/**
 * Invalidates the per-user search facet cache when a model is created,
 * updated, or deleted. Apply this trait to any Eloquent model that
 * affects search facet counts.
 *
 * Requires the model to have a `user_id` attribute.
 */
trait InvalidatesSearchFacets
{
    public static function bootInvalidatesSearchFacets(): void
    {
        $flush = function (self $model): void {
            $userId = $model->user_id ?? null;
            if ($userId) {
                $model->getConnection()->afterCommit(static fn () => SearchFacetService::invalidate((int) $userId));
            }
        };

        static::created($flush);
        static::updated($flush);
        static::deleted($flush);
        static::restored($flush);
    }
}
