<?php

namespace App\Http\Requests\Collection;

use App\Models\CollectionRecord;
use Illuminate\Foundation\Http\FormRequest;

/** Developer 2. Admin settles a disputed weight. */
class ResolveDisputeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('resolve', CollectionRecord::class);
    }

    public function rules(): array
    {
        return [
            'actual_weight_kg' => ['nullable', 'numeric', 'gt:0', 'max:10000', 'decimal:0,3'],
            'note' => ['required', 'string', 'min:5', 'max:1000'],
        ];
    }
}
