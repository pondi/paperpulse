<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

abstract class BaseEntityApiController extends BaseApiController
{
    /**
     * The Eloquent model class.
     *
     * @return class-string
     */
    abstract protected function modelClass(): string;

    /**
     * The API resource class.
     *
     * @return class-string
     */
    abstract protected function resourceClass(): string;

    /**
     * Allowed sort fields for this entity.
     *
     * @return array<string>
     */
    abstract protected function allowedSortFields(): array;

    /**
     * Entity-specific filter validation rules.
     *
     * @return array<string, string>
     */
    protected function filterRules(): array
    {
        return [];
    }

    /**
     * Default eager-load relations for index.
     *
     * @return array<string>
     */
    protected function indexWith(): array
    {
        return ['tags'];
    }

    /**
     * Default eager-load relations for show.
     *
     * @return array<string>
     */
    protected function showWith(): array
    {
        return $this->indexWith();
    }

    /**
     * Apply entity-specific filters to the query.
     */
    protected function applyFilters(Builder $query, array $validated): Builder
    {
        return $query;
    }

    /**
     * Entity label for response messages (e.g. "Receipt", "Bank statement").
     */
    protected function entityLabel(): string
    {
        return class_basename($this->modelClass());
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate(array_merge(
            [
                'page' => 'nullable|integer|min:1',
                'per_page' => 'nullable|integer|min:1|max:100',
                'sort' => 'nullable|string|in:'.implode(',', $this->allowedSortFields()),
                'direction' => 'nullable|string|in:asc,desc',
            ],
            $this->filterRules(),
        ));

        $query = ($this->modelClass())::query()->with($this->indexWith());
        $query = $this->applyFilters($query, $validated);

        $sortField = $validated['sort'] ?? 'created_at';
        $sortDirection = $validated['direction'] ?? 'desc';
        $query->orderBy($sortField, $sortDirection);

        $results = $query->paginate($validated['per_page'] ?? 25);

        return $this->paginated(
            ($this->resourceClass())::collection($results),
            $this->entityLabel().'s retrieved',
        );
    }

    public function show(int $id): JsonResponse
    {
        $item = ($this->modelClass())::query()
            ->with($this->showWith())
            ->find($id);

        if (! $item) {
            return $this->notFound($this->entityLabel().' not found');
        }

        return $this->success(
            new ($this->resourceClass())($item),
            $this->entityLabel().' retrieved',
        );
    }
}
