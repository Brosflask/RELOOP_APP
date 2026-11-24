<?php

namespace App\Http\Requests\Traceability;

use App\Enums\MaterialType;
use App\Models\MaterialBatch;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Developer 2. */
class StoreBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', MaterialBatch::class);
    }

    public function rules(): array
    {
        return [
            'material_type' => ['required', Rule::enum(MaterialType::class)],
            'record_ids' => ['required', 'array', 'min:1', 'max:500'],
            'record_ids.*' => ['integer', 'distinct'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return ['record_ids.required' => 'Choose at least one collection record.'];
    }
}
