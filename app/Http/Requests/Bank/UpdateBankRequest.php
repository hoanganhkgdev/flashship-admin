<?php

namespace App\Http\Requests\Bank;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBankRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'bank_code'    => 'nullable|string|max:20',
            'bank_name'    => 'nullable|string|max:255',
            'bank_account' => 'nullable|string|max:50',
            'bank_owner'   => 'nullable|string|max:255',
        ];
    }
}
