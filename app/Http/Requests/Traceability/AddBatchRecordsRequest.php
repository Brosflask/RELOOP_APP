<?php

namespace App\Http\Requests\Traceability;

use Illuminate\Foundation\Http\FormRequest;

/** Developer 2. */
class AddBatchRecordsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage', $this->route('batch'));
    }

    public function rules(): array
    {
        return [
            'record_ids' => ['required', 'array', 'min:1', 'max:500'],
            'record_ids.*' => ['integer', 'distinct'],
        ];
    }

    public function messages(): array
    {
        return ['record_ids.required' => 'Choose at least one collection record.'];
    }
}
