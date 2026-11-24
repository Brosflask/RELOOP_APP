<?php

namespace App\Http\Requests\Collection;

use App\Models\CollectionRequest;
use Illuminate\Foundation\Http\FormRequest;

/** Developer 2. Admin assigns a collector. */
class AssignCollectorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('assign', CollectionRequest::class);
    }

    public function rules(): array
    {
        return ['collector_id' => ['required', 'integer', 'exists:users,id']];
    }
}
