<?php

namespace App\Http\Requests\Messaging;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOfferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('makeOffer', $this->route('conversation'));
    }

    public function rules(): array
    {
        return [
            'unit_price' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:9999999999.99'],
            'quantity' => ['required', 'numeric', 'decimal:0,3', 'min:0.001', 'max:999999999.999'],
            'message' => ['nullable', 'string', 'max:500'],
            'parent_offer_id' => [
                'nullable', 'integer',
                Rule::exists('offers', 'id')->where('conversation_id', $this->route('conversation')->id),
            ],
        ];
    }
}
