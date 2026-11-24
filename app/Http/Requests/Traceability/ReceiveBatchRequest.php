<?php

namespace App\Http\Requests\Traceability;

use Illuminate\Foundation\Http\FormRequest;

/** Developer 2. */
class ReceiveBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('receive', $this->route('batch'));
    }

    public function rules(): array
    {
        return [
            'received_weight_kg' => ['required', 'numeric', 'gt:0', 'max:1000000', 'decimal:0,3'],
            'condition_notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function attributes(): array
    {
        return ['received_weight_kg' => 'weight received'];
    }
}
