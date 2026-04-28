<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'          => 'required|string|max:255',
            'phone'         => 'required|string|unique:users',
            'password'      => 'required|string|min:6|confirmed',
            'shift_ids'     => 'sometimes|array',
            'shift_ids.*'   => 'exists:shifts,id',
            'city_id'       => 'required|exists:cities,id',
            'profile_photo' => 'required|image|mimes:jpg,jpeg,png|max:2048',
        ];
    }
}
