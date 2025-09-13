<?php

namespace App\Http\Controllers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

abstract class BaseResourceController extends Controller
{
    /**
     * The model class name.
     */
    protected string $model;

    /**
     * The resource name for Inertia pages.
     */
    protected string $resource;

    /**
     * Validation rules for store/update operations.
     */
    protected array $validationRules = [];

    /**
     * Relations to eager load for index.
     */
    protected array $indexWith = [];

    /**
     * Relations to eager load for show.
     */
    protected array $showWith = [];

    /**
     * Default pagination size.
     */
    protected int $perPage = 20;

    /**
     * Searchable fields.
     */
    protected array $searchableFields = [];

    /**
     * Filterable fields.
     */
    protected array $filterableFields = [];

    /**
     * Default sort field and direction.
     */
    protected string $defaultSort = 'created_at';

    protected string $defaultSortDirection = 'desc';

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        $query = $this->model::query()->with($this->indexWith);

        // Apply search
        if ($search = $request->input('search')) {
            $query = $this->applySearch($query, $search);
        }

        // Apply filters
        foreach ($this->filterableFields as $field) {
            if ($value = $request->input($field)) {
                $query = $this->applyFilter($query, $field, $value);
            }
        }

        // Apply sorting
        $sortField = $request->input('sort', $this->defaultSort);
        $sortDirection = $request->input('sort_direction', $this->defaultSortDirection);
        $query->orderBy($sortField, $sortDirection);

        $items = $query->paginate($request->get('per_page', $this->perPage));

        return Inertia::render("{$this->resource}/Index", [
            'items' => $items->through(fn ($item) => $this->transformForIndex($item)),
            'filters' => $this->getFilters($request),
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show($id): Response
    {
        $item = $this->model::with($this->showWith)->findOrFail($id);

        $this->authorize('view', $item);

        return Inertia::render("{$this->resource}/Show", [
            'item' => $this->transformForShow($item),
            'meta' => $this->getShowMeta(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate($this->getValidationRules('store'));

        $item = $this->model::create($this->prepareForStore($validated));

        return $this->afterStore($item, $request);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $item = $this->model::findOrFail($id);

        $this->authorize('update', $item);

        $validated = $request->validate($this->getValidationRules('update'));

        $item->update($this->prepareForUpdate($validated, $item));

        return $this->afterUpdate($item, $request);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $item = $this->model::findOrFail($id);

        $this->authorize('delete', $item);

        $this->beforeDestroy($item);

        $item->delete();

        return $this->afterDestroy($item);
    }

    /**
     * Apply search to the query.
     */
    protected function applySearch($query, string $search)
    {
        if (empty($this->searchableFields)) {
            return $query;
        }

        return $query->where(function ($q) use ($search) {
            foreach ($this->searchableFields as $field) {
                $q->orWhere($field, 'like', "%{$search}%");
            }
        });
    }

    /**
     * Apply a filter to the query.
     */
    protected function applyFilter($query, string $field, $value)
    {
        return $query->where($field, $value);
    }

    /**
     * Transform item for index display.
     */
    protected function transformForIndex(Model $item): array
    {
        return $item->toArray();
    }

    /**
     * Transform item for show display.
     */
    protected function transformForShow(Model $item): array
    {
        return $item->toArray();
    }

    /**
     * Get validation rules for the given operation.
     */
    protected function getValidationRules(string $operation): array
    {
        return $this->validationRules;
    }

    /**
     * Prepare data for storage.
     */
    protected function prepareForStore(array $validated): array
    {
        // Automatically set user_id if the model uses BelongsToUser trait
        if (auth()->check() && in_array('App\Traits\BelongsToUser', class_uses_recursive($this->model))) {
            $validated['user_id'] = auth()->id();
        }

        return $validated;
    }

    /**
     * Prepare data for update.
     */
    protected function prepareForUpdate(array $validated, Model $item): array
    {
        return $validated;
    }

    /**
     * Get filters for the index page.
     */
    protected function getFilters(Request $request): array
    {
        $filters = ['search' => $request->input('search')];

        foreach ($this->filterableFields as $field) {
            $filters[$field] = $request->input($field);
        }

        return $filters;
    }

    /**
     * Get meta data for the show page.
     */
    protected function getShowMeta(): array
    {
        return [];
    }

    /**
     * Hook called after successful store.
     */
    protected function afterStore(Model $item, Request $request)
    {
        return redirect()->route($this->getRouteName().'.show', $item)
            ->with('success', ucfirst($this->getModelName()).' created successfully');
    }

    /**
     * Hook called after successful update.
     */
    protected function afterUpdate(Model $item, Request $request)
    {
        return redirect()->back()
            ->with('success', ucfirst($this->getModelName()).' updated successfully');
    }

    /**
     * Hook called before destroy.
     */
    protected function beforeDestroy(Model $item): void
    {
        // Override in child classes for cleanup
    }

    /**
     * Hook called after successful destroy.
     */
    protected function afterDestroy(Model $item)
    {
        return redirect()->route($this->getRouteName().'.index')
            ->with('success', ucfirst($this->getModelName()).' deleted successfully');
    }

    /**
     * Get the route name prefix.
     */
    protected function getRouteName(): string
    {
        return strtolower(str_replace('Controller', '', class_basename(static::class)));
    }

    /**
     * Get the model name for messages.
     */
    protected function getModelName(): string
    {
        return strtolower(class_basename($this->model));
    }
}
