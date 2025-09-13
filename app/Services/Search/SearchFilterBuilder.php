<?php

namespace App\Services\Search;

use Illuminate\Http\Request;

class SearchFilterBuilder
{
    public static function build(Request $request): array
    {
        $filters = [
            'type' => $request->input('type', 'all'),
            'limit' => $request->input('limit', 20),
        ];

        foreach (['date_from', 'date_to', 'amount_min', 'amount_max', 'category', 'document_type'] as $key) {
            if ($request->has($key)) {
                $filters[$key] = $request->input($key);
            }
        }

        if ($request->has('tags')) {
            $filters['tags'] = is_array($request->input('tags'))
                ? $request->input('tags')
                : explode(',', (string) $request->input('tags'));
        }

        return $filters;
    }
}

