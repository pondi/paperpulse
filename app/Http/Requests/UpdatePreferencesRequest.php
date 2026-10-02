<?php

namespace App\Http\Requests;

use App\Models\UserPreference;
use App\Rules\ExistsForUser;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdatePreferencesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'language' => 'required|string|in:en,nb',
            'timezone' => 'required|string|timezone',
            'date_format' => ['required', 'string', Rule::in(array_keys(UserPreference::getOptions()['date_formats']))],
            'currency' => 'required|string|in:NOK,USD,EUR,GBP,SEK,DKK',

            'auto_categorize' => 'boolean',
            'auto_organize_documents' => 'boolean',
            'extract_line_items' => 'boolean',
            'default_category_id' => ['nullable', new ExistsForUser('categories')],

            'notify_processing_complete' => 'boolean',
            'notify_processing_failed' => 'boolean',
            'notify_bulk_complete' => 'boolean',
            'notify_scanner_import' => 'boolean',
            'notify_weekly_summary_ready' => 'boolean',
            'notify_voucher_expiring' => 'boolean',
            'notify_warranty_expiring' => 'boolean',
            'email_notify_voucher_expiring' => 'boolean',
            'email_notify_warranty_expiring' => 'boolean',
            'email_notify_processing_complete' => 'boolean',
            'email_notify_processing_failed' => 'boolean',
            'email_notify_bulk_complete' => 'boolean',
            'email_notify_scanner_import' => 'boolean',
            'email_notify_weekly_summary' => 'boolean',
            'email_weekly_summary' => 'boolean',
            'weekly_summary_day' => 'required|string|in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',

            'receipt_list_view' => 'required|string|in:grid,list',
            'receipts_per_page' => 'required|integer|in:10,20,50,100',
            'default_sort' => ['required', 'string', Rule::in(array_keys(UserPreference::getOptions()['sort_options']))],

            'auto_process_scanner_uploads' => 'boolean',
            'delete_after_processing' => 'boolean',
            'file_retention_days' => 'required|integer|min:1|max:365',
            'retention_mode' => ['sometimes', 'required', Rule::in(['source_only', 'full_delete'])],
            'pulsedav_realtime_sync' => 'boolean',
        ];

    }

    public function messages(): array
    {
        return [
            'date_format.in' => 'Select a supported date format.',
            'default_sort.in' => 'Select a supported sort order.',
            'retention_mode.in' => 'Select a supported retention mode.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            foreach (array_diff(array_keys($this->all()), array_keys($this->rules()), ['_token', '_method']) as $field) {
                $validator->errors()->add($field, 'Unknown preference field.');
            }
        });
    }
}
