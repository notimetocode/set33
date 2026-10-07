<?php

namespace App\Http\Requests\App\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StartGoogleLoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'locale' => [
                'sometimes',
                'nullable',
                'string',
                Rule::in(config('localization.available', ['en', 'ru'])),
            ],
        ];
    }
}
