<?php

namespace App\Http\Requests\Traceability;

use App\Models\MaterialBatch;
use Illuminate\Foundation\Http\FormRequest;

/** Developer 2. */
class ConsolidateBatchesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', MaterialBatch::class);
    }

    public function rules(): array
    {
        return [
            'batch_ids' => ['required', 'array', 'min:2', 'max:50'],
            'batch_ids.*' => ['integer', 'distinct'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return ['batch_ids.min' => 'Choose at least two batches to merge.'];
    }
}
