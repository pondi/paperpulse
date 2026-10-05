<?php

namespace App\Http\Requests;

use App\Rules\ExistsForUser;
use Illuminate\Foundation\Http\FormRequest;

class LibraryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public static function filterRules(string $prefix = ''): array
    {
        return [
            $prefix.'query' => 'nullable|string|max:200',
            $prefix.'type' => 'nullable|in:all,receipt,document,invoice,contract,bank_statement,voucher,warranty,return_policy',
            $prefix.'view' => 'nullable|in:all,recent,needs-review,processing,unpaid,expiring,shared',
            $prefix.'status' => 'nullable|in:pending,processing,completed,failed,needs_review',
            $prefix.'date_range' => 'nullable|in:all,last_30_days,this_month,this_year,custom',
            $prefix.'date_from' => 'nullable|date_format:Y-m-d',
            $prefix.'date_to' => ['nullable', 'date_format:Y-m-d'],
            $prefix.'collection_id' => ['nullable', 'integer', new ExistsForUser('collections')],
            $prefix.'tag_id' => ['nullable', 'integer', new ExistsForUser('tags')],
            $prefix.'sort' => 'nullable|in:newest,oldest,name',
            $prefix.'display' => 'nullable|in:list,grid',
        ];
    }

    public function rules(): array
    {
        $rules = [...self::filterRules(), 'page' => 'nullable|integer|min:1', 'saved_search' => 'nullable|integer'];
        if ($this->filled('date_from')) {
            $rules['date_to'][] = 'after_or_equal:date_from';
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'date_to.after_or_equal' => 'The end date must be on or after the start date.',
            'type.in' => 'Choose a document type from the library.',
            'view.in' => 'Choose an available smart view.',
        ];
    }
}
