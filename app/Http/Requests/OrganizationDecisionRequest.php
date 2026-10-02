<?php

namespace App\Http\Requests;

use App\Rules\ExistsForUser;
use Illuminate\Foundation\Http\FormRequest;

class OrganizationDecisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return ['recommendation_ids' => 'required|array|min:1|max:25',
            'recommendation_ids.*' => ['required', 'integer', 'distinct', new ExistsForUser('organization_recommendations')],
            'decision' => 'required|in:apply,decline', 'reason' => 'nullable|string|max:240', 'remove_empty' => 'sometimes|boolean'];
    }

    public function messages(): array
    {
        return ['recommendation_ids.required' => 'Select at least one recommendation.',
            'recommendation_ids.*.exists' => 'Select recommendations you own.', 'decision.in' => 'Apply or decline the selected recommendations.'];
    }
}
