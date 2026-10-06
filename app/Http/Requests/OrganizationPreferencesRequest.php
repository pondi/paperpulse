<?php

namespace App\Http\Requests;

use App\Rules\ExistsForUser;
use Illuminate\Foundation\Http\FormRequest;

class OrganizationPreferencesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $label = ['sometimes', 'required', 'string', 'max:180', 'regex:#^[^/\\\\\\x00-\\x1f]+$#u'];

        return ['reset' => 'sometimes|boolean', 'removed_alias_ids' => 'sometimes|array|max:100',
            'removed_alias_ids.*' => ['integer', 'distinct', new ExistsForUser('organization_aliases')], 'naming_rules' => 'present_unless:reset,true|array:building_root,work_root,role_labels,work_structure',
            'naming_rules.building_root' => $label, 'naming_rules.work_root' => $label, 'naming_rules.work_structure' => 'sometimes|required|in:role,year',
            'naming_rules.role_labels' => 'sometimes|array:contracts,invoices,receipts,payslips,letters,other',
            'naming_rules.role_labels.*' => $label, 'aliases' => 'present_unless:reset,true|array|max:100',
            'aliases.*' => 'array:id,kind,alias,canonical_name', 'aliases.*.id' => ['nullable', 'integer', 'distinct', new ExistsForUser('organization_aliases')],
            'aliases.*.kind' => ['required', 'string', 'regex:/^[a-z][a-z_]{0,21}$/'], 'aliases.*.alias' => 'nullable|required_without:aliases.*.id|string|max:180',
            'aliases.*.canonical_name' => ['required', 'string', 'max:180', 'regex:#^[^/\\\\\\x00-\\x1f]+$#u']];
    }

    public function messages(): array
    {
        return ['aliases.*.id.exists' => 'Select an alias you own.', 'aliases.*.canonical_name.regex' => 'Use a folder label without paths or control characters.',
            'naming_rules.*.regex' => 'Use folder labels without paths or control characters.'];
    }
}
