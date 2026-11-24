<?php

namespace App\Http\Requests\Collection;

use Illuminate\Foundation\Http\FormRequest;

/** Developer 2. */
class CancelCollectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('cancel', $this->route('collectionRequest'));
    }

    public function rules(): array
    {
        return ['reason' => ['nullable', 'string', 'max:255']];
    }
}
