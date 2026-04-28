<?php

namespace App\Http\Requests\License;

use Illuminate\Foundation\Http\FormRequest;

class UploadLicenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'image' => 'required|image|max:2048',
        ];
    }
}
