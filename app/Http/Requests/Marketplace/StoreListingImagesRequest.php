<?php

namespace App\Http\Requests\Marketplace;

use Illuminate\Foundation\Http\FormRequest;

/** Developer 3. */
class StoreListingImagesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('listing'));
    }

    public function rules(): array
    {
        return [
            'images' => ['required', 'array', 'min:1', 'max:5'],
            'images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ];
    }
}
