<?php

namespace App\Http\Requests\Traceability;

use Illuminate\Foundation\Http\FormRequest;

/** Developer 2. */
class RejectBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('receive', $this->route('batch'));
    }

    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'min:5', 'max:1000']];
    }
}
