<?php

namespace App\Http\Requests\Recycling;

use App\Enums\MaterialType;
use App\Models\ProcessingRecord;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Developer 3. */
class StoreProcessingRecordRequest extends FormRequest
{
    public const PROCESS_TYPES = ['sorting', 'washing', 'shredding', 'pelletising', 'baling'];

    public const FORMS = ['flakes', 'pellets', 'regrind', 'bales', 'sorted'];

    public function authorize(): bool
    {
        return $this->user()->can('create', ProcessingRecord::class);
    }

    public function rules(): array
    {
        return [
            'material_batch_id' => ['required', 'integer', 'exists:material_batches,id'],
            'process_type' => ['required', Rule::in(self::PROCESS_TYPES)],
            'input_weight_kg' => ['required', 'numeric', 'gt:0', 'max:1000000', 'decimal:0,3'],
            'processed_at' => ['nullable', 'date', 'before_or_equal:now'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'lots' => ['required', 'array', 'min:1', 'max:10'],
            'lots.*.material_type' => ['required', Rule::enum(MaterialType::class)],
            'lots.*.form' => ['nullable', Rule::in(self::FORMS)],
            'lots.*.grade' => ['nullable', 'string', 'max:20'],
            'lots.*.quantity_kg' => ['required', 'numeric', 'gt:0', 'max:1000000', 'decimal:0,3'],
        ];
    }

    public function attributes(): array
    {
        return [
            'material_batch_id' => 'batch',
            'input_weight_kg' => 'input weight',
            'lots.*.material_type' => 'material type',
            'lots.*.quantity_kg' => 'lot weight',
        ];
    }
}
