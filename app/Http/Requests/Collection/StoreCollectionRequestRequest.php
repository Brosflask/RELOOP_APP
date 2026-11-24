<?php

namespace App\Http\Requests\Collection;

use App\Enums\MaterialType;
use App\Models\CollectionRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Developer 2. Used for both creating and editing a pickup request. */
class StoreCollectionRequestRequest extends FormRequest
{
    public const TIME_SLOTS = ['morning', 'afternoon', 'evening'];

    /** Ghana mobile numbers: 0241234567 or +233241234567. */
    public const PHONE_PATTERN = '/^(\+233|0)[235]\d{8}$/';

    /** Ghana Post GPS address, e.g. GA-123-4567 or NT-0012-3456. */
    public const GPS_PATTERN = '/^[A-Za-z]{2}-\d{3,4}-\d{3,4}$/';

    public function authorize(): bool
    {
        $request = $this->route('collectionRequest');

        return $request instanceof CollectionRequest
            ? $this->user()->can('update', $request)
            : $this->user()->can('create', CollectionRequest::class);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'contact_phone' => preg_replace('/[\s-]/', '', (string) $this->input('contact_phone')),
            'gps_address' => $this->filled('gps_address') ? strtoupper(trim($this->input('gps_address'))) : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'pickup_address' => ['required', 'string', 'max:255'],
            'area' => ['nullable', 'string', 'max:100'],
            'gps_address' => ['nullable', 'string', 'regex:'.self::GPS_PATTERN],
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
            'contact_phone' => ['required', 'string', 'regex:'.self::PHONE_PATTERN],
            'preferred_date' => ['nullable', 'date', 'after_or_equal:today', 'before:+60 days'],
            'preferred_time_slot' => ['nullable', Rule::in(self::TIME_SLOTS)],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1', 'max:8'],
            'items.*.material_type' => ['required', 'distinct', Rule::enum(MaterialType::class)],
            'items.*.estimated_weight_kg' => ['nullable', 'numeric', 'gt:0', 'max:10000', 'decimal:0,3'],
        ];
    }

    public function messages(): array
    {
        return [
            'contact_phone.regex' => 'Enter a Ghana phone number, for example 0241234567.',
            'gps_address.regex' => 'Enter a Ghana Post GPS address, for example GA-123-4567.',
            'items.required' => 'Choose at least one type of plastic.',
            'items.*.material_type.distinct' => 'Each plastic type can be listed once.',
        ];
    }

    public function attributes(): array
    {
        return [
            'items.*.material_type' => 'plastic type',
            'items.*.estimated_weight_kg' => 'estimated weight',
        ];
    }
}
