<?php

namespace App\Http\Requests\Driver;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'      => 'sometimes|required|string|max:255',
            'phone'     => 'sometimes|required|string|max:20',
            'avatar'    => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            'fcm_token' => 'nullable|string|max:500',
        ];
    }
}
