<?php

namespace App\Http\Requests\App\Auth;

use Illuminate\Foundation\Http\FormRequest;

class ExchangeGoogleLoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'size:64'],
        ];
    }
}
