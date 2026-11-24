<?php

namespace App\Http\Requests\Marketplace;

use App\Models\Listing;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Developer 3. */
class StoreProductListingRequest extends FormRequest
{
    public const CATEGORIES = ['bags', 'baskets', 'furniture', 'home_decor', 'building_materials', 'fashion', 'toys', 'other'];

    public const CONDITIONS = ['new', 'used'];

    public function authorize(): bool
    {
        return $this->user()->can('createProduct', Listing::class);
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:5000'],
            'price' => ['required', 'numeric', 'gt:0', 'max:9999999', 'decimal:0,2'],
            'location' => ['nullable', 'string', 'max:100'],
            'product_category' => ['required', Rule::in(self::CATEGORIES)],
            'product_condition' => ['required', Rule::in(self::CONDITIONS)],
            'production_quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'materials' => ['required', 'array', 'min:1', 'max:10'],
            'materials.*.material_lot_id' => ['required', 'integer', 'distinct', 'exists:material_lots,id'],
            'materials.*.quantity_used' => ['nullable', 'numeric', 'gt:0', 'decimal:0,3'],
            'publish' => ['boolean'],
        ];
    }

    public function attributes(): array
    {
        return ['materials' => 'materials used', 'materials.*.material_lot_id' => 'lot', 'production_quantity' => 'pieces made'];
    }
}
