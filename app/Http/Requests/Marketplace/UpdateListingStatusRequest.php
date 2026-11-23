<?php

namespace App\Http\Requests\Marketplace;

use App\Enums\ListingStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Developer 3. */
class UpdateListingStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('listing'));
    }

    public function rules(): array
    {
        return ['status' => ['required', Rule::in([ListingStatus::Active->value, ListingStatus::Paused->value, ListingStatus::Closed->value])]];
    }
}
