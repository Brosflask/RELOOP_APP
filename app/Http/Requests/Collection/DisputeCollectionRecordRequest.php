<?php

namespace App\Http\Requests\Collection;

use Illuminate\Foundation\Http\FormRequest;

/** Developer 2. */
class DisputeCollectionRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('respond', $this->route('record'));
    }

    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'min:5', 'max:1000']];
    }
}
