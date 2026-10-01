<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;

class AuthorizedEntityRelations
{
    public static function load(MorphTo $relation): void
    {
        $constraints = [];
        foreach (Relation::morphMap() as $modelClass) {
            $constraints[$modelClass] = function (Builder $query) use ($modelClass): void {
                $query->withoutGlobalScope('user');
                foreach (['merchant', 'category'] as $relationName) {
                    if (method_exists($modelClass, $relationName)) {
                        $query->with([$relationName => fn ($related) => $related->withoutGlobalScope('user')]);
                    }
                }
            };
        }
        $relation->constrain($constraints);
    }
}
