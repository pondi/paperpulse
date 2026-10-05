<?php

namespace App\Http\Requests;

use App\Http\Requests\Api\V1\SearchRequest;
use App\Models\SavedSearch;
use Illuminate\Foundation\Http\FormRequest;

class SaveSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('name'))) {
            $this->merge(['name' => trim($this->input('name'))]);
        }
    }

    public function rules(): array
    {
        $savedSearch = $this->route('savedSearch');
        $scope = $this->input('scope', $savedSearch instanceof SavedSearch ? $savedSearch->scope : 'library');
        $filters = $scope === 'search'
            ? collect((new SearchRequest)->rules())->except(['q', 'page', 'limit', 'saved_search'])->mapWithKeys(fn ($rule, $key): array => ['filters.'.$key => $rule])->all()
            : LibraryRequest::filterRules('filters.');
        if ($scope === 'search') {
            $filters['filters.amount_max'] = ['nullable', 'numeric'];
            if ($this->filled('filters.amount_min')) {
                $filters['filters.amount_max'][] = 'gte:filters.amount_min';
            }
        }
        if ($this->filled('filters.date_from')) {
            $filters['filters.date_to'][] = 'after_or_equal:filters.date_from';
        }
        $keys = collect(array_keys($filters))->filter(fn (string $key): bool => ! str_contains(substr($key, 8), '.'))->map(fn (string $key): string => substr($key, 8))->implode(',');

        return [
            'name' => $this->isMethod('post') ? 'required|string|max:80' : 'sometimes|required|string|max:80',
            'scope' => $this->isMethod('post') ? 'required|in:library,search' : 'sometimes|required|in:library,search',
            'is_pinned' => 'sometimes|boolean',
            'filters' => [$this->isMethod('post') || $this->has('scope') ? 'required' : 'sometimes', 'array:'.$keys],
            ...$filters,
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Give your saved view a name.',
            'name.max' => 'Use a name of 80 characters or fewer.',
            'filters.array' => 'This view contains unsupported filters.',
            'filters.date_to.after_or_equal' => 'The end date must be on or after the start date.',
            'filters.amount_max.gte' => 'The maximum amount must be at least the minimum amount.',
        ];
    }
}
