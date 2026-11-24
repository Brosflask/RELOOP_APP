<?php

namespace App\Http\Requests\Collection;

use App\Enums\MaterialType;
use App\Models\CollectionRecord;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Developer 2. Plastic dropped off at a collector without a pickup request. */
class StoreWalkInRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', CollectionRecord::class);
    }

    public function rules(): array
    {
        return [
            'material_type' => ['required', Rule::enum(MaterialType::class)],
            'actual_weight_kg' => ['required', 'numeric', 'gt:0', 'max:10000', 'decimal:0,3'],
            'collected_at' => ['nullable', 'date', 'before_or_equal:now'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function attributes(): array
    {
        return ['actual_weight_kg' => 'weight'];
    }
}
