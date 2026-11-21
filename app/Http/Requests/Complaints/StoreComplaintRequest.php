<?php

namespace App\Http\Requests\Complaints;

use App\Enums\ComplaintCategory;
use App\Models\Order;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreComplaintRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'order_id' => ['nullable', 'integer', 'exists:orders,id'],
            'against_user_id' => ['nullable', 'integer', 'exists:users,id', Rule::notIn([$this->user()->id])],
            'category' => ['required', Rule::enum(ComplaintCategory::class)],
            'subject' => ['required', 'string', 'max:150'],
            'description' => ['required', 'string', 'max:5000'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if ($this->filled('order_id')) {
                $order = Order::find($this->integer('order_id'));
                if ($order && ! $order->isParticipant($this->user())) {
                    $validator->errors()->add('order_id', 'You can only complain about your own orders.');
                }
            }
        }];
    }
}
