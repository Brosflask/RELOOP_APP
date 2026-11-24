<?php

namespace App\Http\Requests\Marketplace;

use App\Models\Listing;
use Illuminate\Foundation\Http\FormRequest;

/** Developer 3. */
class StoreRawMaterialListingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('createRawMaterial', Listing::class);
    }

    public function rules(): array
    {
        return [
            'material_lot_id' => ['required', 'integer', 'exists:material_lots,id'],
            'title' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:5000'],
            'price' => ['required', 'numeric', 'gt:0', 'max:9999999', 'decimal:0,2'],
            'location' => ['nullable', 'string', 'max:100'],
            'minimum_order_quantity' => ['nullable', 'numeric', 'gt:0', 'decimal:0,3'],
            'publish' => ['boolean'],
        ];
    }

    public function attributes(): array
    {
        return ['material_lot_id' => 'lot', 'price' => 'price per kg'];
    }
}
