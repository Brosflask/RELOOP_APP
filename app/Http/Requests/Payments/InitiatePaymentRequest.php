<?php

namespace App\Http\Requests\Payments;

use Illuminate\Foundation\Http\FormRequest;

class InitiatePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('pay', $this->route('order'));
    }

    /** Accept 024 123 4567, +233 24 123 4567 or 233241234567 and store 233241234567. */
    protected function prepareForValidation(): void
    {
        $digits = preg_replace('/\D+/', '', (string) $this->input('msisdn'));

        if (! $this->isSandbox() && preg_match('/^0\d{9}$/', $digits)) {
            $digits = '233'.substr($digits, 1);
        }

        $this->merge(['msisdn' => $digits]);
    }

    public function rules(): array
    {
        return [
            // The MTN sandbox uses its own test numbers, so only enforce the Ghana format outside it.
            'msisdn' => ['required', 'string', $this->isSandbox() ? 'regex:/^\d{8,15}$/' : 'regex:/^233[0-9]{9}$/'],
            'delivery_address' => [$this->route('order')->delivery_address ? 'nullable' : 'required', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return ['msisdn.regex' => 'Enter a valid MTN Mobile Money number, for example 024 123 4567.'];
    }

    private function isSandbox(): bool
    {
        return config('mtn_momo.environment') === 'sandbox';
    }
}
