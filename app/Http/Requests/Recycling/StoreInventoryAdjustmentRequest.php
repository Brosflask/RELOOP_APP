<?php

namespace App\Http\Requests\Recycling;

use Illuminate\Foundation\Http\FormRequest;

/** Developer 3. Signed change, for example "-2.5" for 2.5 kg damaged. */
class StoreInventoryAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('adjust', $this->route('lot'));
    }

    public function rules(): array
    {
        return [
            'quantity' => ['required', 'numeric', 'not_in:0', 'between:-1000000,1000000', 'decimal:0,3'],
            'reason' => ['required', 'string', 'max:250'],
        ];
    }
}
