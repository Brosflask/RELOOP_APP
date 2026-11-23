<?php

namespace App\Http\Requests\Marketplace;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Developer 3. */
class UpdateListingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('listing'));
    }

    public function rules(): array
    {
        $product = ! $this->route('listing')->isRawMaterial();

        return [
            'title' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:5000'],
            'price' => ['required', 'numeric', 'gt:0', 'max:9999999', 'decimal:0,2'],
            'location' => ['nullable', 'string', 'max:100'],
            'minimum_order_quantity' => ['nullable', 'numeric', 'gt:0', 'decimal:0,3'],
            'product_category' => [Rule::requiredIf($product), 'nullable', Rule::in(StoreProductListingRequest::CATEGORIES)],
            'product_condition' => [Rule::requiredIf($product), 'nullable', Rule::in(StoreProductListingRequest::CONDITIONS)],
            'materials' => [Rule::requiredIf($product), 'nullable', 'array', 'min:1', 'max:10'],
            'materials.*.material_lot_id' => ['required', 'integer', 'distinct', 'exists:material_lots,id'],
            'materials.*.quantity_used' => ['nullable', 'numeric', 'gt:0', 'decimal:0,3'],
        ];
    }
}
