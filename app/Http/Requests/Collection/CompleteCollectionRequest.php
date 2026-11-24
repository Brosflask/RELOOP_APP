<?php

namespace App\Http\Requests\Collection;

use App\Enums\MaterialType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Developer 2. The weights a collector records when finishing a pickup. */
class CompleteCollectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('work', $this->route('collectionRequest'));
    }

    public function rules(): array
    {
        return [
            'lines' => ['required', 'array', 'min:1', 'max:8'],
            'lines.*.material_type' => ['required', Rule::enum(MaterialType::class)],
            'lines.*.actual_weight_kg' => ['required', 'numeric', 'min:0', 'max:10000', 'decimal:0,3'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'lines.*.material_type' => 'plastic type',
            'lines.*.actual_weight_kg' => 'weight',
        ];
    }
}
