<?php

namespace App\Http\Requests\Order;

use Illuminate\Foundation\Http\FormRequest;

class CreateOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'service_type' => 'required|in:delivery,shopping,topup,bike,motor,car',
            'order_note'   => 'required|string|max:1000',
            'shipping_fee' => 'required|numeric|min:0',
            'bonus_fee'    => 'nullable|numeric|min:0',
            'is_freeship'  => 'boolean',
        ];
    }
}
